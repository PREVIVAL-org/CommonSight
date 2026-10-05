<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

/** The validated settings of one layer, by name. */
final readonly class LayerSettings
{
    /** @param array<string, float> $values */
    public function __construct(private array $values = []) {}

    /** @throws \OutOfBoundsException for a setting the layer did not declare */
    public function float(string $name): float
    {
        return $this->values[$name] ?? throw new \OutOfBoundsException('Setting not declared: ' . $name);
    }
}
