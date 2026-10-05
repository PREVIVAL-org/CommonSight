<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Imgw\Tests;

use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\Imgw\ImgwParser;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Station\WaterReading;
use CommonSight\Sdk\Text\NumberParser;
use CommonSight\Sdk\Text\UtcTimeParser;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** IMGW station list: times in UTC, a station with an impossible position is rejected alone. */
final class ImgwParserTest extends TestCase
{
    public function testAStationWithAnImpossiblePositionDoesNotFailTheOthers(): void
    {
        $station = ['id_stacji' => '151140030', 'stacja' => 'Przewoźniki', 'rzeka' => 'Skroda', 'lon' => '14.8217', 'lat' => '51.5253', 'stan_wody' => '226', 'stan_wody_data_pomiaru' => '2026-10-02 12:50:00', 'stan_alarmowy' => '340', 'stan_ostrzegawczy' => '300'];
        $broken = ['id_stacji' => '999'] + ['lat' => '523.1'] + $station;

        $result = (new ImgwParser(new JsonBody(), new NumberParser(), new UtcTimeParser()))->parse(Fixtures::json([$station, $broken], 'imgw'), new ParseContext(Scope::Border, UtcInstant::fromIso('2026-10-02T13:00:00Z')));

        self::assertSame(1, $result->statistics->valid);
        self::assertSame(['invalidPosition' => 1], $result->statistics->rejectedByReason);
        $reading = $result->records[0];
        self::assertInstanceOf(WaterReading::class, $reading);
        self::assertSame('2026-10-02T12:50:00Z', $reading->time?->toIso(), 'UTC without zone suffix');
    }
}
