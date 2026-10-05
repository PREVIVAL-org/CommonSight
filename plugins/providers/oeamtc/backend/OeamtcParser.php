<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Oeamtc;

use CommonSight\Model\Http\HttpResponse;
use CommonSight\Plugin\Oeamtc\Record\TrafficEntry;
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

/** Converts the ÖAMTC GeoRSS feed into TrafficEntries; data timestamp from lastBuildDate or pubDate of the channel (Q-TR-AT-02). */
final class OeamtcParser implements SourceParser
{
    /** Language identifiers of the feed (Windows LCID) -> ISO 639-1. */
    private const LANGUAGES = ['2057' => 'en', '1033' => 'en', '1031' => 'de', '3079' => 'de', 'en' => 'en', 'de' => 'de'];

    public function __construct(
        private readonly XmlEntryReader $xml,
        private readonly UtcTimeParser $time,
        private readonly TextCleaner $text,
        private readonly StableId $stableId,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        try {
            $document = $this->xml->read($response->body, ['item']);
        } catch (MalformedXml $e) {
            throw new UnreadableResponse($e->getMessage(), 0, $e);
        }
        $channel = XmlDocument::children($document->root, 'channel')[0] ?? null;
        if ($channel === null) {
            throw new UnreadableResponse('RSS without channel');
        }
        // Also regional tags such as de-at: their language is the first part.
        $tag = strtolower($this->ownText($channel, 'language'));
        $language = self::LANGUAGES[$tag] ?? self::LANGUAGES[explode('-', $tag)[0]] ?? null;
        $counter = new ParseCounter();
        $records = [];
        foreach ($document->entries as $item) {
            $counter->valid();
            $records[] = $this->record($item, $language);
        }
        $updated = $this->viennaTime($this->ownText($channel, 'lastBuildDate') ?: $this->ownText($channel, 'pubDate'));

        return new ParseResult($records, $counter->statistics(), $updated);
    }

    private function record(\DOMElement $item, ?string $language): TrafficEntry
    {
        $guid = XmlDocument::text($item, 'guid');

        return new TrafficEntry(
            $guid !== '' ? $guid : $this->stableId->fromParts('oeamtc', $item->textContent),
            $this->text->clean(XmlDocument::text($item, 'title')),
            $this->text->clean(XmlDocument::text($item, 'description')),
            XmlDocument::text($item, 'link') ?: null,
            $this->viennaTime(XmlDocument::firstText($item, ['pubDate', 'date'])),
            $this->text->clean(XmlDocument::text($item, 'category')),
            XmlDocument::text($item, 'point'),
            XmlDocument::text($item, 'line'),
            $language,
        );
    }

    /**
     * The feed labels Vienna local time as "GMT" (16:33 "GMT" when fetched at 14:36 UTC, checked on 2026-09-28);
     * the value is therefore read without zone identifier in Europe/Vienna (D-18).
     */
    private function viennaTime(string $value): ?\CommonSight\Model\Value\UtcInstant
    {
        $local = (string) preg_replace('/\s+(GMT|UTC|Z)$/i', '', trim($value));

        return $this->time->parse($local, new \DateTimeZone('Europe/Vienna'));
    }

    private function ownText(\DOMElement $node, string $name): string
    {
        foreach (XmlDocument::children($node, $name) as $child) {
            return trim($child->textContent);
        }

        return '';
    }
}
