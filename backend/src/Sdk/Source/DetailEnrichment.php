<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\HttpClient;
use CommonSight\Port\PluginStore;
use CommonSight\Sdk\Plugin\Diagnostic;

/**
 * Enriches records with details fetched one by one (detail text, warning area): plans the missing ones, fetches them,
 * keeps them in the store of the plugin until the message expires, then applies them (Architecture 4.9).
 */
final class DetailEnrichment
{
    /** Details without their own expiry date are kept this long. */
    private const DEFAULT_TTL_SEC = 3 * 86400;
    /**
     * An answer without a usable detail (e.g. no matching message, no valid area) is not asked again for this long, so
     * the same records do not take every request of each run and the later ones get their turn.
     */
    private const NONE_TTL_SEC = 1800;

    public function __construct(private readonly HttpClient $http, private readonly PluginStore $store, private readonly DetailPlan $plan) {}

    /**
     * @param list<object> $records
     * @return array{list<object>, list<Diagnostic>} enriched records and what to log
     */
    public function enrich(DetailSource $source, array $records, UtcInstant $now): array
    {
        $entries = array_filter($this->load($source->cacheName()), static fn(array $e): bool => $e['expiresAt'] > $now->timestamp);
        $missing = $this->plan->missing($source, $records, $entries);
        [$found, $diagnostics] = $this->fetchDetails($source, $missing, $now);
        $entries = array_merge($entries, $found);
        $this->store->write($source->cacheName(), json_encode($entries, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $enriched = [];
        foreach ($records as $record) {
            $enriched[] = $this->applied($source, $record, $entries, $diagnostics);
        }

        return [$enriched, $diagnostics];
    }

    /**
     * The record with its detail; a kept detail that no longer applies (e.g. after a change of the validation) leaves
     * the record without it instead of failing the whole source.
     *
     * @param array<string, array{expiresAt: int, detail: array<string, mixed>, none?: bool}> $entries
     * @param list<Diagnostic> $diagnostics
     */
    private function applied(DetailSource $source, object $record, array $entries, array &$diagnostics): object
    {
        $key = $source->cacheKey($record);
        if ($key === null || !isset($entries[$key]) || ($entries[$key]['none'] ?? false)) {
            return $record;
        }
        try {
            return $source->apply($record, $entries[$key]['detail']);
        } catch (\InvalidArgumentException $e) {
            $diagnostics[] = new Diagnostic('warning', 'details.unusable', ['cache' => $source->cacheName(), 'key' => $key, 'reason' => $e->getMessage()]);

            return $record;
        }
    }

    /** @return array<string, array{expiresAt: int, detail: array<string, mixed>, none?: bool}> */
    private function load(string $name): array
    {
        $decoded = json_decode((string) $this->store->read($name), true);
        $entries = [];
        foreach (is_array($decoded) ? $decoded : [] as $key => $entry) {
            if (is_array($entry) && is_int($entry['expiresAt'] ?? null) && is_array($entry['detail'] ?? null)) {
                /** @var array<string, mixed> $detail */
                $detail = $entry['detail'];
                $entries[(string) $key] = ['expiresAt' => $entry['expiresAt'], 'detail' => $detail] + (($entry['none'] ?? false) === true ? ['none' => true] : []);
            }
        }

        return $entries;
    }

    /**
     * @param list<object> $records
     * @return array{array<string, array{expiresAt: int, detail: array<string, mixed>, none?: bool}>, list<Diagnostic>}
     */
    private function fetchDetails(DetailSource $source, array $records, UtcInstant $now): array
    {
        $pending = [];
        $requests = [];
        foreach ($records as $record) {
            $request = $source->request($record);
            if ($request !== null) {
                $pending[] = $record;
                $requests[] = $request;
            }
        }
        if ($requests === []) {
            return [[], []];
        }
        $found = [];
        foreach ($this->http->fetchAll($requests) as $index => $response) {
            $record = $pending[$index];
            $key = $source->cacheKey($record);
            if ($key === null || !$response instanceof HttpResponse) {
                continue; // a failed request is asked again in the next run
            }
            $detail = $source->extract($record, $response);
            $expiresAt = $source->expiresAt($record)->timestamp ?? $now->timestamp + self::DEFAULT_TTL_SEC;
            $found[$key] = $detail !== null
                ? ['expiresAt' => $expiresAt, 'detail' => $detail]
                : ['expiresAt' => min($expiresAt, $now->timestamp + self::NONE_TTL_SEC), 'detail' => [], 'none' => true];
        }
        $usable = count(array_filter($found, static fn(array $e): bool => !($e['none'] ?? false)));

        return [$found, [new Diagnostic('info', 'details.fetched', ['cache' => $source->cacheName(), 'requested' => count($requests), 'found' => $usable])]];
    }
}
