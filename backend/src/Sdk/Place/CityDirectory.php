<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Place;

use CommonSight\Model\Place\City;
use CommonSight\Model\Value\Scope;

/** Fixed list of places per country for weather and air, in the order of the batch query (Q-WE-01, Appendix A). */
final class CityDirectory
{
    /** @param list<City> $cities */
    public function __construct(private readonly array $cities) {}

    /** @return list<City> */
    public function forCountry(Scope $scope): array
    {
        return array_values(array_filter($this->cities, static fn(City $city): bool => $city->country === $scope));
    }
}
