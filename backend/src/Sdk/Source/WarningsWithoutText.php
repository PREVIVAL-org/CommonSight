<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Item\WarningItem;

/** Counts warnings without text sections, for sources with a detail query (Q-W-AT-09). */
final class WarningsWithoutText implements DeficitDetector
{
    public function detect(array $items): ?Deficit
    {
        $count = count(array_filter($items, static fn($item): bool => $item instanceof WarningItem && $item->sections === []));

        return $count > 0 ? new Deficit(DeficitKind::MissingDetailText, ['count' => $count]) : null;
    }
}
