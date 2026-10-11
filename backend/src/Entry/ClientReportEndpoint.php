<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Application\ClientReportIntake;
use CommonSight\Application\ClientReportOutcome;
use CommonSight\Model\Decoded;

/**
 * Accepts the error report of a browser at `api/report.php` (Architecture 6.6): a small JSON POST from the own site,
 * answered without a body. The status code is all the browser learns.
 */
final class ClientReportEndpoint
{
    public const MAX_BYTES = 16_384;

    public function __construct(private readonly ?ClientReportIntake $intake) {}

    /**
     * @param array<mixed> $server
     * @param array<mixed> $cookies
     * @param string $body at most MAX_BYTES + 1 bytes of the request body
     * @return int the HTTP status of the answer
     */
    public function answer(array $server, array $cookies, string $body): int
    {
        $request = Decoded::of($server);
        $site = $request->get('HTTP_SEC_FETCH_SITE')->string();

        return match (true) {
            // Switched off (clientReports.enabled): as if there were no endpoint.
            $this->intake === null => 404,
            ($request->get('REQUEST_METHOD')->string() ?? 'GET') !== 'POST' => 405,
            // A report sent from a foreign site, with the visitor's cookies; old browsers without the header pass.
            $site !== null && $site !== 'same-origin' => 403,
            strlen($body) > self::MAX_BYTES => 413,
            default => match ($this->intake->take(VisitorRequest::of($cookies, self::pageUrl($request)), $body)) {
                ClientReportOutcome::Accepted => 204,
                ClientReportOutcome::Refused => 403,
                ClientReportOutcome::Invalid => 400,
                ClientReportOutcome::Full => 429,
            },
        };
    }

    /** The start page of the site (one level above api/), for a provider that looks at it. */
    private static function pageUrl(Decoded $request): string
    {
        $https = $request->get('HTTPS')->string() === 'on' || $request->get('REQUEST_SCHEME')->string() === 'https';
        $path = (string) strtok($request->get('REQUEST_URI')->string() ?? '/', '?');

        return ($https ? 'https' : 'http') . '://' . ($request->get('HTTP_HOST')->string() ?? 'localhost') . rtrim(dirname($path, 2), '/') . '/';
    }
}
