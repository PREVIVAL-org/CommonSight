<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Item\SectionHeading;
use CommonSight\Model\Item\WarningSection;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\GeoSphere\Record\WarnFeature;
use CommonSight\Plugin\GeoSphere\Record\WarningDetail;
use CommonSight\Sdk\Geo\AustriaLambertGeometry;
use CommonSight\Sdk\Geo\InteriorPoint;
use CommonSight\Sdk\Geo\ProjectionOutOfRange;
use CommonSight\Sdk\Source\DetailSource;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Source\UnreadableResponse;

/** Describes the query for the German detail text of a GeoSphere warning at a point inside its area (Q-W-AT-07 to -10). */
final class GeoSphereDetailSource implements DetailSource
{
    public function __construct(
        private readonly AustriaLambertGeometry $geometry,
        private readonly InteriorPoint $interiorPoint,
        private readonly GeoSphereDetailParser $parser,
        private readonly GeoSphereDetailMatcher $matcher,
        private readonly GeoSphereDetailComposer $composer,
    ) {}

    public function cacheName(): string
    {
        return 'geosphere-details';
    }

    public function cacheKey(object $record): string
    {
        $record = RecordType::expect($record, WarnFeature::class);

        return sprintf('%s:%d:%d:%d:%d', $record->warnid, $record->wtype, $record->wlevel, $record->start, $record->end);
    }

    public function request(object $record): ?HttpRequest
    {
        $record = RecordType::expect($record, WarnFeature::class);
        if ($record->geometryType === null || $record->coordinates === null) {
            return null;
        }
        try {
            $point = $this->interiorPoint->of($this->geometry->toWgs84($record->geometryType, $record->coordinates)->polygons());
        } catch (\InvalidArgumentException|ProjectionOutOfRange) {
            return null; // Without an evaluable area there is no query point; the warning stays without detail text.
        }
        if ($point === null) {
            return null;
        }

        return HttpRequest::withQuery(
            GeoSphereStatusRequest::API . 'getWarningsForCoords',
            ['lon' => round($point[0], 6), 'lat' => round($point[1], 6), 'lang' => 'de'],
            'application/json',
            GeoSphereStatusRequest::SOURCE_ID,
            $record->warnid,
        );
    }

    public function extract(object $record, HttpResponse $response): ?array
    {
        $record = RecordType::expect($record, WarnFeature::class);
        try {
            $match = $this->matcher->match($record, $this->parser->parse($response));
        } catch (UnreadableResponse) {
            return null; // Detail missing; new attempt in the next run.
        }
        $detail = $match === null ? null : $this->composer->compose($match);
        if ($detail === null) {
            return null;
        }

        return [
            'sections' => array_map(static fn(WarningSection $s): array => $s->jsonSerialize(), $detail->sections),
            'create' => $detail->create?->timestamp,
        ];
    }

    public function apply(object $record, array $detail): object
    {
        $record = RecordType::expect($record, WarnFeature::class);
        $data = Decoded::of($detail);
        $sections = [];
        foreach ($data->get('sections')->list() as $section) {
            $heading = SectionHeading::tryFrom($section->get('heading')->string() ?? '');
            $text = $section->get('text')->string() ?? '';
            if ($heading !== null && $text !== '') {
                $sections[] = new WarningSection($heading, $text);
            }
        }
        $create = $data->get('create')->int();

        return $sections === [] ? $record : $record->withDetail(new WarningDetail($sections, $create === null ? null : UtcInstant::fromTimestamp($create)));
    }

    public function expiresAt(object $record): UtcInstant
    {
        return UtcInstant::fromTimestamp(RecordType::expect($record, WarnFeature::class)->end);
    }
}
