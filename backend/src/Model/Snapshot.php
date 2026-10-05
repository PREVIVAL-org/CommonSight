<?php

declare(strict_types=1);

namespace CommonSight\Model;

use CommonSight\Model\Item\Item;
use CommonSight\Model\Stats\LayerStats;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;

/** State of a layer for a scope, as delivered as a file (D-01). */
final readonly class Snapshot implements \JsonSerializable
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param list<Msg> $issues
     * @param list<Item> $items
     * @param list<Msg> $coverage what its sources cover, below the note (L-D7)
     */
    public function __construct(
        public LayerId $layer,
        public Scope $scope,
        public FeedStatus $status,
        public string $source,
        public string $sourceUrl,
        public UtcInstant $generatedAt,
        public ?UtcInstant $updatedAt,
        public Msg $note,
        public array $issues,
        public LayerStats $stats,
        public array $items,
        public array $coverage = [],
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'schema' => self::SCHEMA_VERSION,
            'layer' => $this->layer->value,
            'scope' => $this->scope->value,
            'status' => $this->status->value,
            'source' => $this->source,
            'sourceUrl' => $this->sourceUrl,
            'generatedAt' => $this->generatedAt,
            'updatedAt' => $this->updatedAt,
            'note' => $this->note,
            'coverage' => $this->coverage,
            'issues' => $this->issues,
            'stats' => $this->stats,
            'items' => $this->items,
        ];
    }
}
