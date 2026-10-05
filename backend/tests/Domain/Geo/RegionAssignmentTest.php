<?php

declare(strict_types=1);

namespace CommonSight\Tests\Domain\Geo;

use CommonSight\Domain\Geo\AreaNameMatcher;
use CommonSight\Domain\Geo\GeometrySamples;
use CommonSight\Domain\Geo\InteriorSamples;
use CommonSight\Domain\Geo\PointInPolygon;
use CommonSight\Domain\Geo\RegionLocator;
use CommonSight\Domain\Geo\RegionMatcher;
use CommonSight\Model\Geometry;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\RegionMatch;
use CommonSight\Model\Place\Region;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\RegionId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Geo\ScanlineIntervals;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** U-14, V2: region assignment in the fetcher with the real region areas and the grid from the build. */
final class RegionAssignmentTest extends TestCase
{
    /** @return iterable<string, array{Scope}> */
    public static function countries(): iterable
    {
        foreach (Scope::countries() as $scope) {
            yield $scope->value => [$scope];
        }
    }

    /** For random points the grid returns the same regions as the exact check against all areas. */
    #[DataProvider('countries')]
    public function testGridAgreesWithExactPointInPolygon(Scope $scope): void
    {
        $regions = Fixtures::generated()->regions($scope);
        $locator = new RegionLocator(Fixtures::generated()->grid($scope), $regions, new PointInPolygon());
        $pip = new PointInPolygon();
        $bounds = Fixtures::generated()->countries()[$scope->value]->bounds;
        mt_srand(4711);
        // 1000 seeded points per country hit every border cell type of the grid; more only make the suite slow.
        for ($i = 0; $i < 1000; $i++) {
            $lon = $bounds->west + ($bounds->east - $bounds->west) * mt_rand() / mt_getrandmax();
            $lat = $bounds->south + ($bounds->north - $bounds->south) * mt_rand() / mt_getrandmax();
            $exact = array_values(array_map(static fn(Region $r): string => $r->id->value, array_filter($regions, static fn(Region $r): bool => $pip->inAny($r->polygons, $lon, $lat))));
            $fast = array_map(static fn(Region $r): string => $r->id->value, $locator->regionsAt($lon, $lat));
            self::assertSame($exact, $fast, sprintf('point %F, %F', $lon, $lat));
        }
    }

    /** @return iterable<string, array{Scope, float, float, string}> */
    public static function knownPlaces(): iterable
    {
        yield 'Berlin' => [Scope::DE, 13.405, 52.52, 'DE-BE'];
        yield 'Munich' => [Scope::DE, 11.582, 48.1351, 'DE-BY'];
        yield 'Bremerhaven (exclave)' => [Scope::DE, 8.5809, 53.5396, 'DE-HB'];
        yield 'Vienna' => [Scope::AT, 16.3738, 48.2082, 'AT-9'];
        yield 'Innsbruck' => [Scope::AT, 11.4041, 47.2692, 'AT-7'];
        yield 'Bern' => [Scope::CH, 7.4474, 46.948, 'CH-BE'];
        yield 'Lugano' => [Scope::CH, 8.9511, 46.0037, 'CH-TI'];
        yield 'Moutier (Jura since 2026)' => [Scope::CH, 7.3717, 47.2787, 'CH-JU'];
    }

    #[DataProvider('knownPlaces')]
    public function testAssignsPointsToTheirRegion(Scope $scope, float $lon, float $lat, string $expected): void
    {
        [$ids, $match] = $this->matcher($scope)->match(new ItemCommon('p', 't', 'https://x.example/', position: Coordinate::fromLonLat($lon, $lat)), null);

        self::assertSame([$expected], array_map(static fn(RegionId $id): string => $id->value, $ids));
        self::assertSame(RegionMatch::Point, $match);
    }

    public function testGeometryTakesPrecedenceAndCoversSeveralRegions(): void
    {
        // Rectangle over Vienna and Lower Austria, plus a point in Tyrol: the area decides.
        $area = Geometry::fromGeoJson('Polygon', [[[15.9, 47.9], [16.8, 47.9], [16.8, 48.5], [15.9, 48.5], [15.9, 47.9]]]);
        $common = new ItemCommon('w', 't', 'https://x.example/', position: Coordinate::fromLonLat(11.4, 47.27), geometry: $area);

        [$ids, $match] = $this->matcher(Scope::AT)->match($common, null);

        self::assertSame(RegionMatch::Geometry, $match);
        self::assertContains('AT-3', array_map(static fn(RegionId $id): string => $id->value, $ids));
        self::assertContains('AT-9', array_map(static fn(RegionId $id): string => $id->value, $ids));
        self::assertNotContains('AT-7', array_map(static fn(RegionId $id): string => $id->value, $ids));
    }

    public function testCountyPolygonIsNotAssignedToNeighbourState(): void
    {
        // Area entirely within Stormarn (Schleswig-Holstein), near Hamburg.
        $area = Geometry::fromGeoJson('Polygon', [[[10.25, 53.65], [10.45, 53.65], [10.45, 53.8], [10.25, 53.8], [10.25, 53.65]]]);

        [$ids] = $this->matcher(Scope::DE)->match(new ItemCommon('w', 't', 'https://x.example/', geometry: $area), null);

        self::assertSame(['DE-SH'], array_map(static fn(RegionId $id): string => $id->value, $ids));
    }

    public function testFallsBackToSourceThenAreaThenNone(): void
    {
        $matcher = $this->matcher(Scope::CH);

        [$ids, $match] = $matcher->match(new ItemCommon('a', 't', 'https://x.example/', regionIds: [RegionId::fromString('CH-ZH')]), null);
        self::assertSame([RegionMatch::Source, ['CH-ZH']], [$match, array_map(static fn(RegionId $id): string => $id->value, $ids)]);

        [$ids, $match] = $matcher->match(new ItemCommon('b', 't', 'https://x.example/'), 'Genève, Vaud');
        self::assertSame([RegionMatch::Area, ['CH-GE', 'CH-VD']], [$match, array_map(static fn(RegionId $id): string => $id->value, $ids)]);

        [$ids, $match] = $matcher->match(new ItemCommon('c', 't', 'https://x.example/', position: Coordinate::fromLonLat(9.19, 45.46)), 'Lombardia');
        self::assertSame([RegionMatch::None, []], [$match, $ids], 'Milan lies outside: no reliable place assignment');
    }

    public function testAreaNamesIgnoreCaseAndPrefixes(): void
    {
        $regions = Fixtures::generated()->regions(Scope::CH);
        $matcher = new AreaNameMatcher();

        self::assertSame(['CH-TI'], array_map(static fn(Region $r): string => $r->id->value, $matcher->match('Kanton TICINO', $regions)));
        self::assertSame([], $matcher->match('', $regions));
        self::assertSame([], $matcher->match('Bernina', $regions));
    }

    private function matcher(Scope $scope): RegionMatcher
    {
        $pip = new PointInPolygon();

        return new RegionMatcher(
            new RegionLocator(Fixtures::generated()->grid($scope), Fixtures::generated()->regions($scope), $pip),
            new GeometrySamples(new InteriorSamples(new ScanlineIntervals())),
            $pip,
            new AreaNameMatcher(),
        );
    }
}
