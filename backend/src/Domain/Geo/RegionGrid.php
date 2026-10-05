<?php

declare(strict_types=1);

namespace CommonSight\Domain\Geo;

/**
 * Grid over the regions of a country: per cell exactly one region, "outside" or "boundary cell" with candidates.
 *
 * Generated at build time from the region areas (RegionGridBuilder); makes assigning many points fast.
 */
final readonly class RegionGrid
{
    public const OUTSIDE = 254;
    public const BOUNDARY = 255;

    /**
     * @param string $cells two hex characters per cell: index of the region, OUTSIDE or BOUNDARY
     * @param array<int, list<int>> $candidates boundary cell -> indices of the regions that may lie there
     */
    public function __construct(
        public float $west,
        public float $south,
        public float $cellSize,
        public int $cols,
        public int $rows,
        public string $cells,
        public array $candidates,
    ) {
        if (strlen($cells) !== 2 * $cols * $rows) {
            throw new \InvalidArgumentException('Grid does not match its dimensions');
        }
    }

    /** @return int|null cell index, or null outside the grid */
    public function cellAt(float $lon, float $lat): ?int
    {
        $col = (int) floor(($lon - $this->west) / $this->cellSize);
        $row = (int) floor(($lat - $this->south) / $this->cellSize);
        if ($col < 0 || $row < 0 || $col >= $this->cols || $row >= $this->rows) {
            return null;
        }

        return $row * $this->cols + $col;
    }

    public function valueOf(int $cell): int
    {
        return (int) hexdec(substr($this->cells, 2 * $cell, 2));
    }

    /** @return list<int> */
    public function candidatesOf(int $cell): array
    {
        return $this->candidates[$cell] ?? [];
    }

    /** @param array{west: float, south: float, cellSize: float, cols: int, rows: int, cells: string, candidates: array<int, list<int>>} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['west'], $data['south'], $data['cellSize'], $data['cols'], $data['rows'], $data['cells'], $data['candidates']);
    }

    /** @return array{west: float, south: float, cellSize: float, cols: int, rows: int, cells: string, candidates: array<int, list<int>>} */
    public function toArray(): array
    {
        return [
            'west' => $this->west, 'south' => $this->south, 'cellSize' => $this->cellSize,
            'cols' => $this->cols, 'rows' => $this->rows, 'cells' => $this->cells, 'candidates' => $this->candidates,
        ];
    }
}
