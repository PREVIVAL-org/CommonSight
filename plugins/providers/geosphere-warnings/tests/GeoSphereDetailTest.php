<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere\Tests;

use CommonSight\Plugin\GeoSphere\GeoSphereDetailComposer;
use CommonSight\Plugin\GeoSphere\GeoSphereDetailMatcher;
use CommonSight\Plugin\GeoSphere\GeoSphereDetailParser;
use CommonSight\Plugin\GeoSphere\GeoSphereDetailSource;
use CommonSight\Plugin\GeoSphere\Record\WarnFeature;
use CommonSight\Sdk\Geo\AustriaLambertGeometry;
use CommonSight\Sdk\Geo\AustriaLambertProjection;
use CommonSight\Sdk\Geo\InteriorPoint;
use CommonSight\Sdk\Geo\ScanlineIntervals;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\WarningSections;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** Q-W-AT-07 to -10: exact detail text, interior point, cache key. */
final class GeoSphereDetailTest extends TestCase
{
    public function testTakesOnlyTheExactlyMatchingDetail(): void
    {
        $source = $this->source();
        $warning = $this->warning();

        $detail = $source->extract($warning, Fixtures::response('geosphere-warnings', 'at-detail.json'));

        self::assertNotNull($detail);
        $enriched = $source->apply($warning, $detail);
        self::assertInstanceOf(WarnFeature::class, $enriched);
        self::assertSame('Gewitter mit Starkregen, Hagel und Sturmböen.', $enriched->detail?->sections[0]->text);
        self::assertSame(['description', 'situation', 'impact', 'advice'], array_map(static fn($s): string => $s->heading->value, $enriched->detail->sections));
    }

    public function testNoDetailWhenOnlyNeighbourWarningsMatch(): void
    {
        $other = new WarnFeature('w12345c1v9', 5, 2, 1790586000, 1790625600, ['90001'], null, null);

        self::assertNull($this->source()->extract($other, Fixtures::response('geosphere-warnings', 'at-detail.json')));
    }

    public function testRequestsAtInteriorPointAndKeysByWarningAndPeriod(): void
    {
        $source = $this->source();
        $request = $source->request($this->warning());

        self::assertNotNull($request);
        self::assertMatchesRegularExpression('/getWarningsForCoords\?lon=16\.\d+&lat=48\.\d+&lang=de$/', $request->url);
        self::assertSame('w12345c1v2:5:2:1790586000:1790625600', $source->cacheKey($this->warning()));
        self::assertNull($source->request(new WarnFeature('w1', 1, 1, 1, 2, [], null, null)), 'no interior point without area');
    }

    private function warning(): WarnFeature
    {
        return new WarnFeature('w12345c1v2', 5, 2, 1790586000, 1790625600, ['90001'], 'Polygon', [[[615000, 475000], [640000, 475000], [640000, 492000], [615000, 492000], [615000, 475000]]]);
    }

    private function source(): GeoSphereDetailSource
    {
        return new GeoSphereDetailSource(
            new AustriaLambertGeometry(new AustriaLambertProjection()),
            new InteriorPoint(new ScanlineIntervals()),
            new GeoSphereDetailParser(new JsonBody(), new TextCleaner()),
            new GeoSphereDetailMatcher(),
            new GeoSphereDetailComposer(new WarningSections(), new UtcTimeParser()),
        );
    }
}
