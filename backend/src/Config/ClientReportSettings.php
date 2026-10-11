<?php

declare(strict_types=1);

namespace CommonSight\Config;

/**
 * Error reports of the browsers (Architecture 6.6): whether `api/report.php` accepts them and how many days their log
 * files are kept. Off by default: the reports name browser, system and graphics card of a visitor.
 */
final readonly class ClientReportSettings
{
    public function __construct(public bool $enabled = false, public int $keepDays = 30)
    {
        if ($keepDays < 1) {
            throw new ConfigError('clientReports.keepDays must be a positive integer');
        }
    }
}
