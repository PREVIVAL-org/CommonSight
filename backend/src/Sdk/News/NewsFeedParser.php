<?php

declare(strict_types=1);

namespace CommonSight\Sdk\News;

use CommonSight\Model\Http\HttpResponse;
use CommonSight\Port\MalformedXml;
use CommonSight\Port\XmlDocument;
use CommonSight\Port\XmlEntryReader;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\ParseCounter;
use CommonSight\Sdk\Source\ParseResult;
use CommonSight\Sdk\Source\SourceParser;
use CommonSight\Sdk\Source\UnreadableResponse;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Converts an RSS, RDF or Atom feed into FeedEntries. */
final class NewsFeedParser implements SourceParser
{
    private const ROOTS = ['rss', 'RDF', 'feed'];

    public function __construct(
        private readonly XmlEntryReader $xml,
        private readonly UtcTimeParser $time,
        private readonly TextCleaner $text,
    ) {}

    public function parse(HttpResponse $response, ParseContext $context): ParseResult
    {
        try {
            $document = $this->xml->read($response->body, ['item', 'entry']);
        } catch (MalformedXml $e) {
            throw new UnreadableResponse($e->getMessage(), 0, $e);
        }
        if (!in_array($document->root->localName, self::ROOTS, true)) {
            throw new UnreadableResponse('Not an RSS, RDF or Atom feed');
        }
        $counter = new ParseCounter();
        $records = [];
        foreach ($document->entries as $entry) {
            // Without title or link an entry cannot be shown: a broken entry, unlike a headline without a topic, which
            // the mapper leaves out on purpose.
            $title = $this->text->clean(XmlDocument::text($entry, 'title'));
            $link = $this->link($entry);
            if ($title === '' || $link === null) {
                $counter->rejected($title === '' ? 'missingTitle' : 'missingLink');
                continue;
            }
            $counter->valid();
            $records[] = new FeedEntry(
                XmlDocument::firstText($entry, ['guid', 'id']),
                $title,
                $this->text->clean(XmlDocument::firstText($entry, ['description', 'summary'])),
                $link,
                $this->time->parseUtc(XmlDocument::firstText($entry, ['pubDate', 'date', 'published', 'updated'])),
            );
        }

        return new ParseResult($records, $counter->statistics());
    }

    private function link(\DOMElement $entry): ?string
    {
        foreach (XmlDocument::children($entry, 'link') as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);
            if ($href !== '' || $text !== '') {
                return $href !== '' ? $href : $text;
            }
        }

        return null;
    }
}
