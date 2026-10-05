<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Value\UtcInstant;

/** Sets the validity of the assessments from measurement time and the layer's maximum age (B-01, B-02). */
final class FreshnessApplier implements PipelineStep
{
    public function __construct(private readonly FreshnessCheck $check, private readonly int $maxAgeHours) {}

    public function apply(array $items, UtcInstant $now): array
    {
        return array_map(
            fn(Item $item): Item => $item instanceof MeasurementItem
                ? $item->withAssessment($this->check->atFetch($item->assessment, $item->common->time, $this->maxAgeHours, $now))
                : $item,
            $items,
        );
    }
}
