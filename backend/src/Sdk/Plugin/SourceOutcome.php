<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\Deficit;
use CommonSight\Sdk\Source\ParseStatistics;

/**
 * Result of one run of a source: items in the internal model with the numbers the core judges them by, or a failure
 * with its cause and the waiting time the provider asked for; in both cases what the run noticed for the log.
 */
final readonly class SourceOutcome
{
    /**
     * @param list<Item> $items
     * @param list<Deficit> $deficits
     * @param list<Diagnostic> $diagnostics
     */
    private function __construct(
        public array $items,
        public ?ParseStatistics $statistics,
        public ?UtcInstant $sourceUpdatedAt,
        public array $deficits,
        public ?string $failure,
        public ?int $retryAfterSec,
        public array $diagnostics = [],
        /** what the source covers in this scope, shown below the note of its layer (L-D7), e.g. "16 ausgewählte Orte" */
        public ?Msg $coverage = null,
    ) {}

    /**
     * @param list<Item> $items
     * @param list<Deficit> $deficits deficits the source detected itself (e.g. warnings without geometry)
     */
    public static function success(array $items, ParseStatistics $statistics, ?UtcInstant $sourceUpdatedAt = null, array $deficits = []): self
    {
        foreach ($items as $item) {
            if (!$item instanceof Item) {
                throw new \InvalidArgumentException('A source must deliver items of the internal model');
            }
        }

        return new self($items, $statistics, $sourceUpdatedAt, $deficits, null, null);
    }

    public static function failure(string $cause, ?int $retryAfterSec = null): self
    {
        if (trim($cause) === '' || ($retryAfterSec !== null && $retryAfterSec < 0)) {
            throw new \InvalidArgumentException('A failure needs a cause and a waiting time that is not negative');
        }

        return new self([], null, null, [], $cause, $retryAfterSec);
    }

    /** @param list<Diagnostic> $diagnostics */
    public function withDiagnostics(array $diagnostics): self
    {
        return new self($this->items, $this->statistics, $this->sourceUpdatedAt, $this->deficits, $this->failure, $this->retryAfterSec, [...$this->diagnostics, ...$diagnostics], $this->coverage);
    }

    public function withCoverage(Msg $coverage): self
    {
        return new self($this->items, $this->statistics, $this->sourceUpdatedAt, $this->deficits, $this->failure, $this->retryAfterSec, $this->diagnostics, $coverage);
    }

    public function succeeded(): bool
    {
        return $this->failure === null;
    }
}
