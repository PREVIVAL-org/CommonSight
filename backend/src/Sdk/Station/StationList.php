<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Station;

use CommonSight\Model\Decoded;
use CommonSight\Model\Place\Station;
use CommonSight\Model\Value\Coordinate;

/**
 * Reads the master data of measuring stations from JSON (a plugin's data/stations.json, ADR 0038): id, name, lat, lon,
 * optionally river, stageOf and stages.
 */
final class StationList
{
    /** @return list<Station> */
    public function read(Decoded $list): array
    {
        return array_map(static fn(Decoded $s): Station => new Station(
            (string) $s->get('id')->text(),
            (string) $s->get('name')->string(),
            Coordinate::fromLatLon((float) $s->get('lat')->float(), (float) $s->get('lon')->float()),
            $s->get('river')->string(),
            $s->get('stageOf')->string(),
            array_map(static fn(Decoded $v): float => (float) $v->float(), $s->get('stages')->list()),
        ), $list->list());
    }
}
