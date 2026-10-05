<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Recording;

use CommonSight\Model\Decoded;

/**
 * Shortens recorded responses to their first N items (lists, GeoJSON features, RSS items, Atom entries), so that the
 * test data stays small; time series keep their most recent values. Taken over from the former recorder; items
 * east of 17.5° E are dropped, so that national sources keep what lies near DACH.
 */
final class ResponseTrimmer
{
    private const EAST_LIMIT = 17.5;

    public function __construct(private readonly int $limit = 25) {}

    public function trim(string $body): string
    {
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($this->trimJson($decoded), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n";
        }

        return str_starts_with(ltrim($body), '<') ? $this->trimXml($body) : $body;
    }

    private function trimJson(mixed $decoded): mixed
    {
        if (!is_array($decoded)) {
            return $decoded;
        }
        if (array_is_list($decoded)) {
            return $this->trimList($decoded);
        }
        if (is_array($decoded['features'] ?? null)) {
            $west = static fn(mixed $f): bool => (Decoded::of($f)->get('geometry', 'coordinates', 0)->float() ?? 0.0) < self::EAST_LIMIT;
            $decoded['features'] = array_slice(array_values(array_filter($decoded['features'], $west)), 0, $this->limit);
        }
        foreach (['data', 'results'] as $key) {
            if (is_array($decoded[$key] ?? null) && array_is_list($decoded[$key])) {
                $decoded[$key] = array_slice($decoded[$key], 0, $this->limit * 4);
            }
        }
        $results = $decoded['results'] ?? null;
        if (is_array($results) && is_array($results['bindings'] ?? null)) {
            $results['bindings'] = array_slice($results['bindings'], 0, $this->limit);
            $decoded['results'] = $results;
        }

        return $decoded;
    }

    /**
     * @param list<mixed> $list
     * @return list<mixed>
     */
    private function trimList(array $list): array
    {
        $list = array_values(array_filter($list, static fn(mixed $item): bool => (Decoded::of($item)->get('lon')->float() ?? 0.0) < self::EAST_LIMIT));
        $first = $list[0] ?? null;
        $hasHeader = is_array($first) && ($first[0] ?? null) === 'time_tag';
        if ($hasHeader || (is_array($first) && isset($first['time_tag']))) {
            // Time series are oldest first: keep the most recent values and a header row.
            $header = $hasHeader ? [array_shift($list)] : [];

            return [...$header, ...array_slice($list, -$this->limit)];
        }

        return array_slice($list, 0, $this->limit);
    }

    private function trimXml(string $xml): string
    {
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = true;
        if (!@$document->loadXML($xml, LIBXML_NONET)) {
            return $xml;
        }
        foreach (['item', 'entry'] as $name) {
            $nodes = iterator_to_array($document->getElementsByTagNameNS('*', $name));
            if ($nodes === []) {
                $nodes = iterator_to_array($document->getElementsByTagName($name));
            }
            foreach (array_slice($nodes, $this->limit) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        return (string) $document->saveXML();
    }
}
