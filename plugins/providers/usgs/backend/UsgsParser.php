<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Usgs;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\Usgs\Record\Quake;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Converts the USGS event catalog into Quakes; an empty result is valid (Q-NA-04). */
final class UsgsParser implements SourceParser
{
    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly TextCleaner $text,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        $counter = new ParseCounter();
        $records = [];
        foreach ($this->json->features($data) as $feature) {
            $record = $this->record($feature);
            if ($record === null) {
                $counter->rejected('incomplete');
                continue;
            }
            $counter->valid();
            $records[] = $record;
        }

        return new ParseResult($records, $counter->statistics(), $this->time->parseUtc($data->get('metadata', 'generated')->raw()));
    }

    private function record(Decoded $feature): ?Quake
    {
        $p = $feature->get('properties');
        $coordinates = $feature->get('geometry', 'coordinates');
        $id = $feature->get('id')->string();
        $mag = $p->get('mag')->float();
        $lon = $coordinates->get(0)->float();
        $lat = $coordinates->get(1)->float();
        if ($id === null || $mag === null || $lon === null || $lat === null) {
            return null;
        }

        return new Quake(
            $id,
            $this->text->clean($p->get('title')->raw()),
            $mag,
            $this->text->clean($p->get('place')->raw()),
            $this->time->parseUtc($p->get('time')->raw()),
            $p->get('url')->string(),
            $lon,
            $lat,
            $coordinates->get(2)->float() ?? 0.0,
        );
    }
}
