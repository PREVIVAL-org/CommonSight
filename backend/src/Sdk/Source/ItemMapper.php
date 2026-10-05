<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Item\Item;

/** Maps a source record of its source to an item of the model; null = record is dropped. */
interface ItemMapper
{
    /** @throws \InvalidArgumentException if the record does not belong to the source or no valid item results */
    public function map(object $record, ParseContext $context): ?Item;
}
