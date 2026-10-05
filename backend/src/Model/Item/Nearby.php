<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

use CommonSight\Model\Value\RegionId;
use CommonSight\Model\Value\Scope;

/** DACH countries and regions near an item of the border zone, for the section "Grenzgebiet" (border area) in the lists. */
final readonly class Nearby
{
    /**
     * @param list<Scope> $countries countries within the country distance, in the fixed order DE, AT, CH
     * @param list<RegionId> $regionIds regions within the region distance
     */
    public function __construct(public array $countries, public array $regionIds) {}

    /** @return array{countries: list<string>, regionIds: list<string>} */
    public function toArray(): array
    {
        return [
            'countries' => array_map(static fn(Scope $country): string => $country->value, $this->countries),
            'regionIds' => array_map(static fn(RegionId $id): string => $id->value, $this->regionIds),
        ];
    }
}
