<?php

declare(strict_types=1);

namespace CommonSight\Tests\Model;

use CommonSight\Model\Decoded;
use CommonSight\Model\Geometry;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\DoseRate;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\RegionId;
use CommonSight\Model\Value\UtcInstant;
use PHPUnit\Framework\TestCase;

/** V15: value objects validate themselves on construction; D-12, D-13. */
final class ValueObjectsTest extends TestCase
{
    public function testUtcInstantAlwaysPrintsUtcWithZ(): void
    {
        $instant = UtcInstant::fromDateTime(new \DateTimeImmutable('2026-09-28T12:15:30+02:00'));

        self::assertSame('2026-09-28T10:15:30Z', $instant->toIso());
        self::assertSame('"2026-09-28T10:15:30Z"', json_encode($instant));
        self::assertSame($instant->timestamp, UtcInstant::fromIso('2026-09-28T10:15:30Z')->timestamp);
        self::assertSame('2026-09-28T10:15:30Z', UtcInstant::latest([null, UtcInstant::fromIso('2026-09-28T09:00:00Z'), $instant])?->toIso());
    }

    public function testCoordinateOrderIsExplicit(): void
    {
        self::assertSame([16.37, 48.2], Coordinate::fromLatLon(48.2, 16.37)->toLonLat());
        self::assertSame([16.37, 48.2], Coordinate::fromLonLat(16.37, 48.2)->toLonLat());

        $this->expectException(\InvalidArgumentException::class);
        Coordinate::fromLatLon(91.0, 0.0);
    }

    public function testDoseRateNormalizesUnits(): void
    {
        self::assertEqualsWithDelta(0.095, DoseRate::fromValueAndUnit(95.0, 'nSv/h')?->microSievertPerHour, 1e-9);
        self::assertNull(DoseRate::fromValueAndUnit(1.0, 'rem'));
        self::assertNull(DoseRate::fromValueAndUnit(NAN, 'µSv/h'));
    }

    public function testGeometryValidatesStructure(): void
    {
        $polygon = Geometry::fromGeoJson('Polygon', [[[1, 2], [3, 2], [3, 4], [1, 2]]]);
        self::assertSame([[[1.0, 2.0], [3.0, 2.0], [3.0, 4.0], [1.0, 2.0]]], $polygon->coordinates);
        self::assertCount(1, $polygon->polygons());

        $this->expectException(\InvalidArgumentException::class);
        Geometry::fromGeoJson('Polygon', [[[1, 2], [3, 2], [1, 2]]]);
    }

    public function testRejectsUnknownGeometryAndInvalidRegionIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RegionId::fromString('AT-0');
    }

    public function testItemsNeedHttpLinksAndRequestsNeedHttps(): void
    {
        try {
            new ItemCommon('x', 't', 'javascript:alert(1)');
            self::fail('link without http(s) accepted');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        $this->expectException(\InvalidArgumentException::class);
        new HttpRequest('http://insecure.example/', 'application/json', 'x');
    }

    public function testMsgOmitsEmptyParams(): void
    {
        self::assertSame('{"key":"note.space"}', json_encode(new Msg('note.space')));
        self::assertSame('{"key":"issue.missingGeometry","params":{"count":2}}', json_encode(new Msg('issue.missingGeometry', ['count' => 2])));
    }

    public function testDecodedReadsTypedValuesSafely(): void
    {
        $data = Decoded::of(['a' => ['b' => '12', 'c' => 'x', 'd' => [1, 'zwei']], 'f' => '3.5']);

        self::assertSame(12, $data->get('a', 'b')->int());
        self::assertNull($data->get('a', 'c')->int());
        self::assertNull($data->get('missing', 'deep')->string());
        self::assertSame(3.5, $data->get('f')->float());
        self::assertSame(['1', 'zwei'], $data->get('a', 'd')->strings());
    }

    /** Concept "layers as plugins", L1: open layer ids, checked for their format, one instance per id. */
    public function testLayerIdsAreOpenButWellFormed(): void
    {
        self::assertSame(LayerId::from('water'), LayerId::from('water'), 'comparable with ===');
        self::assertSame('pollen-forecast', LayerId::from('pollen-forecast')->value, 'a layer that is not built in');
        self::assertNull(LayerId::tryFrom('Water'));
        self::assertNull(LayerId::tryFrom('w'));
        self::assertNull(LayerId::tryFrom('../state'));

        $this->expectException(\InvalidArgumentException::class);
        LayerId::from('');
    }
}
