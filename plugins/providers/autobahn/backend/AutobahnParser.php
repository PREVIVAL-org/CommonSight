<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Autobahn;

use CommonSight\Model\Decoded;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\Autobahn\Record\RoadWarning;
use CommonSight\Sdk\Source\JsonBody;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\TextCleaner;

/**
 * Converts the warnings, closures or roadworks of an Autobahn into RoadWarnings. Of the roadworks only the short-term
 * ones: the long-term sites (weeks to months, several hundred) would cover the map without telling anything new.
 */
final class AutobahnParser implements SourceParser
{
    public function __construct(private readonly JsonBody $json, private readonly TextCleaner $text) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        $body = $this->json->decode($response);
        $service = $this->service($body);
        $counter = new ParseCounter();
        $records = [];
        foreach ($body->get($service)->list() as $entry) {
            // Announced for later (future: true) is not a current notice; nor is a long-term roadworks site.
            if ($entry->get('future')->bool() === true || !$this->shown($service, $entry)) {
                $counter->skipped();
                continue;
            }
            $record = $this->record($entry);
            if ($record === null) {
                $counter->rejected('missingId');
                continue;
            }
            $counter->valid();
            $records[] = $record;
        }

        return new ParseResult($records, $counter->statistics());
    }

    /** The service of the response: the one list it carries. */
    private function service(Decoded $body): string
    {
        foreach (AutobahnRequest::SERVICES as $service) {
            if ($body->get($service)->isList()) {
                return $service;
            }
        }
        throw new UnreadableResponse('Autobahn response without ' . implode(', ', AutobahnRequest::SERVICES));
    }

    private function shown(string $service, Decoded $entry): bool
    {
        return $service !== 'roadworks' || $entry->get('display_type')->string() === 'SHORT_TERM_ROADWORKS';
    }

    private function record(Decoded $w): ?RoadWarning
    {
        $id = $w->get('identifier')->string();
        if ($id === null || $id === '') {
            return null;
        }
        $description = $w->get('description');
        $lines = $description->isList() ? $description->strings() : [$description->text() ?? ''];

        return new RoadWarning(
            $id,
            $this->text->clean($w->get('title')->raw()),
            $this->text->clean($w->get('subtitle')->raw()),
            array_values(array_filter(array_map($this->text->clean(...), $lines), static fn(string $l): bool => $l !== '')),
            $w->get('startTimestamp')->string(),
            $w->get('point')->string(),
            $w->get('abnormalTrafficType')->string(),
            $w->get('display_type')->string(),
        );
    }
}
