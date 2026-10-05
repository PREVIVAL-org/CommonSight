<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Rijkswaterstaat;

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
use CommonSight\Sdk\Station\NewestPerStation;
use CommonSight\Sdk\Station\WaterReading;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Converts the latest observations of Rijkswaterstaat into WaterReadings in cm above NAP: only measurements
 * (ProcesType "meting"), per location the newest of its instruments. Values beyond ±100 m are error codes.
 */
final class RijkswaterstaatParser implements SourceParser
{
    private const MAX_ABS_CM = 10_000;

    public function __construct(private readonly JsonBody $json, private readonly UtcTimeParser $time) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        if ($data->get('Succesvol')->bool() !== true || !$data->get('WaarnemingenLijst')->isList()) {
            throw new UnreadableResponse('Rijkswaterstaat response not successful: ' . ($data->get('Foutmelding')->string() ?? '-'));
        }
        $counter = new ParseCounter();
        /** @var NewestPerStation<array{Decoded, float}> $newest */
        $newest = new NewestPerStation($counter);
        foreach ($data->get('WaarnemingenLijst')->list() as $observation) {
            if ($observation->get('AquoMetadata', 'ProcesType')->string() !== 'meting') {
                $counter->skipped();
                continue;
            }
            $location = $observation->get('Locatie');
            $code = $location->get('Code')->string();
            $measurement = $observation->get('MetingenLijst', 0);
            $value = $measurement->get('Meetwaarde', 'Waarde_Numeriek')->float();
            $time = $this->time->parseUtc($measurement->get('Tijdstip')->string());
            if ($code === null || $value === null || $time === null || abs($value) > self::MAX_ABS_CM) {
                $counter->rejected($code === null ? 'missingId' : 'invalidValue');
                continue;
            }
            $newest->offer($code, $time, [$location, $value]);
        }

        return new ParseResult($this->readings($newest, $counter), $counter->statistics());
    }

    /**
     * @param NewestPerStation<array{Decoded, float}> $newest
     * @return list<WaterReading>
     */
    private function readings(NewestPerStation $newest, ParseCounter $counter): array
    {
        $readings = [];
        foreach ($newest->all() as $code => [$time, [$location, $value]]) {
            $lat = $location->get('Lat')->float();
            $lon = $location->get('Lon')->float();
            $position = $lat === null || $lon === null ? null : Coordinate::tryFromLatLon($lat, $lon);
            if ($position === null) {
                $counter->rejected($lat === null || $lon === null ? 'missingPosition' : 'invalidPosition');
                continue;
            }
            $counter->valid();
            $readings[] = new WaterReading(
                'rijkswaterstaat:' . $code,
                $location->get('Naam')->string() ?? $code,
                'https://waterinfo.rws.nl/',
                'NL',
                $position,
                $time,
                $value,
                'cm',
                'source.rijkswaterstaat.reference.nap',
                FloodStages::none('Rijkswaterstaat'),
            );
        }

        return $readings;
    }
}
