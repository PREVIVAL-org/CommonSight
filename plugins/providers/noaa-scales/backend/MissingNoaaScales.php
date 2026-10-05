<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaScales;

use CommonSight\Model\Item\IndexItem;
use CommonSight\Sdk\Source\Deficit;
use CommonSight\Sdk\Source\DeficitDetector;

/** Detects which of the NOAA scales G, R, S are missing from the result (Q-SP-05). */
final class MissingNoaaScales implements DeficitDetector
{
    public function detect(array $items): ?Deficit
    {
        $present = array_map(static fn($item): string => $item instanceof IndexItem ? $item->common->title : '', $items);
        $missing = array_values(array_diff(ScalesParser::LETTERS, $present));

        return $missing === [] ? null : Deficit::own('source.noaa-scales.issue.missingScale', ['scale' => implode(', ', $missing)]);
    }
}
