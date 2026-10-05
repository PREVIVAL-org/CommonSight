<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

/** Earthquake with magnitude, depth and the source's place description. */
final readonly class EarthquakeItem implements Item
{
    public function __construct(
        public ItemCommon $common,
        public float $magnitude,
        public float $depthKm,
        public string $place,
    ) {
        if (!is_finite($magnitude) || !is_finite($depthKm)) {
            throw new \InvalidArgumentException('Invalid earthquake values: ' . $common->id);
        }
    }

    public function common(): ItemCommon
    {
        return $this->common;
    }

    public function withCommon(ItemCommon $common): static
    {
        return new self($common, $this->magnitude, $this->depthKm, $this->place);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['kind' => 'earthquake'] + $this->common->toArray() + [
            'magnitude' => $this->magnitude,
            'depthKm' => $this->depthKm,
            'place' => $this->place,
        ];
    }
}
