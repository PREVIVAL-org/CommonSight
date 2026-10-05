<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoAir;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\OpenMeteoAir\Record\CurrentValues;
use CommonSight\Sdk\Place\CityDirectory;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Assigns the response of an Open-Meteo batch query to the places; if a place's main value is missing, it counts as discarded (Q-WE-04). */
final class OpenMeteoParser implements SourceParser
{
    /** @param string $mainValue value without which the place gets no item, e.g. temperature_2m */
    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly CityDirectory $cities,
        private readonly string $mainValue,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        $locations = $data->isList() ? $data->list() : [$data];
        $counter = new ParseCounter();
        $records = [];
        foreach ($this->cities->forCountry($context->scope) as $index => $city) {
            $current = ($locations[$index] ?? Decoded::of(null))->get('current');
            $values = $this->values($current);
            if (!isset($values[$this->mainValue])) {
                $counter->rejected('missingValue');
                continue;
            }
            $counter->valid();
            $records[] = new CurrentValues($city, $this->time->parseUtc($current->get('time')->raw()), $values);
        }

        return new ParseResult($records, $counter->statistics());
    }

    /** @return array<string, float> */
    private function values(Decoded $current): array
    {
        $values = [];
        foreach ($current->entries() as $name => $value) {
            $number = $name === 'time' ? null : $value->float();
            if ($number !== null) {
                $values[$name] = $number;
            }
        }

        return $values;
    }
}
