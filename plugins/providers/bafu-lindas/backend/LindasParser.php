<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Lindas;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\Lindas\Record\HydroObservation;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;

/** Converts SPARQL results from LINDAS into HydroObservations; without point or without level and discharge: discarded (Q-WA-CH-02). */
final class LindasParser implements SourceParser
{
    public function __construct(private readonly JsonBody $json) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $bindings = $this->json->decode($response)->get('results', 'bindings');
        if (!$bindings->isList()) {
            throw new UnreadableResponse('LINDAS response without results.bindings');
        }
        $counter = new ParseCounter();
        $records = [];
        foreach ($bindings->list() as $row) {
            $record = $this->record($row, $counter);
            if ($record !== null) {
                $counter->valid();
                $records[] = $record;
            }
        }

        return new ParseResult($records, $counter->statistics());
    }

    private function record(Decoded $row, ParseCounter $counter): ?HydroObservation
    {
        $id = $row->get('id', 'value')->text();
        $time = $row->get('time', 'value')->string();
        $point = preg_match('/POINT\s*\(\s*([-\d.eE]+)\s+([-\d.eE]+)\s*\)/i', $row->get('wkt', 'value')->string() ?? '', $m) === 1 ? [(float) $m[1], (float) $m[2]] : null;
        $level = $row->get('level', 'value')->float();
        $flow = $row->get('flow', 'value')->float();
        if ($id === null || $time === null || $point === null || ($level === null && $flow === null)) {
            $counter->rejected(match (true) {
                $id === null || $time === null => 'missingId',
                $point === null => 'missingPosition',
                default => 'missingValue',
            });

            return null;
        }

        return new HydroObservation(
            $id,
            $row->get('name', 'value')->string() ?? '',
            $row->get('waterName', 'value')->string() ?? '',
            $point[0],
            $point[1],
            $time,
            $level,
            $flow,
            $row->get('temperature', 'value')->float(),
            $row->get('danger', 'value')->int(),
        );
    }
}
