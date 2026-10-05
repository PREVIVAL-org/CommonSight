<?php

declare(strict_types=1);

namespace CommonSight\Domain\Schedule;

use CommonSight\Model\Layer\LayerTarget;

/**
 * All sources in all their scopes as the scheduler sees them, and all layers in all their scopes (concept: sources as
 * plugins, 6.1). Sources name their layer themselves; the sources of a layer are ordered by their rank, then by id.
 */
final class SourcePlans
{
    /** @var list<SourcePlan> */
    private readonly array $plans;

    /**
     * @param list<SourcePlan> $plans
     * @param list<LayerTarget> $targets every layer in every scope it has
     */
    public function __construct(array $plans, private readonly array $targets)
    {
        $keys = array_map(static fn(SourcePlan $p): string => $p->key(), $plans);
        $duplicates = array_keys(array_filter(array_count_values($keys), static fn(int $count): bool => $count > 1));
        if ($duplicates !== []) {
            throw new \InvalidArgumentException('Sources registered twice: ' . implode(', ', $duplicates));
        }
        usort($plans, static fn(SourcePlan $a, SourcePlan $b): int => [$a->source->order, $a->id(), $a->scope->value] <=> [$b->source->order, $b->id(), $b->scope->value]);
        $this->plans = $plans;
    }

    /** @return list<SourcePlan> */
    public function all(): array
    {
        return $this->plans;
    }

    /** @return list<SourcePlan> */
    public function inLane(string $lane): array
    {
        return array_values(array_filter($this->plans, static fn(SourcePlan $p): bool => $p->lane === $lane));
    }

    /** @return list<SourcePlan> the sources of a layer in a scope, by rank */
    public function of(LayerTarget $target): array
    {
        return array_values(array_filter($this->plans, static fn(SourcePlan $p): bool => $p->target() == $target));
    }

    /** @return list<LayerTarget> */
    public function targets(): array
    {
        return $this->targets;
    }

    /** @return list<LayerTarget> layers without any source in their scope (e.g. traffic CH until it gets one) */
    public function targetsWithoutSources(): array
    {
        return array_values(array_filter($this->targets, fn(LayerTarget $t): bool => $this->of($t) === []));
    }
}
