<?php

declare(strict_types=1);

namespace CommonSight\Port;

use CommonSight\Model\Value\UtcInstant;

/** Keeps the error reports of the browsers (Architecture 6.6). */
interface ClientReportLog
{
    /**
     * @param array<string, scalar|null|array<string, scalar|null>> $report
     * @return bool false: the log of the day is full, the report is dropped
     */
    public function append(array $report, UtcInstant $now): bool;
}
