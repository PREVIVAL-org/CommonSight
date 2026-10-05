<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk;

use CommonSight\Sdk\Geo\AustriaLambertGeometry;
use CommonSight\Sdk\Geo\AustriaLambertProjection;
use CommonSight\Sdk\Geo\ProjectionOutOfRange;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** Q-W-AT-04: projection from MGI / Austria Lambert with reference points. */
final class AustriaLambertTest extends TestCase
{
    public function testProjectionMatchesReferencePointsWithinHalfAMetre(): void
    {
        $projection = new AustriaLambertProjection();
        foreach (Fixtures::contractJsonFromBackend('tests/Fixtures/projection/epsg31287-reference.json')['cases'] as $case) {
            $point = $projection->toWgs84((float) $case['easting'], (float) $case['northing']);
            $dx = ($point->lon - $case['lon']) * 111_320 * cos(deg2rad($case['lat']));
            $dy = ($point->lat - $case['lat']) * 110_540;
            self::assertLessThan(0.5, hypot($dx, $dy), $case['name']);
        }
    }

    public function testProjectionRejectsPointsOutsideAustria(): void
    {
        $this->expectException(ProjectionOutOfRange::class);

        (new AustriaLambertProjection())->toWgs84(1_500_000.0, 400_000.0);
    }

    public function testAnAreaOfOnlyAnOpenRingIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new AustriaLambertGeometry(new AustriaLambertProjection()))->toWgs84('Polygon', [[[615000, 475000], [640000, 475000], [640000, 492000], [615000, 492000]]]);
    }

    /** One bad ring of a multipolygon does not cost the warning its whole area: only that polygon or hole goes. */
    public function testBadRingsAreDiscardedAndTheRestStays(): void
    {
        $square = [[615000, 475000], [640000, 475000], [640000, 492000], [615000, 492000], [615000, 475000]];
        $degenerate = [[620000, 480000], [621000, 480000], [620000, 480000], [621000, 480000]];
        $area = (new AustriaLambertGeometry(new AustriaLambertProjection()))->toWgs84('MultiPolygon', [[$square, $degenerate], [$degenerate], [$square]]);

        $polygons = $area->polygons();
        self::assertCount(2, $polygons, 'the polygon with the degenerate outer ring is gone');
        self::assertCount(1, $polygons[0], 'the degenerate hole is gone');
    }
}
