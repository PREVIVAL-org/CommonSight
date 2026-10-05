<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere\Tests;

use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\GeoSphere\GeoSphereStatusParser;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** Q-W-AT-02: parser edge cases of GeoSphere Austria. */
final class GeoSphereStatusParserTest extends TestCase
{
    private ParseContext $context;

    protected function setUp(): void
    {
        $this->context = new ParseContext(Scope::AT, UtcInstant::fromIso('2026-09-28T12:00:00Z'));
    }

    /** Q-W-AT-02: only invalid, no valid records -> fetch failed. */
    public function testGeoSphereWithOnlyInvalidRecordsFails(): void
    {
        $this->expectException(UnreadableResponse::class);

        (new GeoSphereStatusParser(new JsonBody()))->parse(Fixtures::json(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['warnid' => 'w1', 'wtype' => 9, 'wlevel' => 1, 'start' => 1790586000, 'end' => 1790704800]],
        ]]), $this->context);
    }

    public function testGeoSphereCountsSkippedAndRejected(): void
    {
        $result = (new GeoSphereStatusParser(new JsonBody()))->parse(Fixtures::response('geosphere-warnings', 'at-cases.json'), $this->context);

        self::assertSame(3, $result->statistics->valid);
        self::assertSame(2, $result->statistics->skipped, 'expired and level 0');
        self::assertSame(['unknownType' => 1, 'missingId' => 1], $result->statistics->rejectedByReason);
        self::assertSame(['90001', '31001'], $result->records[0]->gemeinden, 'only five-digit codes');
    }
}
