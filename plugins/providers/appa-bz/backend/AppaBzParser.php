<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AppaBz;

use CommonSight\Model\Http\HttpResponse;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Station\DoseRateReading;
use CommonSight\Sdk\Station\NewestPerStation;
use CommonSight\Sdk\Station\StationDirectory;
use CommonSight\Sdk\Text\UtcTimeParser;

/**
 * Converts the sensor list of the South Tyrol Agency for Environment into DoseRateReadings: only gamma probes
 * (MCODE GAMMA) with hourly values (TYPE 1, TYPE 2 is the daily mean); -1 marks a probe without a value.
 */
final class AppaBzParser implements SourceParser
{
    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly StationDirectory $stations,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        if (!$data->isList()) {
            throw new UnreadableResponse('Sensor list of the South Tyrol Agency for Environment missing');
        }
        $counter = new ParseCounter();
        /** @var NewestPerStation<array{float, string}> $newest */
        $newest = new NewestPerStation($counter);
        foreach ($data->list() as $sensor) {
            $code = $sensor->get('SCODE')->string();
            if ($sensor->get('MCODE')->string() !== 'GAMMA' || $sensor->get('TYPE')->text() !== '1' || $code === null || $this->stations->find($code) === null) {
                $counter->skipped();
                continue;
            }
            $value = $sensor->get('VALUE')->float();
            $time = $this->time->parseUtc($sensor->get('DATE')->string());
            if ($value === null || $value < 0 || $time === null) {
                $counter->rejected('invalidValue');
                continue;
            }
            $newest->offer($code, $time, [$value, $sensor->get('UNIT')->string() ?? 'µSv/h']);
        }

        return new ParseResult($this->readings($newest, $counter), $counter->statistics());
    }

    /**
     * @param NewestPerStation<array{float, string}> $newest
     * @return list<DoseRateReading>
     */
    private function readings(NewestPerStation $newest, ParseCounter $counter): array
    {
        $readings = [];
        foreach ($newest->all() as $code => [$time, [$value, $unit]]) {
            $station = $this->stations->find($code);
            if ($station === null) {
                continue;
            }
            $counter->valid();
            $readings[] = new DoseRateReading('appa-bz:' . $code, $station->name, 'https://dati.retecivica.bz.it/de/dataset/situazione-dell-aria', 'IT', $station->position, $time, $value, $unit, '1h');
        }

        return $readings;
    }
}
