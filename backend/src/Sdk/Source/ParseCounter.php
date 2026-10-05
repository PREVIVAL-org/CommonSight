<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

/** Counts valid, discarded and skipped records during a parser run. */
final class ParseCounter
{
    private int $valid = 0;
    private int $skipped = 0;
    /** @var array<string, int> */
    private array $rejected = [];

    public function valid(): void
    {
        $this->valid++;
    }

    public function skipped(): void
    {
        $this->skipped++;
    }

    public function rejected(string $reason): void
    {
        $this->rejected[$reason] = ($this->rejected[$reason] ?? 0) + 1;
    }

    public function statistics(): ParseStatistics
    {
        return new ParseStatistics($this->valid, array_sum($this->rejected), $this->skipped, $this->rejected);
    }
}
