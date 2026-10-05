<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Xml;

use CommonSight\Port\MalformedXml;
use CommonSight\Port\XmlDocument;
use CommonSight\Port\XmlEntryReader;

/**
 * Reads XML without DTD, without entities and without network access (F-09) and collects the entries with the requested names.
 *
 * All responses are small enough for DOM (Architecture 4.8); XMLReader would bring no advantage here.
 */
final class SafeXmlReader implements XmlEntryReader
{
    public function read(string $xml, array $localNames): XmlDocument
    {
        // The byte search for a DTD only works in ASCII-compatible encodings: UTF-16 or UTF-32 (NUL bytes between the
        // characters) would hide one, and libxml would expand its entities while loading (billion laughs).
        if (str_contains($xml, "\0")) {
            throw new MalformedXml('XML in UTF-16 or UTF-32 is not processed');
        }
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new MalformedXml('XML with DTD or entities is not processed');
        }
        $document = new \DOMDocument();
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
            $error = libxml_get_last_error();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded || $document->documentElement === null) {
            throw new MalformedXml('Invalid XML' . ($error !== false ? ': ' . trim($error->message) : ''));
        }
        if ($document->doctype !== null) {
            throw new MalformedXml('XML with DTD or entities is not processed');
        }

        return new XmlDocument($document->documentElement, $this->entries($document->documentElement, $localNames));
    }

    /**
     * @param list<string> $localNames
     * @return list<\DOMElement>
     */
    private function entries(\DOMElement $root, array $localNames): array
    {
        $entries = [];
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            if (in_array($node->localName, $localNames, true)) {
                $entries[] = $node;
                continue;
            }
            for ($child = $node->lastChild; $child !== null; $child = $child->previousSibling) {
                if ($child instanceof \DOMElement) {
                    $stack[] = $child;
                }
            }
        }

        return $entries;
    }
}
