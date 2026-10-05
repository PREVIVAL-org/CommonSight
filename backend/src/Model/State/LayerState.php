<?php

declare(strict_types=1);

namespace CommonSight\Model\State;

use CommonSight\Model\FeedStatus;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;

/**
 * Operational state of a layer: current version, last assembly, errors, and from its sources the shortest interval and
 * the time from which it counts as stale (Architecture 4.7; concept: sources as plugins, V1).
 */
final readonly class LayerState implements \JsonSerializable
{
    /** @param list<Msg> $issues */
    public function __construct(
        public LayerId $layer,
        public Scope $scope,
        public ?string $version,
        public ?string $file,
        public ?FeedStatus $status,
        public ?UtcInstant $generatedAt,
        public ?UtcInstant $checkedAt,
        public ?UtcInstant $sourceUpdatedAt,
        public int $itemCount,
        public array $issues,
        public ?LastError $lastError,
        public int $consecutiveFailures,
        public ?UtcInstant $backoffUntil,
        /** shortest interval of the active sources; null before the first assembly or without sources */
        public ?int $intervalSec = null,
        /** stale from this time on: an active source has not succeeded for staleFactor × its interval; null = never */
        public ?UtcInstant $staleAfter = null,
    ) {}

    /** State of a layer for which there is no snapshot yet (A-04). */
    public static function pending(LayerId $layer, Scope $scope): self
    {
        return new self($layer, $scope, null, null, null, null, null, null, 0, [], null, 0, null);
    }

    public function hasSnapshot(): bool
    {
        return $this->version !== null && $this->file !== null;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'layer' => $this->layer->value,
            'scope' => $this->scope->value,
            'version' => $this->version,
            'file' => $this->file,
            'status' => $this->status?->value,
            'generatedAt' => $this->generatedAt,
            'checkedAt' => $this->checkedAt,
            'sourceUpdatedAt' => $this->sourceUpdatedAt,
            'itemCount' => $this->itemCount,
            'issues' => $this->issues,
            'lastError' => $this->lastError,
            'consecutiveFailures' => $this->consecutiveFailures,
            'backoffUntil' => $this->backoffUntil,
            'intervalSec' => $this->intervalSec,
            'staleAfter' => $this->staleAfter,
        ];
    }
}
