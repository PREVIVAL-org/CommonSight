<?php

declare(strict_types=1);

namespace CommonSight\Plugin\MeteoAlarm;

use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\MeteoAlarm\Record\CapEntry;
use CommonSight\Port\MalformedXml;
use CommonSight\Port\XmlDocument;
use CommonSight\Port\XmlEntryReader;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\StableId;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Converts the MeteoAlarm Atom feed into CapEntries; skips cancellations and expired warnings. */
final class MeteoAlarmParser implements SourceParser
{
    public function __construct(
        private readonly XmlEntryReader $xml,
        private readonly UtcTimeParser $time,
        private readonly TextCleaner $text,
        private readonly StableId $stableId,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        try {
            $document = $this->xml->read($response->body, ['entry']);
        } catch (MalformedXml $e) {
            throw new UnreadableResponse($e->getMessage(), 0, $e);
        }
        if ($document->root->localName !== 'feed') {
            throw new UnreadableResponse('Not an Atom feed');
        }
        $counter = new ParseCounter();
        $records = [];
        foreach ($document->entries as $entry) {
            $record = $this->record($entry);
            $cancelled = XmlDocument::firstText($entry, ['msgType', 'message_type']) === 'Cancel';
            if ($cancelled || ($record->expires !== null && !$record->expires->isAfter($context->now))) {
                $counter->skipped();
                continue;
            }
            $counter->valid();
            $records[] = $record;
        }
        $feedUpdated = $this->time->parseUtc($this->ownText($document->root, 'updated'));

        return new ParseResult($records, $counter->statistics(), $feedUpdated);
    }

    private function record(\DOMElement $entry): CapEntry
    {
        $id = $this->ownText($entry, 'id');

        return new CapEntry(
            $id !== '' ? $id : $this->stableId->fromParts('meteoalarm', $entry->textContent),
            $this->text->clean($this->ownText($entry, 'title')),
            $this->text->clean(XmlDocument::text($entry, 'headline')),
            $this->text->clean(XmlDocument::firstText($entry, ['description', 'summary'])),
            $this->alternateLink($entry),
            $this->time->parseUtc(XmlDocument::firstText($entry, ['updated', 'published', 'sent'])),
            $this->time->parseUtc(XmlDocument::firstText($entry, ['onset', 'effective'])),
            $this->time->parseUtc(XmlDocument::text($entry, 'expires')),
            XmlDocument::text($entry, 'severity'),
            $this->text->clean(XmlDocument::text($entry, 'event')),
            $this->text->clean(XmlDocument::text($entry, 'areaDesc')),
            XmlDocument::text($entry, 'polygon'),
        );
    }

    private function alternateLink(\DOMElement $entry): ?string
    {
        foreach (XmlDocument::children($entry, 'link') as $link) {
            if ($link->getAttribute('rel') === 'alternate' || $link->getAttribute('rel') === '') {
                return $link->getAttribute('href');
            }
        }

        return null;
    }

    /** Text of a direct child element (not of a deeper element with the same name). */
    private function ownText(\DOMElement $node, string $localName): string
    {
        foreach (XmlDocument::children($node, $localName) as $child) {
            return trim($child->textContent);
        }

        return '';
    }
}
