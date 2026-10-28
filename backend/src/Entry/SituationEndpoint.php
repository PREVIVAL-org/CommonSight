<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Application\SituationQuery;
use CommonSight\Config\SituationSettings;
use CommonSight\Model\Decoded;
use CommonSight\Model\Value\UtcInstant;

/**
 * Accepts the HTTP request to the situation endpoint (concept: situation level, L-D4): the level per country, readable
 * by the sites named in situation.allowOrigins (CORS). Runs no fallback: the status endpoint and cron keep the layer
 * current, a stale one is answered as unknown.
 */
final class SituationEndpoint
{
    /** Browsers and proxies may keep the answer this long; the forum box does not ask more often per visitor. */
    private const MAX_AGE_SEC = 60;

    public function __construct(private readonly SituationQuery $query, private readonly SituationSettings $settings) {}

    /**
     * @param array<mixed> $get
     * @param array<mixed> $server
     */
    public function handle(array $get, array $server): void
    {
        $request = Decoded::of($server);
        $method = $request->get('REQUEST_METHOD')->string() ?? 'GET';
        header('X-Content-Type-Options: nosniff');
        header('Vary: Origin');
        $origin = $request->get('HTTP_ORIGIN')->string();
        if ($origin !== null && $this->settings->allows($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        if ($method !== 'GET' && $method !== 'HEAD') {
            $this->plain(405, 'Only GET and HEAD', ['Allow: GET, HEAD']);

            return;
        }
        if ($get !== []) {
            $this->plain(400, 'No parameters');

            return;
        }
        $answer = $this->query->answer(UtcInstant::fromTimestamp(time()), $request->get('HTTP_IF_NONE_MATCH')->string());
        http_response_code($answer->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=' . self::MAX_AGE_SEC);
        header('ETag: ' . $answer->etag);
        if ($method !== 'HEAD') {
            echo $answer->body;
        }
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
