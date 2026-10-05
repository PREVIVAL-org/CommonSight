<?php

declare(strict_types=1);

namespace CommonSight\Domain\Source;

use CommonSight\Sdk\Source\Deficit;
use CommonSight\Sdk\Source\DeficitKind;
use CommonSight\Sdk\Source\ParseStatistics;
use CommonSight\Sdk\Source\SourceExpectations;

/** Detects format changes of a source from its parse statistics (F-18, Architecture 4.14). */
final class DriftPolicy
{
    public function __construct(private readonly float $maxRejectedShare = 0.10) {}

    /** @return list<Deficit> */
    public function evaluate(ParseStatistics $statistics, SourceExpectations $expectations): array
    {
        $deficits = [];
        $total = $statistics->total();
        if ($total > 0 && $statistics->rejected / $total > $this->maxRejectedShare) {
            $deficits[] = new Deficit(DeficitKind::FormatDrift, ['rejected' => $statistics->rejected, 'total' => $total]);
        }
        $minimum = $expectations->minimumValid;
        if ($minimum !== null && $statistics->valid < $minimum) {
            $deficits[] = new Deficit(DeficitKind::BelowExpected, ['valid' => $statistics->valid, 'expected' => $minimum]);
        }

        return $deficits;
    }
}
