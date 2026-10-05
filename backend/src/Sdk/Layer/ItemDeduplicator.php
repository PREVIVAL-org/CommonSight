<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Level;
use CommonSight\Model\Value\UtcInstant;

/**
 * Merges items with the same ID (Q-03; concept: sources as plugins, D6): the more critical one wins (warnings by CAP
 * severity, measurements by their assessment); on equal criticality the first one, i.e. the source with the smaller
 * rank (the items arrive in the order of their sources). The winner takes over a missing geometry from the other.
 */
final class ItemDeduplicator implements PipelineStep
{
    public function apply(array $items, UtcInstant $now): array
    {
        $unique = [];
        foreach ($items as $item) {
            $id = $item->common()->id;
            if (!isset($unique[$id])) {
                $unique[$id] = $item;
                continue;
            }
            [$kept, $other] = $this->criticality($item) > $this->criticality($unique[$id]) ? [$item, $unique[$id]] : [$unique[$id], $item];
            if ($kept->common()->geometry === null && $other->common()->geometry !== null) {
                $kept = $kept->withCommon($kept->common()->withGeometry($other->common()->geometry));
            }
            $unique[$id] = $kept;
        }

        return array_values($unique);
    }

    /** Higher is more critical; kinds without criticality (news, indices, ...) are all equal. */
    private function criticality(Item $item): int
    {
        return match (true) {
            $item instanceof WarningItem => 10 - $item->severity->rank(),
            $item instanceof MeasurementItem => match ($item->assessment->level) {
                Level::High => 3,
                Level::Elevated => 2,
                Level::Normal => 1,
                Level::Unknown => 0,
            },
            default => 0,
        };
    }
}
