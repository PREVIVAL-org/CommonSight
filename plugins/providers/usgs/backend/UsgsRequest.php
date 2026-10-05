<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Usgs;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Place\BoundingBox;
use CommonSight\Model\Place\Country;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\SourceRequest;

/**
 * Names the USGS query for the earthquakes of the last 7 days in the country's rectangle plus a 0.5° margin (Q-NA-01);
 * for the border zone the rectangle of DACH plus 4° (about 300 km, ADR 0038).
 */
final class UsgsRequest implements SourceRequest
{
    public const SOURCE_ID = 'usgs';
    private const MARGIN_DEG = 0.5;
    private const BORDER_MARGIN_DEG = 4.0;
    private const DAYS = 7;

    /** @param array<string, Country> $countries */
    public function __construct(private readonly array $countries) {}

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        $bounds = $scope === Scope::Border
            ? $this->dachBounds()->expandedBy(self::BORDER_MARGIN_DEG)
            : $this->countries[$scope->value]->bounds->expandedBy(self::MARGIN_DEG);

        return [HttpRequest::withQuery('https://earthquake.usgs.gov/fdsnws/event/1/query', [
            'format' => 'geojson',
            'starttime' => $now->plusSeconds(-self::DAYS * 86400)->toIso(),
            'minlatitude' => $bounds->south,
            'maxlatitude' => $bounds->north,
            'minlongitude' => $bounds->west,
            'maxlongitude' => $bounds->east,
            'orderby' => 'time',
            'limit' => 150,
        ], 'application/geo+json, application/json', self::SOURCE_ID)];
    }

    /** Rectangle around all countries; without countries (only in tests) an empty rectangle at 0/0. */
    private function dachBounds(): BoundingBox
    {
        return array_reduce(
            array_values($this->countries),
            static fn(?BoundingBox $all, Country $c): BoundingBox => $all === null ? $c->bounds : new BoundingBox(
                min($all->west, $c->bounds->west),
                min($all->south, $c->bounds->south),
                max($all->east, $c->bounds->east),
                max($all->north, $c->bounds->north),
            ),
        ) ?? new BoundingBox(0.0, 0.0, 0.0, 0.0);
    }
}
