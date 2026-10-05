<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Item\Item;

/** Detects a specific gap in the items of a source. */
interface DeficitDetector
{
    /** @param list<Item> $items */
    public function detect(array $items): ?Deficit;
}
