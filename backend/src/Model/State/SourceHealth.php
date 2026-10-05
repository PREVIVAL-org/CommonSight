<?php

declare(strict_types=1);

namespace CommonSight\Model\State;

use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;

/**
 * Health of a source in a scope (state/sources/<id>-<scope>.json): last attempt and success, failures in a row with
 * backoff and cause, item count, typical duration of a run, and which release wrote its last outcome (concept: sources
 * as plugins, 6).
 */
final readonly class SourceHealth implements \JsonSerializable
{
    public function __construct(
        public string $sourceId,
        public Scope $scope,
        public ?UtcInstant $lastAttemptAt = null,
        public ?UtcInstant $lastSuccessAt = null,
        public int $consecutiveFailures = 0,
        public ?UtcInstant $backoffUntil = null,
        public ?string $lastFailure = null,
        public int $itemCount = 0,
        /** moving average of the run durations in milliseconds (D8) */
        public ?int $typicalDurationMs = null,
        /** release that wrote the stored outcome; another release cannot read it, the source is due again */
        public ?string $outcomeRelease = null,
    ) {}

    public function key(): string
    {
        return $this->sourceId . '-' . $this->scope->value;
    }

    public function inBackoff(UtcInstant $now): bool
    {
        return $this->backoffUntil !== null && $this->backoffUntil->isAfter($now);
    }

    /** After a successful run: failures and backoff are cleared. */
    public function succeeded(UtcInstant $now, int $itemCount, int $durationMs, string $release): self
    {
        return new self($this->sourceId, $this->scope, $now, $now, 0, null, null, $itemCount, $this->typical($durationMs), $release);
    }

    /** After a failed run: one more failure in a row, backoff until the given time. */
    /** @param int|null $durationMs null when unknown (a crashed run): the typical run time stays */
    public function failed(UtcInstant $now, string $cause, UtcInstant $backoffUntil, ?int $durationMs, string $release): self
    {
        return new self($this->sourceId, $this->scope, $now, $this->lastSuccessAt, $this->consecutiveFailures + 1, $backoffUntil, $cause, 0, $this->typical($durationMs), $release);
    }

    /** After a run of a switched-off source: only the attempt counts, so it is looked at again after its interval. */
    public function disabled(UtcInstant $now, string $release): self
    {
        return new self($this->sourceId, $this->scope, $now, $this->lastSuccessAt, 0, null, null, 0, $this->typicalDurationMs, $release);
    }

    private function typical(?int $durationMs): ?int
    {
        if ($durationMs === null) {
            return $this->typicalDurationMs;
        }

        return $this->typicalDurationMs === null ? $durationMs : (int) round(0.7 * $this->typicalDurationMs + 0.3 * $durationMs);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'source' => $this->sourceId,
            'scope' => $this->scope->value,
            'lastAttemptAt' => $this->lastAttemptAt,
            'lastSuccessAt' => $this->lastSuccessAt,
            'consecutiveFailures' => $this->consecutiveFailures,
            'backoffUntil' => $this->backoffUntil,
            'lastFailure' => $this->lastFailure,
            'itemCount' => $this->itemCount,
            'typicalDurationMs' => $this->typicalDurationMs,
            'outcomeRelease' => $this->outcomeRelease,
        ];
    }
}
