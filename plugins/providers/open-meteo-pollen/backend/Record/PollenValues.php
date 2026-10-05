<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoPollen\Record;

use CommonSight\Model\Place\City;
use CommonSight\Model\Value\UtcInstant;

/** Current pollen concentrations of a place in grains per m³, by pollen type (e.g. grass). */
final readonly class PollenValues
{
    /** @param non-empty-array<string, float> $concentrations */
    public function __construct(public City $city, public ?UtcInstant $time, public array $concentrations) {}

    /** @return array{string, float} the type with the highest concentration and its value; the first on a tie */
    public function strongest(): array
    {
        $type = (string) array_key_first($this->concentrations);
        foreach ($this->concentrations as $candidate => $value) {
            if ($value > $this->concentrations[$type]) {
                $type = $candidate;
            }
        }

        return [$type, $this->concentrations[$type]];
    }
}
