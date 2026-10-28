<?php

declare(strict_types=1);

namespace CommonSight\Domain\Status;

use CommonSight\Domain\Schedule\StalenessPolicy;
use CommonSight\Model\Decoded;
use CommonSight\Model\FeedStatus;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\Value\UtcInstant;

/**
 * Assembles the response of the situation endpoint: per country the situation level from the key figures of its layer
 * of official warnings (concept: situation level, L-D4). Without a current level, the level is null ("unknown"), never
 * 1: a layer that failed, is stale or has no snapshot yet is no all-clear.
 */
final class SituationView
{
    public function __construct(private readonly StalenessPolicy $staleness) {}

    /**
     * @param Decoded|null $situation stats.situation of the current snapshot
     * @return array<string, mixed>
     */
    public function country(?LayerState $state, ?Decoded $situation, UtcInstant $now): array
    {
        $stale = $this->staleness->isStale($state, $now);
        $status = $state !== null && $state->hasSnapshot() ? $state->status : null;
        $current = !$stale && ($status === FeedStatus::Ok || $status === FeedStatus::Partial) ? $situation : null;
        $level = $current?->get('level')->int();

        return [
            'level' => $level,
            'status' => $status->value ?? 'pending',
            'stale' => $stale,
            'generatedAt' => $state?->generatedAt?->toIso(),
            'checkedAt' => $state?->checkedAt?->toIso(),
            'regionCount' => $level === null ? null : $current?->get('regionCount')->int(),
            // As an object also when empty: an empty PHP array would go out as a JSON list.
            'grades' => $level === null ? null : (object) ($current?->get('grades')->array() ?? []),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $countries by country code
     * @return array<string, mixed>
     */
    public function response(array $countries, UtcInstant $now): array
    {
        return ['schema' => 1, 'serverTime' => $now->toIso(), 'countries' => $countries];
    }
}
