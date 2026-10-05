<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Station;

use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Source\ParseCounter;

/**
 * Keeps the newest value per station of a response with several values per station (time series, several
 * instruments); every value left behind counts as skipped.
 *
 * @template T
 */
final class NewestPerStation
{
    /** @var array<string, array{UtcInstant, T}> */
    private array $newest = [];

    public function __construct(private readonly ParseCounter $counter) {}

    /** @param T $value */
    public function offer(string $station, UtcInstant $time, mixed $value): void
    {
        $current = $this->newest[$station] ?? null;
        if ($current !== null) {
            $this->counter->skipped();
            if (!$time->isAfter($current[0])) {
                return;
            }
        }
        $this->newest[$station] = [$time, $value];
    }

    /** @return array<string, array{UtcInstant, T}> */
    public function all(): array
    {
        return $this->newest;
    }
}
