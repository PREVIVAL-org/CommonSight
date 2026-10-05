<?php

declare(strict_types=1);

namespace CommonSight\Model\Place;

use CommonSight\Model\Value\RegionId;

/**
 * Maps stable official codes to regions (concept: sources as plugins, V2), so that no plugin keeps its own tables:
 *
 * - iso: ISO 3166-2, the region id itself (DE-BY, AT-7, CH-ZH)
 * - ags: German municipality key (Amtlicher Gemeindeschlüssel) or its first two digits, the state key
 * - gkz: Austrian municipality code (Gemeindekennzahl) or its first digit, the state digit
 * - bfs: Swiss canton number of the BFS (1 to 26); municipality numbers of the BFS do not tell the canton
 *
 * Municipality codes change with mergers and reforms and are therefore never stored; only their stable prefix is read.
 */
final readonly class RegionCodes
{
    /**
     * @param array<string, array<string, RegionId>> $byScheme scheme -> code -> region
     * @param array<string, string> $names region id -> name
     */
    public function __construct(private array $byScheme, private array $names = []) {}

    /** Name of a region, e.g. "Niederösterreich" for AT-3. */
    public function nameOf(RegionId $region): ?string
    {
        return $this->names[$region->value] ?? null;
    }

    /** The region of a code, null if the code is unknown (the caller then assigns by geometry). */
    public function regionFor(string $scheme, string $code): ?RegionId
    {
        $code = trim($code);
        $key = match ($scheme) {
            'iso' => strtoupper($code),
            'ags' => preg_match('/^\d{2}/', $code) === 1 ? substr($code, 0, 2) : null,
            'gkz' => preg_match('/^\d/', $code) === 1 ? $code[0] : null,
            'bfs' => ctype_digit($code) ? (string) (int) $code : null,
            default => throw new \InvalidArgumentException('Unknown code scheme: ' . $scheme),
        };

        return $key === null ? null : ($this->byScheme[$scheme][$key] ?? null);
    }
}
