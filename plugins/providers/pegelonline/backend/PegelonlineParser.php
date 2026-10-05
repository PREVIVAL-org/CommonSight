<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Pegelonline;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\Pegelonline\Record\Station;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;

/** Converts the PEGELONLINE station list into Stations and takes over only the time series W per station (Q-WA-DE-01, -02). */
final class PegelonlineParser implements SourceParser
{
    public function __construct(private readonly JsonBody $json) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        if (!$data->isList()) {
            throw new UnreadableResponse('PEGELONLINE response is not a station list');
        }
        $counter = new ParseCounter();
        $records = [];
        foreach ($data->list() as $station) {
            $series = $this->waterLevelSeries($station);
            if ($series === null) {
                $counter->skipped();
                continue;
            }
            $record = $this->record($station, $series, $counter);
            if ($record !== null) {
                $counter->valid();
                $records[] = $record;
            }
        }

        return new ParseResult($records, $counter->statistics());
    }

    private function waterLevelSeries(Decoded $station): ?Decoded
    {
        foreach ($station->get('timeseries')->list() as $series) {
            if ($series->get('shortname')->string() === 'W') {
                return $series;
            }
        }

        return null;
    }

    private function record(Decoded $station, Decoded $series, ParseCounter $counter): ?Station
    {
        $measurement = $series->get('currentMeasurement');
        $uuid = $station->get('uuid')->string();
        $value = $measurement->get('value')->float();
        $timestamp = $measurement->get('timestamp')->string();
        $lat = $station->get('latitude')->float();
        $lon = $station->get('longitude')->float();
        if ($lat === null || $lon === null) {
            // About 6 % of the stations have no coordinates (as of 2026-09-28): not a format error,
            // but not taken over without a location (Q-WA-DE-01).
            $counter->skipped();

            return null;
        }
        if ($uuid === null || $value === null || $timestamp === null) {
            $counter->rejected($uuid === null ? 'missingId' : 'missingValue');

            return null;
        }
        $zero = $series->get('gaugeZero');

        return new Station(
            $uuid,
            $station->get('number')->text() ?? '',
            $station->get('longname')->string() ?? '',
            $station->get('water', 'longname')->string() ?? '',
            $lat,
            $lon,
            $series->get('unit')->string() ?? 'cm',
            $value,
            $timestamp,
            $measurement->get('stateMnwMhw')->string(),
            $measurement->get('stateNswHsw')->string(),
            $zero->get('value')->float(),
            $zero->get('unit')->string(),
        );
    }
}
