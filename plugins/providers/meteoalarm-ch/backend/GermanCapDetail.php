<?php

declare(strict_types=1);

namespace CommonSight\Plugin\MeteoAlarm;

use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\MeteoAlarm\Record\CapEntry;
use CommonSight\Port\MalformedXml;
use CommonSight\Port\XmlDocument;
use CommonSight\Port\XmlEntryReader;
use CommonSight\Sdk\Source\DetailSource;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Text\TextCleaner;

/**
 * The German texts of a warning: the Atom feed of MeteoAlarm names event and title in English and summarises in the
 * language of the region (e.g. Italian for Ticino); the full CAP message behind the id of each entry has one block per
 * language. The first German block (de, de-CH, ...) replaces headline, event, description and instruction; a warning
 * without one keeps the texts of the feed. Fetched once per warning and version (key id and update time).
 */
final class GermanCapDetail implements DetailSource
{
    private const API = 'https://feeds.meteoalarm.org/api/';

    public function __construct(private readonly XmlEntryReader $xml, private readonly TextCleaner $text) {}

    public function cacheName(): string
    {
        return 'meteoalarm-german';
    }

    public function cacheKey(object $record): ?string
    {
        $record = RecordType::expect($record, CapEntry::class);

        return str_starts_with($record->id, self::API) ? $record->id . '#' . ($record->updated?->toIso() ?? '') : null;
    }

    public function request(object $record): ?HttpRequest
    {
        $record = RecordType::expect($record, CapEntry::class);

        return str_starts_with($record->id, self::API)
            ? new HttpRequest($record->id, 'application/cap+xml, application/xml;q=0.9, text/xml;q=0.8', MeteoAlarmRequest::SOURCE_ID, $record->id)
            : null;
    }

    public function extract(object $record, HttpResponse $response): ?array
    {
        try {
            $document = $this->xml->read($response->body, ['info']);
        } catch (MalformedXml) {
            return null; // The warning keeps the texts of the feed, query in the next run.
        }
        foreach ($document->entries as $info) {
            if (str_starts_with(strtolower(XmlDocument::text($info, 'language')), 'de')) {
                return [
                    'headline' => $this->text->clean(XmlDocument::text($info, 'headline')),
                    'event' => $this->text->clean(XmlDocument::text($info, 'event')),
                    'description' => $this->text->clean(XmlDocument::text($info, 'description')),
                    'instruction' => $this->text->clean(XmlDocument::text($info, 'instruction')),
                ];
            }
        }

        return null;
    }

    public function apply(object $record, array $detail): object
    {
        $record = RecordType::expect($record, CapEntry::class);
        $text = static fn(string $key): string => is_string($detail[$key] ?? null) ? $detail[$key] : '';

        return $record->inGerman($text('headline'), $text('event'), $text('description'), $text('instruction'));
    }

    public function expiresAt(object $record): ?UtcInstant
    {
        return RecordType::expect($record, CapEntry::class)->expires;
    }
}
