<?php

declare(strict_types=1);

namespace CommonSight\Port;

/** Read XML document: root element and the requested entries. */
final readonly class XmlDocument
{
    /** @param list<\DOMElement> $entries */
    public function __construct(public \DOMElement $root, public array $entries) {}

    /** Text of the first descendant with this local name, otherwise ''. */
    public static function text(\DOMElement $node, string $localName): string
    {
        foreach ($node->getElementsByTagNameNS('*', $localName) as $found) {
            return trim($found->textContent);
        }
        foreach ($node->getElementsByTagName($localName) as $found) {
            return trim($found->textContent);
        }

        return '';
    }

    /** @param list<string> $localNames first non-empty text in this order */
    public static function firstText(\DOMElement $node, array $localNames): string
    {
        foreach ($localNames as $name) {
            $text = self::text($node, $name);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /** @return list<\DOMElement> direct children with this local name */
    public static function children(\DOMElement $node, string $localName): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
