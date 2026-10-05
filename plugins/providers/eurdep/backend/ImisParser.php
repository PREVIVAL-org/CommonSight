<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Eurdep;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\Eurdep\Record\DoseRateStation;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;

/**
 * Converts the WFS response of BfS/IMIS into DoseRateStations: only stations in operation (site_status 1)
 * with a valid, non-negative value and coordinates (Q-RA-02).
 */
final class ImisParser implements SourceParser
{
    public function __construct(private readonly JsonBody $json) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $counter = new ParseCounter();
        $records = [];
        foreach ($this->json->features($this->json->decode($response)) as $feature) {
            if ($feature->get('properties', 'site_status')->int() !== 1) {
                $counter->skipped();
                continue;
            }
            $record = $this->record($feature, $counter);
            if ($record !== null) {
                $counter->valid();
                $records[] = $record;
            }
        }

        return new ParseResult($records, $counter->statistics());
    }

    private function record(Decoded $feature, ParseCounter $counter): ?DoseRateStation
    {
        $p = $feature->get('properties');
        $id = $p->get('id')->string();
        $value = $p->get('value')->float();
        $lon = $feature->get('geometry', 'coordinates', 0)->float();
        $lat = $feature->get('geometry', 'coordinates', 1)->float();
        if ($id === null || $id === '' || $value === null || $value < 0 || $lon === null || $lat === null) {
            $counter->rejected(match (true) {
                $id === null || $id === '' => 'missingId',
                $lon === null || $lat === null => 'missingPosition',
                default => 'invalidValue',
            });

            return null;
        }

        return new DoseRateStation(
            $id,
            $p->get('name')->string() ?? $id,
            $lon,
            $lat,
            $value,
            $p->get('unit')->string() ?? '',
            $p->get('end_measure')->string(),
            $p->get('duration')->string() ?? '',
        );
    }
}
