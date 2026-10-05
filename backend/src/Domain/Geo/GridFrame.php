<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

/** Dimensions of a grid: origin, cell size, columns and rows; converts between cells and coordinates. */
final readonly class GridFrame
{
    public function __construct(
        public float $west,
        public float $south,
        public float $cellSize,
        public int $cols,
        public int $rows,
    ) {}

    /** @return array{int, int} column and row, also outside the grid */
    public function columnRow(float $lon, float $lat): array
    {
        return [(int) floor(($lon - $this->west) / $this->cellSize), (int) floor(($lat - $this->south) / $this->cellSize)];
    }

    public function contains(int $col, int $row): bool
    {
        return $col >= 0 && $row >= 0 && $col < $this->cols && $row < $this->rows;
    }

    public function index(int $col, int $row): int
    {
        return $row * $this->cols + $col;
    }

    /** @return array{float, float} center [lon, lat] */
    public function center(int $cell): array
    {
        $col = $cell % $this->cols;
        $row = intdiv($cell, $this->cols);

        return [$this->west + ($col + 0.5) * $this->cellSize, $this->south + ($row + 0.5) * $this->cellSize];
    }

    /** @return list<int> neighbors in the four cardinal directions */
    public function neighbours(int $cell): array
    {
        $col = $cell % $this->cols;
        $row = intdiv($cell, $this->cols);
        $result = [];
        foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dc, $dr]) {
            if ($this->contains($col + $dc, $row + $dr)) {
                $result[] = $this->index($col + $dc, $row + $dr);
            }
        }

        return $result;
    }
}
