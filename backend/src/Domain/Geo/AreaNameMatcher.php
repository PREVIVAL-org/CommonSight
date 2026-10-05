<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

use CommonSight\Model\Place\Region;

/** Finds regions whose name or alias is mentioned in the area description of a warning (U-14 step 4). */
final class AreaNameMatcher
{
    /**
     * @param list<Region> $regions
     * @return list<Region>
     */
    public function match(?string $area, array $regions): array
    {
        if ($area === null || trim($area) === '') {
            return [];
        }
        $parts = array_map($this->normalize(...), preg_split('/\s*[,;\/]\s*|\s+(?:und|and|et|e)\s+/u', $area) ?: []);
        $wanted = array_flip(array_filter($parts, static fn(string $p): bool => $p !== ''));

        return array_values(array_filter(
            $regions,
            fn(Region $region): bool => array_intersect_key($wanted, array_flip(array_map($this->normalize(...), $region->names()))) !== [],
        ));
    }

    private function normalize(string $name): string
    {
        $lower = mb_strtolower(trim($name));

        return trim((string) preg_replace('/^(kanton|canton( of)?|cantone|bundesland|land)\s+/u', '', $lower));
    }
}
