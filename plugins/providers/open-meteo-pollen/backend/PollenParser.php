<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoPollen;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\OpenMeteoPollen\Record\PollenValues;
use CommonSight\Sdk\Place\CityDirectory;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Assigns the response of the batch query to the places in the order of the query. A place missing from the response
 * or without any pollen field (a format change) counts as discarded; a place whose values are all empty is skipped: the model has no pollen for it right now (outside
 * the season), which is no error. Negative values are dropped.
 */
final class PollenParser implements SourceParser
{
    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly CityDirectory $cities,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        $locations = $data->isList() ? $data->list() : [$data];
        $counter = new ParseCounter();
        $records = [];
        foreach ($this->cities->forCountry($context->scope) as $index => $city) {
            $current = ($locations[$index] ?? Decoded::of(null))->get('current');
            if ($current->isNull()) {
                $counter->rejected('missingPlace');
                continue;
            }
            if (!$this->namesThePollen($current)) {
                $counter->rejected('missingField'); // the response no longer has the pollen fields: a format change
                continue;
            }
            $concentrations = $this->concentrations($current);
            if ($concentrations === []) {
                $counter->skipped(); // no pollen in the model right now, e.g. outside the season
                continue;
            }
            $counter->valid();
            $records[] = new PollenValues($city, $this->time->parseUtc($current->get('time')->raw()), $concentrations);
        }

        return new ParseResult($records, $counter->statistics());
    }

    /** The block names at least one pollen type, even if empty (null outside the season). */
    private function namesThePollen(Decoded $current): bool
    {
        foreach (PollenType::cases() as $type) {
            if (array_key_exists($type->apiName(), $current->array())) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, float> grains per m³ by pollen type */
    private function concentrations(Decoded $current): array
    {
        $values = [];
        foreach (PollenType::cases() as $type) {
            $value = $current->get($type->apiName())->float();
            if ($value !== null && $value >= 0) {
                $values[$type->value] = $value;
            }
        }

        return $values;
    }
}
