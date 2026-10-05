<?php

declare(strict_types=1);

namespace CommonSight\Application;

/** Result of a run of sources (lane, fallback, by hand): lock busy, outcome per layer, sources skipped for lack of time. */
final readonly class LaneReport
{
    /** @param array<string, RunOutcome> $outcomes */
    public function __construct(public bool $busy, public array $outcomes, public int $skippedForBudget) {}

    public static function busy(): self
    {
        return new self(true, [], 0);
    }

    public function hasFailures(): bool
    {
        return in_array(RunOutcome::Failed, $this->outcomes, true);
    }
}
