<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Usgs;

use CommonSight\Model\Item\EarthquakeItem;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Plugin\Usgs\Record\Quake;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Text\SafeUrl;

/** Maps a USGS earthquake to an item with magnitude, depth and place description (Q-NA-02). */
final class UsgsMapper implements ItemMapper
{
    public function __construct(private readonly SafeUrl $url, private readonly GermanPlace $place) {}

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, Quake::class);
        // USGS writes English only ("M 3.5 - 0 km NNW of Baumkirchen, Austria"): title and place in German from the place.
        $place = $this->place->of($record->place);

        return new EarthquakeItem(
            new ItemCommon(
                id: 'usgs:' . $record->id,
                title: $place !== '' ? 'Erdbeben · ' . $place : 'Erdbeben',
                url: $this->url->orFallback($record->url, 'https://earthquake.usgs.gov/'),
                time: $record->time,
                position: Coordinate::fromLonLat($record->lon, $record->lat),
            ),
            $record->mag,
            $record->depth,
            $place,
        );
    }
}
