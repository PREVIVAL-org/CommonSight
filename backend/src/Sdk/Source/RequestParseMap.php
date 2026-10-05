<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Http\HttpFailure;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;
use CommonSight\Model\Item\Item;
use CommonSight\Port\HttpClient;
use CommonSight\Sdk\Plugin\Diagnostic;
use CommonSight\Sdk\Plugin\SourceOutcome;
use CommonSight\Sdk\Plugin\SourceRun;

/**
 * The common shape of a source (concept: sources as plugins, 4.3): requests -> HTTP -> parser (with follow-up pages)
 * -> details -> mapper. A failed request or an unreadable response drops only that response; a record that does not
 * yield a valid item is rejected and counted. Returns items, numbers, the deficits found and what to log.
 */
final class RequestParseMap
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly SourceParts $parts,
        private readonly ?DetailEnrichment $details = null,
    ) {}

    public function run(SourceRun $run): SourceOutcome
    {
        $context = new ParseContext($run->scope, $run->now);
        $collected = $this->collect($this->parts->request->requestsFor($run->scope, $run->now), $context);
        $diagnostics = array_map(static fn(string $reason): Diagnostic => new Diagnostic('warning', 'source.requestFailed', ['reason' => $reason]), $collected->failures);
        if ($collected->results === []) {
            return SourceOutcome::failure($collected->failureSummary(), $collected->retryAfterSec)->withDiagnostics($diagnostics);
        }
        $parsed = ParseResult::merge($collected->results);
        $records = $parsed->records;
        if ($this->parts->detail !== null && $this->details !== null) {
            [$records, $detailDiagnostics] = $this->details->enrich($this->parts->detail, $records, $run->now);
            array_push($diagnostics, ...$detailDiagnostics);
        }
        [$items, $rejections] = $this->map($records, $context);
        array_push($diagnostics, ...array_map(static fn(string $reason): Diagnostic => new Diagnostic('notice', 'source.recordRejected', ['reason' => $reason]), $rejections));
        $deficits = [...$collected->deficits(), ...$this->deficits($parsed, $items, count($rejections))];

        $outcome = SourceOutcome::success($items, $parsed->statistics, $parsed->sourceUpdatedAt, $deficits)->withDiagnostics($diagnostics);
        $coverage = $this->parts->coverage === null ? null : ($this->parts->coverage)($run->scope);

        return $coverage === null ? $outcome : $outcome->withCoverage($coverage);
    }

    /** @param list<HttpRequest> $requests */
    private function collect(array $requests, ParseContext $context): CollectedResponses
    {
        $collected = $this->parseAll($this->http->fetchAll($requests), $context);
        if ($this->parts->paging === null || count($requests) !== 1 || $collected->results === []) {
            return $collected;
        }
        $followUps = $this->parts->paging->followUpRequests($requests[0], $collected->results[0]);

        return $followUps === [] ? $collected : $collected->plus($this->parseAll($this->http->fetchAll($followUps), $context));
    }

    /** @param list<HttpResponse|HttpFailure> $responses */
    private function parseAll(array $responses, ParseContext $context): CollectedResponses
    {
        $results = [];
        $failures = [];
        $retryAfter = null;
        foreach ($responses as $response) {
            if ($response instanceof HttpFailure) {
                $failures[] = $response->kind->value . ': ' . $response->reason;
                $retryAfter = $response->retryAfterSec === null ? $retryAfter : max($retryAfter ?? 0, $response->retryAfterSec);
                continue;
            }
            try {
                $results[] = $this->parts->parser->parse($response, $context);
            } catch (UnreadableResponse $e) {
                // A response that cannot be evaluated counts like a failed request.
                $failures[] = 'unreadable: ' . $e->getMessage();
            }
        }

        return new CollectedResponses($results, $failures, count($responses), $retryAfter);
    }

    /**
     * @param list<object> $records
     * @return array{list<Item>, list<string>} items and the reasons of the rejected records
     */
    private function map(array $records, ParseContext $context): array
    {
        $items = [];
        $rejections = [];
        foreach ($records as $record) {
            try {
                $item = $this->parts->mapper->map($record, $context);
            } catch (\InvalidArgumentException $e) {
                // A record that does not yield a valid item is discarded and counted.
                $rejections[] = $e->getMessage();
                continue;
            }
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return [$items, $rejections];
    }

    /**
     * @param list<Item> $items
     * @return list<Deficit>
     */
    private function deficits(ParseResult $parsed, array $items, int $mappingRejected): array
    {
        $deficits = [];
        $rejected = $parsed->statistics->rejected + $mappingRejected;
        if ($rejected > 0) {
            $deficits[] = new Deficit(DeficitKind::InvalidRecords, ['count' => $rejected]);
        }
        foreach ($this->parts->detectors as $detector) {
            $deficit = $detector->detect($items);
            if ($deficit !== null) {
                $deficits[] = $deficit;
            }
        }

        return $deficits;
    }
}
