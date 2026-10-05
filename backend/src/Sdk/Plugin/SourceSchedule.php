<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

/**
 * When a source is fetched: its update rate (F-19), the shortest interval its terms of use allow, the cron lane and an
 * own interval; the effective interval is never below the update rate or the terms.
 */
final readonly class SourceSchedule
{
    public const DEFAULT_LANE = 'heavy';

    public function __construct(
        /** how often the source itself has new data */
        public int $updateRateSec,
        /** shortest fetch interval the provider's terms of use allow; null = no limit */
        public ?int $termsMinIntervalSec = null,
        /** cron lane; the core checks that it exists */
        public string $lane = self::DEFAULT_LANE,
        /** own fetch interval; null = the update rate */
        public ?int $intervalSec = null,
    ) {
        if ($updateRateSec < 1 || ($termsMinIntervalSec !== null && $termsMinIntervalSec < 1) || ($intervalSec !== null && $intervalSec < 1)) {
            throw new \InvalidArgumentException('Intervals must be positive');
        }
        if (preg_match('/^[a-z][a-z0-9-]{0,30}$/', $lane) !== 1) {
            throw new \InvalidArgumentException('Invalid lane name: ' . $lane);
        }
    }

    /** The interval the core uses: the own interval, but never shorter than the update rate or the terms of use. */
    public function effectiveIntervalSec(): int
    {
        return max($this->intervalSec ?? $this->updateRateSec, $this->updateRateSec, $this->termsMinIntervalSec ?? 0);
    }
}
