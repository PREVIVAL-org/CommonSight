<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\GeoSphere\Record\WarnFeature;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;

/**
 * Converts the GeoSphere warning status into WarnFeatures and counts invalid records (Q-W-AT-02).
 *
 * Skipped: expired warnings and warning level 0. Invalid: without valid start/end, end <= start,
 * unknown wtype or wlevel, empty warnid. Only invalid and no valid records -> fetch failed.
 */
final class GeoSphereStatusParser implements SourceParser
{
    public function __construct(private readonly JsonBody $json) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        if ($data->get('type')->string() !== 'FeatureCollection') {
            throw new UnreadableResponse('GeoSphere warning status is not a FeatureCollection');
        }
        $counter = new ParseCounter();
        $records = [];
        foreach ($this->json->features($data) as $feature) {
            $record = $this->record($feature, $context, $counter);
            if ($record !== null) {
                $counter->valid();
                $records[] = $record;
            }
        }
        $statistics = $counter->statistics();
        if ($records === [] && $statistics->rejected > 0) {
            throw new UnreadableResponse('GeoSphere delivers no usable warnings');
        }

        return new ParseResult($records, $statistics);
    }

    private function record(Decoded $feature, ParseContext $context, ParseCounter $counter): ?WarnFeature
    {
        $p = $feature->get('properties');
        $start = $this->stamp($p->get('start'));
        $end = $this->stamp($p->get('end'));
        $level = $p->get('wlevel')->int();
        if (($end !== null && $end <= $context->now->timestamp) || $level === 0) {
            $counter->skipped();

            return null;
        }
        $type = $p->get('wtype')->int();
        $warnid = $p->get('warnid')->text();
        $reason = match (true) {
            $start === null || $end === null || $end <= $start => 'invalidPeriod',
            $type === null || $type < 1 || $type > 7 => 'unknownType',
            $level === null || $level < 1 || $level > 3 => 'unknownLevel',
            $warnid === null || $warnid === '' => 'missingId',
            default => null,
        };
        if ($reason !== null || $start === null || $end === null || $type === null || $level === null || $warnid === null) {
            $counter->rejected($reason ?? 'invalid');

            return null;
        }
        $geometry = $feature->get('geometry');

        return new WarnFeature(
            $warnid,
            $type,
            $level,
            $start,
            $end,
            $this->municipalities($p->get('gemeinden')),
            $geometry->get('type')->string(),
            $geometry->get('coordinates')->isArray() ? $geometry->get('coordinates')->array() : null,
        );
    }

    private function stamp(Decoded $value): ?int
    {
        $number = $value->float();

        return $number !== null && $number > 0 ? (int) $number : null;
    }

    /** @return list<string> */
    private function municipalities(Decoded $codes): array
    {
        return array_values(array_filter($codes->strings(), static fn(string $code): bool => preg_match('/^[1-9][0-9]{4}$/D', $code) === 1));
    }
}
