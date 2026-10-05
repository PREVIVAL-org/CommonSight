<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

/** Chooses the records whose detail is missing, at most N per run (Q-W-AT-10, Q-W-DE-05). */
final class DetailPlan
{
    public function __construct(private readonly int $maxRequestsPerRun) {}

    /**
     * @param list<object> $records
     * @param array<string, mixed> $cachedKeys existing cache entries
     * @return list<object>
     */
    public function missing(DetailSource $source, array $records, array $cachedKeys): array
    {
        $missing = [];
        foreach ($records as $record) {
            if (count($missing) >= $this->maxRequestsPerRun) {
                break;
            }
            $key = $source->cacheKey($record);
            if ($key !== null && !array_key_exists($key, $cachedKeys) && $source->request($record) !== null) {
                $missing[] = $record;
            }
        }

        return $missing;
    }
}
