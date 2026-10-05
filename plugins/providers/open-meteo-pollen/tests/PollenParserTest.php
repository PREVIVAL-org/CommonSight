<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoPollen\Tests;

use CommonSight\Model\Place\City;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\OpenMeteoPollen\PollenParser;
use CommonSight\Plugin\OpenMeteoPollen\Record\PollenValues;
use CommonSight\Sdk\Place\CityDirectory;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Text\UtcTimeParser;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** The batch response is assigned to the places in the order of the query; empty and broken places are told apart. */
final class PollenParserTest extends TestCase
{
    public function testAssignsThePlacesInTheOrderOfTheQuery(): void
    {
        $result = $this->parse([
            ['current' => ['time' => '2026-10-03T19:00', 'grass_pollen' => 0.4, 'mugwort_pollen' => 5.5, 'olive_pollen' => -1.0]],
            ['current' => ['time' => '2026-10-03T19:00', 'birch_pollen' => 0.0]],
        ]);

        self::assertSame(2, $result->statistics->valid);
        [$vienna, $graz] = $result->records;
        self::assertInstanceOf(PollenValues::class, $vienna);
        self::assertInstanceOf(PollenValues::class, $graz);
        self::assertSame('at-wien', $vienna->city->id);
        self::assertSame(['grass' => 0.4, 'mugwort' => 5.5], $vienna->concentrations, 'negative values dropped');
        self::assertSame('2026-10-03T19:00:00Z', $vienna->time?->toIso());
        self::assertSame(['birch' => 0.0], $graz->concentrations);
    }

    /** Outside the season the model has no values: skipped, not an error; a place missing from the response is one. */
    public function testEmptyPlacesAreSkippedMissingOnesRejected(): void
    {
        $result = $this->parse([
            ['current' => ['time' => '2026-01-10T12:00', 'alder_pollen' => null, 'birch_pollen' => null, 'grass_pollen' => null]],
        ]);

        self::assertSame([], $result->records);
        self::assertSame(1, $result->statistics->skipped);
        self::assertSame(1, $result->statistics->rejected, 'Graz is missing from the response');
        self::assertSame(['missingPlace' => 1], $result->statistics->rejectedByReason);
    }

    /** Renamed fields are a format change, not an empty season: rejected, so the layer reports it. */
    public function testRenamedFieldsAreRejected(): void
    {
        $block = ['current' => ['time' => '2026-10-03T19:00', 'grass_pollen_grains' => 3.0]];
        $result = $this->parse([$block, $block]);

        self::assertSame([], $result->records);
        self::assertSame(['missingField' => 2], $result->statistics->rejectedByReason);
    }

    /** @param list<array<string, mixed>> $locations */
    private function parse(array $locations): \CommonSight\Sdk\Source\ParseResult
    {
        $cities = new CityDirectory([
            new City('at-wien', Scope::AT, 'Wien', Coordinate::fromLonLat(16.3738, 48.2082), null),
            new City('at-graz', Scope::AT, 'Graz', Coordinate::fromLonLat(15.4395, 47.0707), null),
        ]);

        return (new PollenParser(new JsonBody(), new UtcTimeParser(), $cities))->parse(Fixtures::json($locations, 'open-meteo-pollen'), new ParseContext(Scope::AT, UtcInstant::fromIso('2026-10-03T19:30:00Z')));
    }
}
