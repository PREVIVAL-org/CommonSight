<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaKp;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\NoaaKp\Record\KpSeries;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Reads the planetary Kp index; supports rows as objects and the older format with a header row. */
final class KpParser implements SourceParser
{
    public function __construct(private readonly JsonBody $json, private readonly UtcTimeParser $time) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $counter = new ParseCounter();
        $values = [];
        $times = [];
        foreach ($this->json->decode($response)->list() as $row) {
            [$value, $time] = $this->row($row);
            if ($value === null || $time === null) {
                if ($row->get(0)->string() !== 'time_tag') {
                    $counter->rejected('invalidRow');
                }
                continue;
            }
            $counter->valid();
            $values[] = $value;
            $times[] = $time;
        }
        if ($values === []) {
            throw new UnreadableResponse('Kp index without valid values');
        }

        return new ParseResult([new KpSeries($values, $times)], $counter->statistics());
    }

    /** @return array{?float, ?UtcInstant} */
    private function row(Decoded $row): array
    {
        $value = $row->get('Kp')->float() ?? $row->get('kp_index')->float() ?? $row->get(1)->float();
        $time = $row->get('time_tag')->string() ?? $row->get(0)->string();

        return [$value, $this->time->parseUtc($time)];
    }
}
