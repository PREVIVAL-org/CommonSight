<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Value\UtcInstant;

/** A processing step on the items of a layer. */
interface PipelineStep
{
    /**
     * @param list<Item> $items
     * @return list<Item>
     */
    public function apply(array $items, UtcInstant $now): array;
}
