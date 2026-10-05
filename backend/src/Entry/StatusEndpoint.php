<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Application\StatusAnswer;
use CommonSight\Application\StatusQuery;
use CommonSight\Domain\Status\ScopeParameter;
use CommonSight\Infrastructure\Http\ResponseCompletion;
use CommonSight\Model\Decoded;
use CommonSight\Model\Value\UtcInstant;

/**
 * Accepts the HTTP request to the status endpoint, outputs the response and then starts the fallback (A-01 to A-07, Architecture 6).
 */
final class StatusEndpoint
{
    public function __construct(
        private readonly ScopeParameter $scopeParameter,
        private readonly StatusQuery $query,
        private readonly ResponseCompletion $completion,
        private readonly StatusEndpointFactory $factory,
    ) {}

    /**
     * @param array<mixed> $get
     * @param array<mixed> $server
     */
    public function handle(array $get, array $server): void
    {
        $request = Decoded::of($server);
        $method = $request->get('REQUEST_METHOD')->string() ?? 'GET';
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if ($method !== 'GET' && $method !== 'HEAD') {
            $this->plain(405, 'Only GET and HEAD', ['Allow: GET, HEAD']);

            return;
        }
        $scope = $this->scopeParameter->parse($get['scope'] ?? null);
        if ($scope === null || count($get) !== 1) {
            $this->plain(400, 'Parameter scope missing or invalid (DE, AT, CH)');

            return;
        }
        $now = UtcInstant::fromTimestamp(time());
        $answer = $this->query->answer($scope, $now, $request->get('HTTP_IF_NONE_MATCH')->string());
        $this->send($answer, $method === 'HEAD');
        $this->fallback($answer, $now);
    }

    private function send(StatusAnswer $answer, bool $headOnly): void
    {
        http_response_code($answer->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache');
        header('ETag: ' . $answer->etag);
        if ($this->completion->needsExplicitClose()) {
            header('Content-Length: ' . strlen($answer->body));
            header('Connection: close');
        }
        if (!$headOnly) {
            echo $answer->body;
        }
    }

    private function fallback(StatusAnswer $answer, UtcInstant $now): void
    {
        if ($this->factory->stalenessPolicy()->mostStale($answer->candidates, $now) === null) {
            return;
        }
        // The response is complete; the due sources of the most stale layer run within the fallback budget of their lane.
        $this->completion->finish($this->factory->fallbackBudgetSec());
        $this->factory->fallbackTrigger()->trigger($answer->candidates, $now);
    }

    /** @param list<string> $headers */
    private function plain(int $status, string $message, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        foreach ($headers as $header) {
            header($header);
        }
        echo $message, "\n";
    }
}
