<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Report\ClientReportParser;
use CommonSight\Port\ClientReportLog;
use CommonSight\Sdk\Auth\AuthRequest;

/**
 * Takes the error report of a browser (Architecture 6.6): only from a visitor the start page admits (the same gate as
 * gate.php), so nobody outside the community can fill the log. Without an auth provider the page and the reports are
 * open to everyone.
 */
final class ClientReportIntake
{
    public function __construct(
        private readonly StartPageGate $gate,
        private readonly ClientReportParser $parser,
        private readonly ClientReportLog $log,
    ) {}

    public function take(AuthRequest $request, string $body): ClientReportOutcome
    {
        if ($this->gate->refuse($request) !== null) {
            return ClientReportOutcome::Refused;
        }
        try {
            $report = $this->parser->parse($body);
        } catch (\InvalidArgumentException) {
            return ClientReportOutcome::Invalid;
        }

        return $this->log->append($report, $request->now) ? ClientReportOutcome::Accepted : ClientReportOutcome::Full;
    }
}
