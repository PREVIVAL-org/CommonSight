<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WarningsLayer;

use CommonSight\Model\Stats\LayerStats;

/** Situation level of a country from 1 (nothing) to 5 (massive situation) with what it is based on (L-D3). */
final readonly class SituationStats implements LayerStats
{
    /**
     * @param array<string, int> $warnings number of warnings by grade
     * @param array<string, int> $regions number of regions with a warning of at least this grade, by grade
     */
    public function __construct(public int $level, public int $regionCount, public array $warnings, public array $regions) {}

    /** @return array{situation: array<string, mixed>} */
    public function jsonSerialize(): array
    {
        $grades = [];
        foreach (WarningGrade::cases() as $grade) {
            $grades[$grade->key()] = ['warnings' => $this->warnings[$grade->key()] ?? 0, 'regions' => $this->regions[$grade->key()] ?? 0];
        }

        return ['situation' => ['level' => $this->level, 'regionCount' => $this->regionCount, 'grades' => $grades]];
    }
}
