<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

/** Determines all grid cells a line segment passes through (method by Amanatides and Woo). */
final class GridTraversal
{
    /**
     * @param array{float, float} $from [lon, lat]
     * @param array{float, float} $to [lon, lat]
     * @return list<int>
     */
    public function cells(GridFrame $frame, array $from, array $to): array
    {
        [$col, $row] = $frame->columnRow($from[0], $from[1]);
        [$endCol, $endRow] = $frame->columnRow($to[0], $to[1]);
        $dx = $to[0] - $from[0];
        $dy = $to[1] - $from[1];
        $stepCol = $dx > 0 ? 1 : -1;
        $stepRow = $dy > 0 ? 1 : -1;
        $tMaxX = $this->firstBoundary($from[0], $frame->west, $frame->cellSize, $col, $dx);
        $tMaxY = $this->firstBoundary($from[1], $frame->south, $frame->cellSize, $row, $dy);
        $tDeltaX = $dx === 0.0 ? INF : $frame->cellSize / abs($dx);
        $tDeltaY = $dy === 0.0 ? INF : $frame->cellSize / abs($dy);

        $cells = [];
        $limit = abs($endCol - $col) + abs($endRow - $row) + 1;
        for ($step = 0; $step < $limit; $step++) {
            if ($frame->contains($col, $row)) {
                $cells[] = $frame->index($col, $row);
            }
            if ($tMaxX < $tMaxY) {
                $tMaxX += $tDeltaX;
                $col += $stepCol;
            } else {
                $tMaxY += $tDeltaY;
                $row += $stepRow;
            }
        }

        return $cells;
    }

    /** Fraction of the segment up to the first cell boundary along one axis. */
    private function firstBoundary(float $start, float $origin, float $size, int $index, float $delta): float
    {
        if ($delta === 0.0) {
            return INF;
        }
        $boundary = $origin + ($delta > 0 ? $index + 1 : $index) * $size;

        return ($boundary - $start) / $delta;
    }
}
