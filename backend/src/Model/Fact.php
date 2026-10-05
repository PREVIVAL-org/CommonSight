<?php

declare(strict_types=1);

namespace CommonSight\Model;

/** Additional value of an item, e.g. wind 12 km/h (D-16). */
final readonly class Fact implements \JsonSerializable
{
    public function __construct(public Msg $label, public int|float|string $value, public ?string $unit = null)
    {
        if (is_float($value) && !is_finite($value)) {
            throw new \InvalidArgumentException('Invalid value of a fact: ' . $label->key);
        }
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = ['label' => $this->label, 'value' => $this->value];
        if ($this->unit !== null) {
            $data['unit'] = $this->unit;
        }

        return $data;
    }
}
