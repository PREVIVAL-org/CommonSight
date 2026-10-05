<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Http\FailureKind;

/** Parsed responses of a source together with the failed requests. */
final readonly class CollectedResponses
{
    /**
     * @param list<ParseResult> $results
     * @param list<string> $failures
     */
    public function __construct(
        public array $results,
        public array $failures,
        public int $total,
        /** the longest wait a provider asked for in a failed request (Retry-After) */
        public ?int $retryAfterSec = null,
    ) {}

    public function plus(self $other): self
    {
        $retryAfter = $this->retryAfterSec === null || $other->retryAfterSec === null ? ($this->retryAfterSec ?? $other->retryAfterSec) : max($this->retryAfterSec, $other->retryAfterSec);

        return new self([...$this->results, ...$other->results], [...$this->failures, ...$other->failures], $this->total + $other->total, $retryAfter);
    }

    /**
     * The first three failures, real ones before those caused by the time budget of the run: a run is "out of time"
     * only when no real failure is among them (SourceResult::ranOutOfTime).
     */
    public function failureSummary(): string
    {
        $outOfTime = array_filter($this->failures, static fn(string $f): bool => str_starts_with($f, FailureKind::BudgetExhausted->value . ':'));
        $real = array_diff_key($this->failures, $outOfTime);

        return implode('; ', array_slice([...$real, ...$outOfTime], 0, 3)) ?: 'no response';
    }

    /** @return list<Deficit> */
    public function deficits(): array
    {
        $deficits = [];
        if ($this->failures !== []) {
            $deficits[] = new Deficit(DeficitKind::RequestsFailed, ['failed' => count($this->failures), 'total' => $this->total]);
        }
        $matched = $this->results[0]->totalAvailable ?? null;
        $received = array_sum(array_map(
            static fn(ParseResult $r): int => $r->statistics->valid + $r->statistics->rejected + $r->statistics->skipped,
            $this->results,
        ));
        if ($matched !== null && $received < $matched) {
            $deficits[] = new Deficit(DeficitKind::PagingIncomplete, ['received' => $received, 'matched' => $matched]);
        }

        return $deficits;
    }
}
