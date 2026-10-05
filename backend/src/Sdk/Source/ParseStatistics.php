<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

/**
 * Number of valid, discarded and deliberately skipped records of a parser run (F-18).
 *
 * Discarded = invalid (format error, required field missing). Skipped = valid, but not relevant
 * (expired, cancelled, warning level 0, other time series).
 */
final readonly class ParseStatistics
{
    /** @param array<string, int> $rejectedByReason */
    public function __construct(
        public int $valid,
        public int $rejected,
        public int $skipped,
        public array $rejectedByReason = [],
    ) {}

    public function total(): int
    {
        return $this->valid + $this->rejected;
    }

    /** @param list<self> $parts */
    public static function sum(array $parts): self
    {
        $reasons = [];
        foreach ($parts as $part) {
            foreach ($part->rejectedByReason as $reason => $count) {
                $reasons[$reason] = ($reasons[$reason] ?? 0) + $count;
            }
        }

        return new self(
            array_sum(array_map(static fn(self $p): int => $p->valid, $parts)),
            array_sum(array_map(static fn(self $p): int => $p->rejected, $parts)),
            array_sum(array_map(static fn(self $p): int => $p->skipped, $parts)),
            $reasons,
        );
    }
}
