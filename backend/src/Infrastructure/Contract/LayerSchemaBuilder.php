<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Contract;

use CommonSight\Sdk\Layer\LayerDescription;

/**
 * Builds contract/schema/layers.schema.json from the layer plugins (concept: layers as plugins, 5): the key figures
 * (stats) each layer contributes, their union, and per layer exactly which key figures and which scopes a snapshot has.
 * snapshot.schema.json refers to it, so a new layer needs no change there.
 */
final class LayerSchemaBuilder
{
    /** Definitions of the core in the generated schema. */
    private const RESERVED = ['emptyStats', 'layerStats'];

    /**
     * @param list<LayerDescription> $layers in their order
     * @param array<string, array<mixed>> $stats decoded schema/stats.schema.json per layer id that has key figures
     * @return array<string, mixed>
     */
    public function schema(array $layers, array $stats): array
    {
        $definitions = ['emptyStats' => ['title' => 'EmptyStats', 'type' => 'object', 'additionalProperties' => false]];
        $union = [];
        $conditions = [];
        foreach ($layers as $layer) {
            $name = 'emptyStats';
            if (isset($stats[$layer->id])) {
                $name = $this->definitionName($layer->id);
                $definitions[$name] = $stats[$layer->id];
                $union[] = ['$ref' => '#/$defs/' . $name];
            }
            // Each layer: exactly its key figures (none: an empty object) and exactly its scopes.
            $conditions[] = [
                'if' => ['properties' => ['layer' => ['const' => $layer->id]], 'required' => ['layer']],
                'then' => ['properties' => [
                    'stats' => ['$ref' => '#/$defs/' . $name],
                    'scope' => ['enum' => array_map(static fn($scope): string => $scope->value, $layer->scopes())],
                ]],
            ];
        }
        $union[] = ['$ref' => '#/$defs/emptyStats'];

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => 'https://commonsight.org/schema/v1/layers.schema.json',
            'title' => 'Layers',
            'description' => 'Generated from the layer plugins by bin/build-contract.php: key figures and scopes per layer. Do not edit.',
            '$defs' => $definitions + [
                'layerStats' => ['title' => 'LayerStats', 'anyOf' => $union],
                'byLayer' => ['allOf' => $conditions],
            ],
        ];
    }

    /** e.g. "radiation" -> "radiationStats", "pollen-forecast" -> "pollenForecastStats" */
    private function definitionName(string $id): string
    {
        $name = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $id)))) . 'Stats';
        if (in_array($name, self::RESERVED, true)) {
            throw new \RuntimeException(sprintf('Layer %s: its key figures would be named %s, a name of the core; choose another layer id', $id, $name));
        }

        return $name;
    }
}
