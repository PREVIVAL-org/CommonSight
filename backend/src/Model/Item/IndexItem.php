<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

use CommonSight\Model\Msg;

/** Value on a scale, e.g. Kp 0-9 or NOAA G 0-5, optionally with history. */
final readonly class IndexItem implements Item
{
    /** @param list<float>|null $history */
    public function __construct(
        public ItemCommon $common,
        public Msg $name,
        public float $value,
        public IndexScale $scale,
        public Msg $description,
        public ?array $history = null,
    ) {
        // NaN or INF would break the JSON of the snapshot and with it the whole layer.
        if (!is_finite($value) || array_filter($history ?? [], static fn(float $v): bool => !is_finite($v)) !== []) {
            throw new \InvalidArgumentException('Invalid index value: ' . $common->id);
        }
    }

    public function common(): ItemCommon
    {
        return $this->common;
    }

    public function withCommon(ItemCommon $common): static
    {
        return new self($common, $this->name, $this->value, $this->scale, $this->description, $this->history);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = ['kind' => 'index'] + $this->common->toArray() + [
            'name' => $this->name,
            'value' => $this->value,
            'scale' => $this->scale,
        ];
        if ($this->history !== null) {
            $data['history'] = $this->history;
        }
        $data['description'] = $this->description;

        return $data;
    }
}
