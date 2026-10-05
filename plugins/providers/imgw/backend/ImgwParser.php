<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Imgw;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Station\FloodStages;
use CommonSight\Sdk\Station\WaterReading;
use CommonSight\Sdk\Text\NumberParser;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Converts the IMGW-PIB station list into WaterReadings in cm above the gauge zero, with warning and alarm level of
 * the station. Numbers come as text; the measuring time is UTC (newest values at most 20 minutes old, checked
 * 2026-10-02). Stations beyond the border zone are removed later in the layer.
 */
final class ImgwParser implements SourceParser
{
    public function __construct(
        private readonly JsonBody $json,
        private readonly NumberParser $numbers,
        private readonly UtcTimeParser $time,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        if (!$data->isList()) {
            throw new UnreadableResponse('IMGW response is not a station list');
        }
        $counter = new ParseCounter();
        $records = [];
        foreach ($data->list() as $station) {
            $record = $this->record($station, $counter);
            if ($record !== null) {
                $counter->valid();
                $records[] = $record;
            }
        }

        return new ParseResult($records, $counter->statistics());
    }

    private function record(Decoded $station, ParseCounter $counter): ?WaterReading
    {
        $id = $station->get('id_stacji')->text();
        $level = $this->numbers->parse($station->get('stan_wody')->raw());
        $time = $this->time->parseUtc($station->get('stan_wody_data_pomiaru')->string());
        $lat = $this->numbers->parse($station->get('lat')->raw());
        $lon = $this->numbers->parse($station->get('lon')->raw());
        if ($id === null || $lat === null || $lon === null) {
            $counter->rejected($id === null ? 'missingId' : 'missingPosition');

            return null;
        }
        $position = Coordinate::tryFromLatLon($lat, $lon);
        if ($position === null) {
            $counter->rejected('invalidPosition');

            return null;
        }
        if ($level === null || $time === null) {
            // Stations out of operation deliver no current water level: not a format error.
            $counter->skipped();

            return null;
        }

        return new WaterReading(
            'imgw:' . $id,
            implode(' · ', array_filter([$station->get('stacja')->text(), $station->get('rzeka')->text()])),
            'https://hydro.imgw.pl/#/station/hydro/' . rawurlencode($id),
            'PL',
            $position,
            $time,
            $level,
            'cm',
            'reference.gaugeZero',
            new FloodStages([$this->numbers->parse($station->get('stan_ostrzegawczy')->raw()), $this->numbers->parse($station->get('stan_alarmowy')->raw())], $level, 'cm', 'Warn-/Alarmstufe', 'IMGW-PIB'),
        );
    }
}
