<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

use CommonSight\Model\Place\Region;

/**
 * Generates the grid for a set of regions: boundary cells via the edges, all remaining cells
 * connected per area with a single point-in-polygon check.
 */
final class RegionGridBuilder
{
    public function __construct(
        private readonly PointInPolygon $pointInPolygon,
        private readonly GridTraversal $traversal,
    ) {}

    /** @param non-empty-list<Region> $regions */
    public function build(array $regions, float $cellSize): RegionGrid
    {
        $frame = $this->frame($regions, $cellSize);
        $edges = $this->boundaryCells($regions, $frame);
        $values = $this->fillAreas($regions, $frame, $edges);
        $candidates = $this->candidates($frame, $edges, $values);

        $cells = '';
        for ($cell = 0, $total = $frame->cols * $frame->rows; $cell < $total; $cell++) {
            $cells .= sprintf('%02x', $values[$cell] ?? RegionGrid::BOUNDARY);
        }

        return new RegionGrid($frame->west, $frame->south, $cellSize, $frame->cols, $frame->rows, $cells, $candidates);
    }

    /** @param non-empty-list<Region> $regions */
    private function frame(array $regions, float $cellSize): GridFrame
    {
        $west = min(array_map(static fn(Region $r): float => $r->bbox->west, $regions)) - $cellSize;
        $south = min(array_map(static fn(Region $r): float => $r->bbox->south, $regions)) - $cellSize;
        $east = max(array_map(static fn(Region $r): float => $r->bbox->east, $regions));
        $north = max(array_map(static fn(Region $r): float => $r->bbox->north, $regions));

        return new GridFrame($west, $south, $cellSize, (int) ceil(($east - $west) / $cellSize) + 1, (int) ceil(($north - $south) / $cellSize) + 1);
    }

    /**
     * @param list<Region> $regions
     * @return array<int, array<int, true>> boundary cell -> regions whose edges intersect it
     */
    private function boundaryCells(array $regions, GridFrame $frame): array
    {
        $edges = [];
        foreach ($regions as $index => $region) {
            foreach ($region->polygons as $rings) {
                foreach ($rings as $ring) {
                    foreach ($this->ringCells($frame, $ring) as $cell) {
                        $edges[$cell][$index] = true;
                    }
                }
            }
        }

        return $edges;
    }

    /**
     * @param list<array{float, float}> $ring
     * @return list<int> cells the edges of the ring pass through
     */
    private function ringCells(GridFrame $frame, array $ring): array
    {
        $cells = [];
        for ($i = 1, $count = count($ring); $i < $count; $i++) {
            array_push($cells, ...$this->traversal->cells($frame, $ring[$i - 1], $ring[$i]));
        }

        return $cells;
    }

    /**
     * @param list<Region> $regions
     * @param array<int, array<int, true>> $edges
     * @return array<int, int> cell -> region index or OUTSIDE (boundary cells missing)
     */
    private function fillAreas(array $regions, GridFrame $frame, array $edges): array
    {
        $values = [];
        for ($start = 0, $total = $frame->cols * $frame->rows; $start < $total; $start++) {
            if (isset($edges[$start]) || isset($values[$start])) {
                continue;
            }
            $value = $this->regionAtCenter($regions, $frame, $start);
            foreach ($this->component($frame, $edges, $start) as $cell) {
                $values[$cell] = $value;
            }
        }

        return $values;
    }

    /**
     * @param array<int, array<int, true>> $edges
     * @return list<int> connected cells without an edge, starting at $start
     */
    private function component(GridFrame $frame, array $edges, int $start): array
    {
        $seen = [$start => true];
        $queue = [$start];
        for ($head = 0; $head < count($queue); $head++) {
            foreach ($frame->neighbours($queue[$head]) as $next) {
                if (!isset($seen[$next]) && !isset($edges[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                }
            }
        }

        return $queue;
    }

    /** @param list<Region> $regions */
    private function regionAtCenter(array $regions, GridFrame $frame, int $cell): int
    {
        [$lon, $lat] = $frame->center($cell);
        foreach ($regions as $index => $region) {
            if ($region->bbox->contains($lon, $lat) && $this->pointInPolygon->inAny($region->polygons, $lon, $lat)) {
                return $index;
            }
        }

        return RegionGrid::OUTSIDE;
    }

    /**
     * @param array<int, array<int, true>> $edges
     * @param array<int, int> $values
     * @return array<int, list<int>>
     */
    private function candidates(GridFrame $frame, array $edges, array $values): array
    {
        $candidates = [];
        foreach ($edges as $cell => $regionIndexes) {
            $set = $regionIndexes;
            foreach ($frame->neighbours($cell) as $next) {
                $neighbour = $values[$next] ?? RegionGrid::OUTSIDE;
                if ($neighbour !== RegionGrid::OUTSIDE) {
                    $set[$neighbour] = true;
                }
            }
            $list = array_keys($set);
            sort($list);
            $candidates[$cell] = $list;
        }
        ksort($candidates);

        return $candidates;
    }
}
