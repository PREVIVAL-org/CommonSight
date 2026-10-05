<?php

declare(strict_types=1);

namespace CommonSight\Model\Place;

/** Axis-aligned rectangle in WGS84 for the quick pre-check. */
final readonly class BoundingBox
{
    public function __construct(public float $west, public float $south, public float $east, public float $north)
    {
        if ($east < $west || $north < $south) {
            throw new \InvalidArgumentException('Invalid bounding box');
        }
    }

    public function contains(float $lon, float $lat): bool
    {
        return $lon >= $this->west && $lon <= $this->east && $lat >= $this->south && $lat <= $this->north;
    }

    public function intersects(self $other): bool
    {
        return $this->west <= $other->east && $other->west <= $this->east
            && $this->south <= $other->north && $other->south <= $this->north;
    }

    public function expandedBy(float $degrees): self
    {
        return new self($this->west - $degrees, $this->south - $degrees, $this->east + $degrees, $this->north + $degrees);
    }
}
