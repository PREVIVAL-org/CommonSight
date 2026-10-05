<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Mowas;

use CommonSight\Model\Decoded;
use CommonSight\Model\Geometry;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\Mowas\Record\MowasWarning;
use CommonSight\Sdk\Geo\GeometryRounding;
use CommonSight\Sdk\Source\DetailSource;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Source\UnreadableResponse;

/** Loads the warning area of a MoWaS message; only new or changed messages (key ID and version, Q-W-DE-05). */
final class MowasGeometryDetail implements DetailSource
{
    public function __construct(private readonly JsonBody $json, private readonly GeometryRounding $rounding) {}

    public function cacheName(): string
    {
        return 'mowas-geometry';
    }

    public function cacheKey(object $record): string
    {
        $record = RecordType::expect($record, MowasWarning::class);

        return $record->id . '#' . $record->version;
    }

    public function request(object $record): HttpRequest
    {
        $record = RecordType::expect($record, MowasWarning::class);

        return new HttpRequest('https://warnung.bund.de/api31/warnings/' . rawurlencode($record->id) . '.geojson', 'application/geo+json, application/json', MowasRequest::SOURCE_ID, $record->id);
    }

    public function extract(object $record, HttpResponse $response): ?array
    {
        try {
            $polygons = $this->polygons($this->json->features($this->json->decode($response)));
        } catch (UnreadableResponse) {
            return null; // The message stays without an area, query in the next run.
        }
        if ($polygons === []) {
            return null;
        }
        $geometry = count($polygons) === 1 ? Geometry::fromGeoJson('Polygon', $polygons[0]) : Geometry::fromGeoJson('MultiPolygon', $polygons);

        return $this->rounding->round($geometry)->jsonSerialize();
    }

    public function apply(object $record, array $detail): object
    {
        $record = RecordType::expect($record, MowasWarning::class);
        $data = Decoded::of($detail);

        return $record->withGeometry(Geometry::fromGeoJson((string) $data->get('type')->string(), $data->get('coordinates')->array()));
    }

    public function expiresAt(object $record): ?UtcInstant
    {
        return RecordType::expect($record, MowasWarning::class)->expiresDate;
    }

    /**
     * @param list<Decoded> $features
     * @return list<array<mixed>> valid polygon coordinates
     */
    private function polygons(array $features): array
    {
        $polygons = [];
        foreach ($features as $feature) {
            $geometry = $feature->get('geometry');
            $coordinates = $geometry->get('coordinates');
            $parts = match ($geometry->get('type')->string()) {
                'Polygon' => [$coordinates->array()],
                'MultiPolygon' => array_map(static fn(Decoded $p): array => $p->array(), $coordinates->list()),
                default => [],
            };
            array_push($polygons, ...array_values(array_filter($parts, $this->isValidPolygon(...))));
        }

        return $polygons;
    }

    /** @param array<mixed> $polygon */
    private function isValidPolygon(array $polygon): bool
    {
        try {
            Geometry::fromGeoJson('Polygon', $polygon);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
