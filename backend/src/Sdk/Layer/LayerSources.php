<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Decoded;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\SourceDescription;

/**
 * The sources of a layer in one scope, by rank, as descriptions only: a layer never knows a source by id (concept:
 * layers as plugins, L-D6). Gives the display name and link of the layer: curated per scope from the layer's data
 * (names.json: {"DE": {"name": ..., "url": ...}}), otherwise composed from the sources.
 */
final readonly class LayerSources
{
    /** @param list<SourceDescription> $sources */
    public function __construct(public array $sources) {}

    /** The names of the sources joined with " · ", e.g. "Hub'Eau · Rijkswaterstaat"; empty without sources. */
    public function composedName(): string
    {
        return implode(' · ', array_map(static fn(SourceDescription $s): string => $s->name, $this->sources));
    }

    /**
     * Display name and link of the layer in a scope: curated if the layer names one for the scope, else composed from
     * its sources with the link of the first one.
     *
     * @param Decoded $curated the decoded names.json of the layer
     * @return array{string, string} name and link
     * @throws \RuntimeException if neither a curated name nor a source exists
     */
    public function nameAndLink(Scope $scope, Decoded $curated): array
    {
        $entry = $curated->get($scope->value);
        $name = $entry->get('name')->string();
        $url = $entry->get('url')->string();
        if ($name !== null && $url !== null) {
            return [$name, $url];
        }
        $first = $this->sources[0] ?? throw new \RuntimeException('No name for scope ' . $scope->value . ' and no source to compose it from');

        return [$this->composedName(), $first->attribution->url];
    }
}
