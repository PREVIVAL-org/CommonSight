<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Dwd;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\Dwd\Record\DwdWarningFeature;
use CommonSight\Sdk\Source\GeoJsonGeometry;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Converts a page of the DWD WFS into DwdWarningFeatures; skips cancellations and expired warnings. */
final class DwdWarningParser implements SourceParser
{
    public function __construct(
        private readonly JsonBody $json,
        private readonly UtcTimeParser $time,
        private readonly TextCleaner $text,
        private readonly GeoJsonGeometry $geometry,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $data = $this->json->decode($response);
        $counter = new ParseCounter();
        $records = [];
        foreach ($this->json->features($data) as $feature) {
            $record = $this->record($feature, $counter);
            if ($record === null) {
                continue;
            }
            if ($record->EXPIRES !== null && !$record->EXPIRES->isAfter($context->now)) {
                $counter->skipped();
                continue;
            }
            $counter->valid();
            $records[] = $record;
        }

        return new ParseResult($records, $counter->statistics(), null, $data->get('numberMatched')->int());
    }

    private function record(Decoded $feature, ParseCounter $counter): ?DwdWarningFeature
    {
        $p = $feature->get('properties');
        // Cancelled warnings and CAP messages that are not real ones (Test, Exercise, System, Draft) are not shown.
        $status = $p->get('STATUS')->string();
        if ($p->get('MSGTYPE')->string() === 'Cancel' || ($status !== null && $status !== 'Actual')) {
            $counter->skipped();

            return null;
        }
        $id = $feature->get('id')->string() ?? $p->get('IDENTIFIER')->string();
        if ($id === null || $id === '') {
            $counter->rejected('missingId');

            return null;
        }

        return new DwdWarningFeature(
            $id,
            $this->text->clean($p->get('HEADLINE')->raw()),
            $this->text->clean($p->get('AREADESC')->raw()),
            $this->text->clean($p->get('DESCRIPTION')->raw()),
            $this->text->clean($p->get('INSTRUCTION')->raw()),
            $this->text->clean($p->get('EVENT')->raw()),
            $p->get('SEVERITY')->string() ?? 'Unknown',
            $p->get('WEB')->string(),
            $this->time->parseUtc($p->get('SENT')->raw()),
            $this->time->parseUtc($p->get('ONSET')->raw()),
            $this->time->parseUtc($p->get('EXPIRES')->raw()),
            $this->geometry->read($feature->get('geometry')),
        );
    }
}
