<?php

declare(strict_types=1);

namespace CommonSight\Config;

/**
 * The cron lanes (concept: sources as plugins, 6.1, D7): time budget per run and per fallback in the status endpoint;
 * a lane without fallback budget is never refreshed by the fallback. One cron line per lane runs its due sources.
 */
final readonly class Lanes
{
    /** everySec: how often the cron line of the lane runs (install/crontab.example); null = not known */
    public const DEFAULTS = [
        'fast' => ['budgetSec' => 50, 'fallbackSec' => 60, 'everySec' => 60],
        'heavy' => ['budgetSec' => 240, 'fallbackSec' => 120, 'everySec' => 300],
        'slow' => ['budgetSec' => 600, 'fallbackSec' => null, 'everySec' => 1800],
    ];

    /** @param array<string, array{budgetSec: int, fallbackSec: ?int, everySec: ?int}> $lanes */
    public function __construct(public array $lanes = self::DEFAULTS) {}

    public function has(string $lane): bool
    {
        return isset($this->lanes[$lane]);
    }

    public function budget(string $lane): int
    {
        return ($this->lanes[$lane] ?? throw new ConfigError('Unknown lane: ' . $lane))['budgetSec'];
    }

    /** @return array<string, int|null> fallback budget per lane, null = no fallback */
    public function fallbackSeconds(): array
    {
        return array_map(static fn(array $lane): ?int => $lane['fallbackSec'], $this->lanes);
    }

    /** The longest fallback budget of all lanes: the time limit of a status request that runs the fallback. */
    public function maxFallbackSec(): int
    {
        return (int) max([0, ...array_values(array_filter($this->fallbackSeconds(), static fn(?int $s): bool => $s !== null))]);
    }

    /**
     * How often each lane runs: a source cannot be fresher than its lane comes around, whatever its interval.
     *
     * @return array<string, int|null> seconds per lane, null = not known
     */
    public function cadences(): array
    {
        return array_map(static fn(array $lane): ?int => $lane['everySec'], $this->lanes);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->lanes);
    }
}
