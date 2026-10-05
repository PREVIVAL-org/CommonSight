<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Item\WarningItem;

/** Counts warnings without an evaluable area (Q-W-AT-05, Q-W-CH-03). */
final class WarningsWithoutGeometry implements DeficitDetector
{
    public function detect(array $items): ?Deficit
    {
        $count = count(array_filter($items, static fn($item): bool => $item instanceof WarningItem && $item->common->geometry === null));

        return $count > 0 ? new Deficit(DeficitKind::MissingGeometry, ['count' => $count]) : null;
    }
}
