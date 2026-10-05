<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Chmi;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Place\Station;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Station\FloodStages;
use CommonSight\Sdk\Station\StationDirectory;
use CommonSight\Sdk\Station\WaterReading;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Converts the file of one ČHMÚ gauge into a WaterReading: the newest water level H (cm) with its time, the discharge
 * Q of the same time as additional value, and the SPA stages of the master data for H or Q.
 */
final class ChmiParser implements SourceParser
{
    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly StationDirectory $stations,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $station = $this->stations->find($response->request->key);
        if ($station === null) {
            throw new UnreadableResponse('ČHMÚ-Station unbekannt: ' . $response->request->key);
        }
        $series = [];
        foreach ($this->json->decode($response)->get('objList', 0, 'tsList')->list() as $list) {
            $series[(string) $list->get('tsConID')->string()] = $this->newest($list->get('tsData'));
        }
        $counter = new ParseCounter();
        [$time, $level] = $series['H'] ?? [null, null];
        if ($time === null || $level === null) {
            // A gauge without a current water level is out of operation: not a format error.
            $counter->skipped();

            return new ParseResult([], $counter->statistics());
        }
        $counter->valid();
        [$dischargeTime, $discharge] = $series['Q'] ?? [null, null];
        $discharge = $dischargeTime?->timestamp === $time->timestamp ? $discharge : null;

        return new ParseResult([$this->reading($station, $time, $level, $discharge)], $counter->statistics());
    }

    private function reading(Station $station, UtcInstant $time, float $level, ?float $discharge): WaterReading
    {
        $compared = $station->stageOf === 'Q' ? $discharge : $level;

        return new WaterReading(
            'chmi:' . $station->id,
            implode(' · ', array_filter([$station->name, $station->river])),
            'https://hydro.chmi.cz/hppsoldv/hpps_act_quick.php',
            'CZ',
            $station->position,
            $time,
            $level,
            'cm',
            'reference.gaugeZero',
            new FloodStages($station->stages, $compared, $station->stageOf === 'Q' ? 'm³/s' : 'cm', 'SPA', 'ČHMÚ'),
            $discharge,
        );
    }

    /** @return array{UtcInstant|null, float|null} the newest value of a series, measured values only (no forecast H_F, Q_F) */
    private function newest(Decoded $data): array
    {
        $values = array_reverse($data->list());
        foreach ($values as $point) {
            $value = $point->get('value')->float();
            $time = $this->time->parseUtc($point->get('dt')->string());
            if ($value !== null && $time !== null) {
                return [$time, $value];
            }
        }

        return [null, null];
    }
}
