<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

use CommonSight\Model\Fact;
use CommonSight\Model\Msg;

/** Value computed from a model for a place, not a measurement. */
final readonly class ModelValueItem implements Item
{
    /** @param list<Fact> $facts */
    public function __construct(
        public ItemCommon $common,
        public CatalogTerm $quantity,
        public float $value,
        public string $unit,
        public Msg $summary,
        public array $facts = [],
    ) {
        if (!is_finite($value) || $unit === '') {
            throw new \InvalidArgumentException('Invalid model value: ' . $common->id);
        }
    }

    public function common(): ItemCommon
    {
        return $this->common;
    }

    public function withCommon(ItemCommon $common): static
    {
        return new self($common, $this->quantity, $this->value, $this->unit, $this->summary, $this->facts);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['kind' => 'modelValue'] + $this->common->toArray() + [
            'quantity' => $this->quantity->value,
            'value' => $this->value,
            'unit' => $this->unit,
            'summary' => $this->summary,
            'facts' => $this->facts,
        ];
    }
}
