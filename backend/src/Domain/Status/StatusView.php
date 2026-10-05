<?php

declare(strict_types=1);

namespace CommonSight\Domain\Status;

use CommonSight\Domain\Schedule\StalenessPolicy;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;

/**
 * Assembles the status endpoint response per schema from the state files of a country, the global layers and the
 * layers of the border zone (Architecture 6.2).
 */
final class StatusView
{
    /** Interval reported for a layer before its first assembly, when its sources are not known yet. */
    private const UNKNOWN_INTERVAL_SEC = 300;

    /** @param float $vicinityKm the vicinity of the installation, for the texts of the frontend ("Grenzgebiet bis … km") */
    public function __construct(
        private readonly StalenessPolicy $staleness,
        private readonly string $dataUrlPrefix = 'data/v1/',
        private readonly float $vicinityKm = 200.0,
    ) {}

    /**
     * @param list<array{LayerTarget, ?LayerState}> $layers
     * @param list<array{LayerTarget, ?LayerState}> $border layers of the border zone; the section is left out without any
     * @return array{layers: array<string, array<string, mixed>>, border?: array<string, array<string, mixed>>}
     */
    public function layers(array $layers, UtcInstant $now, array $border = []): array
    {
        $result = ['layers' => $this->section($layers, $now)];
        if ($border !== []) {
            $result['border'] = $this->section($border, $now);
        }

        return $result;
    }

    /**
     * @param list<array{LayerTarget, ?LayerState}> $layers
     * @return array<string, array<string, mixed>> by layer
     */
    private function section(array $layers, UtcInstant $now): array
    {
        $result = [];
        foreach ($layers as [$target, $state]) {
            $result[$target->layer->value] = $this->layer($target, $state ?? LayerState::pending($target->layer, $target->scope), $now);
        }

        return $result;
    }

    /**
     * @param array{layers: array<string, array<string, mixed>>, border?: array<string, array<string, mixed>>} $layers
     * @return array<string, mixed>
     */
    public function response(Scope $scope, array $layers, UtcInstant $now): array
    {
        return ['schema' => 1, 'serverTime' => $now->toIso(), 'scope' => $scope->value, 'vicinityKm' => $this->vicinityKm] + $layers;
    }

    /** @return array<string, mixed> */
    private function layer(LayerTarget $target, LayerState $state, UtcInstant $now): array
    {
        $hasSnapshot = $state->hasSnapshot();

        return [
            'scope' => $target->scope->value,
            'status' => $hasSnapshot && $state->status !== null ? $state->status->value : 'pending',
            'version' => $hasSnapshot ? $state->version : null,
            'url' => $hasSnapshot ? $this->dataUrlPrefix . $state->file : null,
            'checkedAt' => $state->checkedAt?->toIso(),
            'generatedAt' => $state->generatedAt?->toIso(),
            'sourceUpdatedAt' => $state->sourceUpdatedAt?->toIso(),
            'itemCount' => $state->itemCount,
            'issues' => array_map(static fn($issue): array => $issue->jsonSerialize(), $state->issues),
            'stale' => $this->staleness->isStale($state, $now),
            'intervalSec' => $state->intervalSec ?? self::UNKNOWN_INTERVAL_SEC,
            'lastError' => $state->lastError === null ? null : [
                'at' => $state->lastError->at->toIso(),
                'message' => $state->lastError->message->jsonSerialize(),
            ],
        ];
    }
}
