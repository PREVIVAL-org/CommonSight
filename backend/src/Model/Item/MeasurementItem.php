<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

use CommonSight\Model\Assessment;
use CommonSight\Model\Fact;
use CommonSight\Model\Msg;

/** Measurement of a station with reference, assessment and additional values. */
final readonly class MeasurementItem implements Item
{
    /** @param list<Fact> $facts */
    public function __construct(
        public ItemCommon $common,
        public CatalogTerm $quantity,
        public float $value,
        public string $unit,
        public Msg $reference,
        public Assessment $assessment,
        public array $facts = [],
    ) {
        if (!is_finite($value) || $unit === '') {
            throw new \InvalidArgumentException('Invalid measured value: ' . $common->id);
        }
    }

    public function common(): ItemCommon
    {
        return $this->common;
    }

    public function withCommon(ItemCommon $common): static
    {
        return new self($common, $this->quantity, $this->value, $this->unit, $this->reference, $this->assessment, $this->facts);
    }

    public function withAssessment(Assessment $assessment): self
    {
        return new self($this->common, $this->quantity, $this->value, $this->unit, $this->reference, $assessment, $this->facts);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['kind' => 'measurement'] + $this->common->toArray() + [
            'quantity' => $this->quantity->value,
            'value' => $this->value,
            'unit' => $this->unit,
            'reference' => $this->reference,
            'assessment' => $this->assessment,
            'facts' => $this->facts,
        ];
    }
}
