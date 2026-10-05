<?php

declare(strict_types=1);

namespace CommonSight\Tests\Domain\Geo;

use CommonSight\Domain\Geo\NearbyFinder;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\RegionId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** DACH countries and regions within the border radius (vicinityKm) of a point (ADR 0038): in the border zone all of them, in a country the other two. */
final class NearbyFinderTest extends TestCase
{
    private static function finder(): NearbyFinder
    {
        $data = Fixtures::generated();
        $countries = [];
        foreach (Scope::countries() as $country) {
            $countries[$country->value] = [$data->grid($country), $data->regions($country)];
        }

        return new NearbyFinder($countries);
    }

    /** @return list<string> */
    private static function ids(array $regionIds): array
    {
        return array_map(static fn(RegionId $id): string => $id->value, $regionIds);
    }

    public function testAPointOfACountryIsNearItsOwnAndTheOtherCountries(): void
    {
        $kufstein = self::finder()->near(Coordinate::fromLatLon(47.583, 12.17));

        self::assertSame([Scope::DE, Scope::AT, Scope::CH], $kufstein->countries, 'Switzerland is a good 140 km away');
        self::assertContains('DE-BY', self::ids($kufstein->regionIds));
        self::assertContains('AT-7', self::ids($kufstein->regionIds), 'own region');
        self::assertContains('AT-5', self::ids($kufstein->regionIds), 'neighbouring region in the same country');
        self::assertNotContains('CH-GE', self::ids($kufstein->regionIds), 'Geneva is more than 400 km away');
    }

    public function testRegionsOfTheSameCountryWithinReach(): void
    {
        $kassel = self::finder()->near(Coordinate::fromLatLon(51.312, 9.48));

        self::assertSame([Scope::DE], $kassel->countries, 'Austria and Switzerland are further away');
        self::assertContains('DE-HE', self::ids($kassel->regionIds));
        self::assertContains('DE-NI', self::ids($kassel->regionIds));
    }

    public function testAPointOfTheBorderZoneIsNearAllCountriesInReach(): void
    {
        $basel = self::finder()->near(Coordinate::fromLatLon(47.75, 7.34));

        self::assertSame([Scope::DE, Scope::AT, Scope::CH], $basel->countries, 'Mulhouse: Vorarlberg a good 200 km');
        self::assertContains('CH-BS', self::ids($basel->regionIds));
        self::assertContains('DE-BW', self::ids($basel->regionIds));
        self::assertNotContains('AT-9', self::ids($basel->regionIds), 'Vienna is much further than the border radius');
    }

    public function testAPointFarFromACountryIsNotNearIt(): void
    {
        $amsterdam = self::finder()->near(Coordinate::fromLatLon(52.368, 4.904));

        self::assertSame([Scope::DE], $amsterdam->countries, 'Austria and Switzerland further than the border radius');
    }
}
