<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

use CommonSight\Model\Msg;

/** Scale of an index with minimum, maximum and label. */
final readonly class IndexScale implements \JsonSerializable
{
    public function __construct(public float $min, public float $max, public Msg $name)
    {
        if ($max <= $min) {
            throw new \InvalidArgumentException('Scale without range');
        }
    }

    /** @return array{min: float, max: float, name: Msg} */
    public function jsonSerialize(): array
    {
        return ['min' => $this->min, 'max' => $this->max, 'name' => $this->name];
    }
}
