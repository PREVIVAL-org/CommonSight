<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Ehyd;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\Ehyd\Record\EhydGauge;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Text\NumberParser;

/** Converts eHYD PegelAktuell into EhydGauges; values with a decimal comma are read (Q-WA-AT-02). */
final class EhydParser implements SourceParser
{
    public function __construct(private readonly JsonBody $json, private readonly NumberParser $numbers) {}

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

    private function record(Decoded $feature, ParseCounter $counter): ?EhydGauge
    {
        $p = $feature->get('properties');
        $id = $p->get('hzbnr')->text();
        $value = $this->numbers->parse($p->get('wert')->raw());
        $time = $p->get('zp')->string();
        $lon = $feature->get('geometry', 'coordinates', 0)->float();
        $lat = $feature->get('geometry', 'coordinates', 1)->float();
        // A gauge out of service reports neither value nor time: no record, but nothing broken either (like IMGW, ČHMÚ).
        if ($p->get('wert')->isNull() && $p->get('zp')->isNull()) {
            $counter->skipped();

            return null;
        }
        if ($id === null || $value === null || $time === null || $lon === null || $lat === null) {
            $counter->rejected(match (true) {
                $id === null => 'missingId',
                $lon === null || $lat === null => 'missingPosition',
                default => 'missingValue',
            });

            return null;
        }

        return new EhydGauge(
            $id,
            trim($p->get('messstelle')->text() ?? ''),
            trim($p->get('gewasser')->text() ?? ''),
            trim($p->get('hd')->text() ?? ''),
            trim($p->get('parameter')->text() ?? ''),
            $value,
            trim($p->get('einheit')->text() ?? ''),
            $time,
            $this->numbers->parseInt($p->get('gesamtcode')->raw()),
            $p->get('internet')->string(),
            $lon,
            $lat,
        );
    }
}
