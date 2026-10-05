<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NoaaScales;

use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\NoaaScales\Record\NoaaScale;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Reads the current NOAA scales G, R and S (entry "0" of noaa-scales.json); a missing scale is discarded. */
final class ScalesParser implements SourceParser
{
    public const LETTERS = ['G', 'R', 'S'];

    public function __construct(private readonly JsonBody $json, private readonly UtcTimeParser $time) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $current = $this->json->decode($response)->get('0');
        if (!$current->isArray()) {
            throw new UnreadableResponse('NOAA scales without current entry');
        }
        $stamp = $this->time->parseUtc(sprintf('%sT%sZ', $current->get('DateStamp')->string() ?? '', $current->get('TimeStamp')->string() ?? ''));
        $counter = new ParseCounter();
        $records = [];
        foreach (self::LETTERS as $letter) {
            $scale = $current->get($letter, 'Scale')->int();
            if ($scale === null || $scale < 0 || $scale > 5) {
                $counter->rejected('missingScale');
                continue;
            }
            $counter->valid();
            $records[] = new NoaaScale($letter, $scale, $stamp);
        }

        return new ParseResult($records, $counter->statistics(), $stamp);
    }
}
