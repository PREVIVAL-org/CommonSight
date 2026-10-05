<?php

declare(strict_types=1);

namespace CommonSight\Domain\Source;

use CommonSight\Model\Http\FailureKind;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Source\Deficit;
use CommonSight\Sdk\Source\ParseStatistics;
use CommonSight\Sdk\Source\SourceExpectations;

/** Result of fetching a source as the assembly of its layer sees it: items and deficits, failed or disabled. */
final readonly class SourceResult
{
    /**
     * @param list<Item> $items
     * @param list<Deficit> $deficits
     */
    private function __construct(
        public string $sourceId,
        public string $sourceName,
        public SourceExpectations $expectations,
        public SourceState $outcome,
        public array $items,
        public array $deficits,
        public ?ParseStatistics $statistics,
        public ?UtcInstant $sourceUpdatedAt,
        public ?string $failureReason,
        /** seconds the provider asked to wait (Retry-After) */
        public ?int $retryAfterSec = null,
        /** what the source covers in this scope (L-D7) */
        public ?Msg $coverage = null,
    ) {}

    /**
     * @param list<Item> $items
     * @param list<Deficit> $deficits
     */
    public static function success(SourceDescription $source, array $items, array $deficits, ParseStatistics $statistics, ?UtcInstant $sourceUpdatedAt, ?Msg $coverage = null): self
    {
        return new self($source->id, $source->name, $source->expectations, SourceState::Succeeded, $items, $deficits, $statistics, $sourceUpdatedAt, null, null, $coverage);
    }

    /**
     * Failed only because the time budget of the process was used up (every failed request says so): not the source's
     * fault, so it neither replaces the last outcome nor counts as a failure.
     */
    public function ranOutOfTime(): bool
    {
        if ($this->outcome !== SourceState::Failed || $this->failureReason === null) {
            return false;
        }
        foreach (explode('; ', $this->failureReason) as $part) {
            if (!str_starts_with($part, FailureKind::BudgetExhausted->value . ':')) {
                return false;
            }
        }

        return true;
    }

    public static function failure(SourceDescription $source, string $reason, ?int $retryAfterSec = null): self
    {
        return new self($source->id, $source->name, $source->expectations, SourceState::Failed, [], [], null, null, $reason, $retryAfterSec);
    }

    public static function unconfigured(SourceDescription $source): self
    {
        return new self($source->id, $source->name, $source->expectations, SourceState::Unconfigured, [], [], null, null, null);
    }

    public static function pending(SourceDescription $source): self
    {
        return new self($source->id, $source->name, $source->expectations, SourceState::Pending, [], [], null, null, null);
    }

    public static function disabled(SourceDescription $source): self
    {
        return new self($source->id, $source->name, $source->expectations, SourceState::Disabled, [], [], null, null, null);
    }
}
