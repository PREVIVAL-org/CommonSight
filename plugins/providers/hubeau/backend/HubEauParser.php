<?php

declare(strict_types=1);

namespace CommonSight\Plugin\HubEau;

use CommonSight\Model\Http\HttpResponse;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Station\FloodStages;
use CommonSight\Sdk\Station\NewestPerStation;
use CommonSight\Sdk\Station\StationDirectory;
use CommonSight\Sdk\Station\WaterReading;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Converts the real-time observations of Hub'Eau into WaterReadings: the newest value per station of the border zone,
 * water level from mm to cm above the gauge zero. Stations not in the master data (outside the zone, not in service)
 * are skipped. A band whose response holds fewer rows than it counts was cut off at the page size and fails as a
 * whole, so that the gap is visible.
 */
final class HubEauParser implements SourceParser
{
    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly StationDirectory $stations,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        if (!$data->get('data')->isList()) {
            throw new UnreadableResponse("Hub'Eau response without observations");
        }
        $rows = $data->get('data')->list();
        if (($data->get('count')->int() ?? 0) > count($rows)) {
            throw new UnreadableResponse(sprintf("Hub'Eau response cut off: %d of %d observations", count($rows), $data->get('count')->int()));
        }
        $counter = new ParseCounter();
        /** @var NewestPerStation<float> $newest */
        $newest = new NewestPerStation($counter);
        foreach ($rows as $row) {
            $code = $row->get('code_station')->string();
            $value = $row->get('resultat_obs')->float();
            $time = $this->time->parseUtc($row->get('date_obs')->string());
            if ($code === null || $value === null || $time === null) {
                $counter->rejected($code === null ? 'missingId' : 'missingValue');
                continue;
            }
            if ($this->stations->find($code) === null) {
                $counter->skipped();
                continue;
            }
            $newest->offer($code, $time, $value);
        }

        return new ParseResult($this->readings($newest, $counter), $counter->statistics());
    }

    /**
     * @param NewestPerStation<float> $newest
     * @return list<WaterReading>
     */
    private function readings(NewestPerStation $newest, ParseCounter $counter): array
    {
        $readings = [];
        foreach ($newest->all() as $code => [$time, $millimetres]) {
            $station = $this->stations->find($code);
            if ($station === null) {
                continue;
            }
            $counter->valid();
            $readings[] = new WaterReading(
                'hubeau:' . $code,
                $station->name,
                'https://www.hydro.eaufrance.fr/stationhydro/' . rawurlencode($code) . '/fiche',
                'FR',
                $station->position,
                $time,
                round($millimetres / 10, 1),
                'cm',
                'reference.gaugeZero',
                FloodStages::none("Hub'Eau"),
            );
        }

        return $readings;
    }
}
