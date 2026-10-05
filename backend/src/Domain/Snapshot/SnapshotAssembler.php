<?php

declare(strict_types=1);

namespace CommonSight\Domain\Snapshot;

use CommonSight\Domain\Source\SourceResult;
use CommonSight\Domain\Source\SourceState;
use CommonSight\Model\FeedStatus;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Msg;
use CommonSight\Model\Snapshot;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Layer\LayerDefinition;

/** Assembles the results of a layer's sources into a snapshot and decides between ok, partial, error and setup (Q-01, Q-02, F-17). */
final class SnapshotAssembler
{
    public function __construct(private readonly IssueCollector $issues) {}

    /** @param list<SourceResult> $results one per source of the layer; none = data access not set up (setup) */
    public function assemble(LayerDefinition $layer, array $results, UtcInstant $now): Assembly
    {
        $usable = array_values(array_filter($results, fn(SourceResult $r): bool => $this->isUsable($r)));
        $issues = $this->issues->collect($results);
        $status = $this->status($results, $usable, $issues);
        if ($status === FeedStatus::Error) {
            return Assembly::failed($this->failureMessage($results));
        }
        $items = $this->process($layer, $usable, $now);

        return Assembly::assembled(new Snapshot(
            $layer->layer,
            $layer->scope,
            $status,
            $layer->sourceName,
            $layer->sourceUrl,
            $now,
            $this->updatedAt($usable, $items),
            $layer->note,
            $issues,
            $layer->stats->build($items),
            $items,
            array_values(array_filter(array_map(static fn(SourceResult $r): ?Msg => $r->coverage, $usable))),
        ));
    }

    private function isUsable(SourceResult $result): bool
    {
        if ($result->outcome !== SourceState::Succeeded) {
            return false;
        }
        return !$result->expectations->emptyIsFailure || $result->items !== [];
    }

    /**
     * @param list<SourceResult> $results
     * @param list<SourceResult> $usable
     * @param list<Msg> $issues
     */
    private function status(array $results, array $usable, array $issues): FeedStatus
    {
        $enabled = array_filter($results, static fn(SourceResult $r): bool => $r->outcome !== SourceState::Disabled && $r->outcome !== SourceState::Unconfigured);
        if ($enabled === []) {
            return FeedStatus::Setup;
        }
        if ($usable === []) {
            return FeedStatus::Error;
        }

        return $issues === [] ? FeedStatus::Ok : FeedStatus::Partial;
    }

    /**
     * @param list<SourceResult> $usable
     * @return list<Item>
     */
    private function process(LayerDefinition $layer, array $usable, UtcInstant $now): array
    {
        $items = [];
        foreach ($usable as $result) {
            array_push($items, ...$result->items);
        }
        foreach ($layer->steps as $step) {
            $items = $step->apply($items, $now);
        }

        return $items;
    }

    /**
     * @param list<SourceResult> $usable
     * @param list<Item> $items
     */
    private function updatedAt(array $usable, array $items): ?UtcInstant
    {
        $times = array_map(static fn(SourceResult $r): ?UtcInstant => $r->sourceUpdatedAt, $usable);
        foreach ($items as $item) {
            $times[] = $item->common()->time;
        }

        return UtcInstant::latest($times);
    }

    /** @param list<SourceResult> $results */
    private function failureMessage(array $results): Msg
    {
        foreach ($results as $result) {
            if ($result->outcome === SourceState::Succeeded) {
                return new Msg('error.noUsableItems');
            }
        }

        return new Msg('error.allSourcesFailed');
    }
}
