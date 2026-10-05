<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Paa;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Station\DoseRateReading;
use CommonSight\Sdk\Text\NumberParser;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Converts the PAA map layer into DoseRateReadings. The value comes as text with unit ("0.073 µSv/h"); the time is
 * the end of the hourly mean in Polish local time (compared with the UTC export layer, 2026-10-02).
 */
final class PaaParser implements SourceParser
{
    public function __construct(
        private readonly JsonBody $json,
        private readonly NumberParser $numbers,
        private readonly UtcTimeParser $time,
        private readonly \DateTimeZone $sourceZone,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $counter = new ParseCounter();
        $records = [];
        foreach ($this->json->features($this->json->decode($response)) as $feature) {
            $record = $this->record($feature, $counter);
            if ($record !== null) {
                $counter->valid();
                $records[] = $record;
            }
        }

        return new ParseResult($records, $counter->statistics());
    }

    private function record(Decoded $feature, ParseCounter $counter): ?DoseRateReading
    {
        $p = $feature->get('properties');
        $id = $p->get('id')->string();
        $lon = $feature->get('geometry', 'coordinates', 0)->float();
        $lat = $feature->get('geometry', 'coordinates', 1)->float();
        $parts = preg_split('/\s+/u', trim($p->get('tip_value')->string() ?? ''), 2) ?: [];
        $value = $this->numbers->parse($parts[0] ?? null);
        $position = $lon === null || $lat === null ? null : Coordinate::tryFromLonLat($lon, $lat);
        if ($id === null || $position === null || $value === null || $value < 0) {
            $counter->rejected(match (true) {
                $id === null => 'missingId',
                $lon === null || $lat === null => 'missingPosition',
                $position === null => 'invalidPosition',
                default => 'invalidValue',
            });

            return null;
        }

        return new DoseRateReading(
            'paa:' . $id,
            $p->get('stacja')->string() ?? $id,
            'https://monitoring.paa.gov.pl/',
            'PL',
            $position,
            $this->time->parse($p->get('tip_date')->string(), $this->sourceZone),
            $value,
            $parts[1] ?? 'µSv/h',
            '1h',
        );
    }
}
