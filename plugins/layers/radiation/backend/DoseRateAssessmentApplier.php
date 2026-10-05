<?php

declare(strict_types=1);

namespace CommonSight\Plugin\RadiationLayer;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Value\DoseRate;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Layer\PipelineStep;

/**
 * Classifies the dose rates of the radiation layer by its own display thresholds (B-13): a step of the layer, because
 * the thresholds are the operator's, not the source's (concept: sources as plugins, P5). Other items stay as they are.
 */
final class DoseRateAssessmentApplier implements PipelineStep
{
    public function __construct(private readonly RadiationAssessor $assessor) {}

    public function apply(array $items, UtcInstant $now): array
    {
        return array_map(
            fn(Item $item): Item => $item instanceof MeasurementItem && $item->quantity->value === 'doseRate'
                ? $item->withAssessment($this->assessor->assess(DoseRate::fromValueAndUnit($item->value, $item->unit)))
                : $item,
            $items,
        );
    }
}
