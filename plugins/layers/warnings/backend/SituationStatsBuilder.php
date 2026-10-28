<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WarningsLayer;

use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Value\RegionId;
use CommonSight\Sdk\Layer\StatsBuilder;

/**
 * The situation level of a country from its official warnings (L-D3): each grade from orange on that occurs gives a
 * level (orange 2, red 3, violet 4), one more when warnings of at least this grade reach a quarter of the states or
 * cantons; the highest counts, yellow alone is level 1. Current and announced warnings count alike, as the layer shows
 * them.
 */
final class SituationStatsBuilder implements StatsBuilder
{
    private const WIDESPREAD_SHARE = 0.25;
    private const MAX_LEVEL = 5;

    /** @param list<RegionId> $regions the regions of the country */
    public function __construct(private readonly array $regions) {}

    public function build(array $items): SituationStats
    {
        $highest = null;
        $warnings = [];
        /** @var array<string, int> $regionGrades highest grade per region of the country */
        $regionGrades = array_fill_keys(array_map(static fn(RegionId $id): string => $id->value, $this->regions), 0);
        foreach ($items as $item) {
            if (!$item instanceof WarningItem) {
                continue;
            }
            $grade = WarningGrade::of($item);
            $highest = max($highest ?? 0, $grade->value);
            $warnings[$grade->key()] = ($warnings[$grade->key()] ?? 0) + 1;
            foreach ($item->common->regionIds as $region) {
                if (isset($regionGrades[$region->value])) {
                    $regionGrades[$region->value] = max($regionGrades[$region->value], $grade->value);
                }
            }
        }
        $regions = $this->reached($regionGrades);

        return new SituationStats($this->level($highest === null ? null : WarningGrade::from($highest), $regions), count($this->regions), $warnings, $regions);
    }

    /**
     * A region reached by a grade is reached by every lower grade too.
     *
     * @param array<string, int> $regionGrades
     * @return array<string, int> number of regions by grade, without the grades that reach none
     */
    private function reached(array $regionGrades): array
    {
        $regions = [];
        foreach (WarningGrade::cases() as $grade) {
            $reached = count(array_filter($regionGrades, static fn(int $g): bool => $g >= $grade->value));
            if ($reached > 0) {
                $regions[$grade->key()] = $reached;
            }
        }

        return $regions;
    }

    /** @param array<string, int> $regions */
    private function level(?WarningGrade $highest, array $regions): int
    {
        $level = 1;
        foreach (WarningGrade::cases() as $grade) {
            if ($grade === WarningGrade::Yellow || $highest === null || $grade->value > $highest->value) {
                continue;
            }
            $widespread = $this->regions !== [] && ($regions[$grade->key()] ?? 0) / count($this->regions) >= self::WIDESPREAD_SHARE;
            $level = max($level, $grade->value + ($widespread ? 1 : 0));
        }

        return min($level, self::MAX_LEVEL);
    }
}
