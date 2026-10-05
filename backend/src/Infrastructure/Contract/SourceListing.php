<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Contract;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\SourceDescription;

/**
 * What the frontend needs to know about the source plugins (contract/catalog/sources.json): name, layer, scopes, rank
 * and the attribution the source demands; the map credits and the data use list are built from it.
 */
final class SourceListing
{
    /**
     * @param list<SourceDescription> $descriptions
     * @return list<array{id: string, name: string, layer: string, scopes: list<string>, order: int, attribution: array{text: string, url: string, license?: string}}>
     *         by layer, then rank, then id
     */
    public function list(array $descriptions): array
    {
        usort($descriptions, static fn(SourceDescription $a, SourceDescription $b): int => [$a->layer, $a->order, $a->id] <=> [$b->layer, $b->order, $b->id]);

        return array_map(static fn(SourceDescription $d): array => [
            'id' => $d->id,
            'name' => $d->name,
            'layer' => $d->layer,
            'scopes' => array_map(static fn(Scope $scope): string => $scope->value, $d->scopes),
            'order' => $d->order,
            'attribution' => ['text' => $d->attribution->text, 'url' => $d->attribution->url]
                + ($d->attribution->license === null ? [] : ['license' => $d->attribution->license]),
        ], $descriptions);
    }
}
