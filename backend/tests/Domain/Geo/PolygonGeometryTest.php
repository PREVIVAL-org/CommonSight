<?php

declare(strict_types=1);

namespace CommonSight\Tests\Domain\Geo;

use CommonSight\Domain\Geo\GridFrame;
use CommonSight\Domain\Geo\GridTraversal;
use CommonSight\Domain\Geo\PointInPolygon;
use CommonSight\Sdk\Geo\InteriorPoint;
use CommonSight\Sdk\Geo\ScanlineIntervals;
use PHPUnit\Framework\TestCase;

/** U-14, Q-W-AT-07: point in polygon with holes, interior point via scan lines, grid traversal. */
final class PolygonGeometryTest extends TestCase
{
    /** Square 0..10 with hole 4..6. */
    private const DONUT = [
        [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]],
        [[4.0, 4.0], [6.0, 4.0], [6.0, 6.0], [4.0, 6.0], [4.0, 4.0]],
    ];

    public function testPointInPolygonHonoursHoles(): void
    {
        $pip = new PointInPolygon();

        self::assertTrue($pip->inPolygon(self::DONUT, 2.0, 2.0));
        self::assertFalse($pip->inPolygon(self::DONUT, 5.0, 5.0), 'in the hole');
        self::assertFalse($pip->inPolygon(self::DONUT, 11.0, 5.0));
        self::assertTrue($pip->inAny([[[[20.0, 20.0], [21.0, 20.0], [21.0, 21.0], [20.0, 20.0]]], self::DONUT], 8.0, 8.0));
    }

    public function testInteriorPointAvoidsHoleAndLiesInside(): void
    {
        $point = (new InteriorPoint(new ScanlineIntervals()))->of([self::DONUT]);

        self::assertNotNull($point);
        self::assertTrue((new PointInPolygon())->inPolygon(self::DONUT, $point[0], $point[1]));
    }

    public function testInteriorPointOfConcaveShapeLiesInside(): void
    {
        // U shape: the center of the bounding box lies outside.
        $u = [[[0.0, 0.0], [9.0, 0.0], [9.0, 9.0], [6.0, 9.0], [6.0, 3.0], [3.0, 3.0], [3.0, 9.0], [0.0, 9.0], [0.0, 0.0]]];
        $point = (new InteriorPoint(new ScanlineIntervals()))->of([$u]);

        self::assertNotNull($point);
        self::assertTrue((new PointInPolygon())->inPolygon($u, $point[0], $point[1]));
    }

    public function testGridTraversalVisitsEveryCrossedCell(): void
    {
        $frame = new GridFrame(0.0, 0.0, 1.0, 10, 10);
        $cells = (new GridTraversal())->cells($frame, [0.5, 0.5], [3.5, 1.5]);

        self::assertSame([0, 1, 11, 12, 13], $cells);
        self::assertSame([55], (new GridTraversal())->cells($frame, [5.2, 5.2], [5.8, 5.9]));
    }
}
