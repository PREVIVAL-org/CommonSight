<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Umweltbundesamt;

use CommonSight\Model\Http\HttpResponse;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Station\DoseRateReading;
use CommonSight\Sdk\Station\StationDirectory;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Converts the current values of the Austrian radiation early warning system into DoseRateReadings: one time for all
 * probes (Unix seconds, hourly value), values in nSv/h converted to µSv/h like the other networks. The response has
 * only screen positions; the position comes from the master data (data/stations.json of this plugin).
 */
final class UmweltbundesamtParser implements SourceParser
{
    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly StationDirectory $stations,
        private readonly string $infoUrl,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        if (!$data->get('data')->isList()) {
            throw new UnreadableResponse('Radiation early warning system: values missing');
        }
        $time = $this->time->parseUtc($data->get('time')->raw());
        $counter = new ParseCounter();
        $records = [];
        foreach ($data->get('data')->list() as $probe) {
            $number = $probe->get('nummer')->string();
            $value = $probe->get('messwert')->float();
            $station = $number === null ? null : $this->stations->find($number);
            if ($number === null || $station === null || $value === null || $value < 0) {
                $counter->rejected(match (true) {
                    $number === null => 'missingId',
                    $station === null => 'missingPosition',
                    default => 'invalidValue',
                });
                continue;
            }
            $counter->valid();
            $records[] = new DoseRateReading('odl:' . $number, $probe->get('name')->string() ?? $station->name, $this->infoUrl, null, $station->position, $time, round($value / 1000, 3), 'µSv/h', '1h');
        }

        return new ParseResult($records, $counter->statistics(), $time);
    }
}
