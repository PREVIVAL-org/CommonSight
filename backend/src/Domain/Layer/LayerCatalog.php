<?php

declare(strict_types=1);

namespace CommonSight\Domain\Layer;

use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Layer\LayerDefinition;

/**
 * Registered layers per scope (F-01): a new source only touches its adapter and this registration.
 *
 * Definitions are only built on first access, so that a run only loads the master data of its layers.
 */
final class LayerCatalog
{
    /** @var array<string, \Closure(): LayerDefinition> */
    private array $factories = [];
    /** @var array<string, LayerDefinition> */
    private array $built = [];

    /** @param array<string, \Closure(): LayerDefinition> $factories key "<scope>/<layer>" */
    private function __construct(array $factories)
    {
        $this->factories = $factories;
    }

    /** @param list<LayerDefinition> $definitions */
    public static function of(array $definitions): self
    {
        $factories = [];
        foreach ($definitions as $definition) {
            $factories[self::key($definition->layer, $definition->scope)] = static fn(): LayerDefinition => $definition;
        }

        return new self($factories);
    }

    /** @param array<string, \Closure(): LayerDefinition> $factories */
    public static function lazy(array $factories): self
    {
        return new self($factories);
    }

    public static function key(LayerId $layer, Scope $scope): string
    {
        return $scope->value . '/' . $layer->value;
    }

    public function get(LayerId $layer, Scope $scope): LayerDefinition
    {
        $key = self::key($layer, $scope);
        if (!isset($this->factories[$key])) {
            throw new \OutOfBoundsException(sprintf('Layer %s is not registered for %s', $layer->value, $scope->value));
        }

        return $this->built[$key] ??= ($this->factories[$key])();
    }

    /** @return list<string> keys of all registered layers */
    public function keys(): array
    {
        return array_keys($this->factories);
    }
}
