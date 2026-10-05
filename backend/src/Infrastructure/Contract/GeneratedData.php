<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Contract;

use CommonSight\Domain\Geo\RegionGrid;
use CommonSight\Model\Decoded;
use CommonSight\Model\Layer\LayerMeta;
use CommonSight\Model\Place\BoundingBox;
use CommonSight\Model\Place\City;
use CommonSight\Model\Place\Country;
use CommonSight\Model\Place\Region;
use CommonSight\Model\Place\RegionCodes;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\RegionId;
use CommonSight\Model\Value\Scope;

/**
 * Loads the PHP files generated from contract/ at build time (generated/*.php, in the OPcache) and builds the
 * master data from them (F-16, Architecture 3.3).
 */
final class GeneratedData
{
    /** @var array<string, Decoded> */
    private array $loaded = [];
    /** @var array<string, list<Region>> */
    private array $regions = [];

    public function __construct(private readonly string $directory) {}

    /** @return list<LayerMeta> */
    public function layerMetas(): array
    {
        return array_map(static fn(Decoded $l): LayerMeta => new LayerMeta(
            LayerId::from((string) $l->get('id')->string()),
            $l->get('scope')->string() === 'global',
            $l->get('border')->bool() === true,
        ), $this->file('layers')->list());
    }

    /** @return array<string, array{kind: string, factory: string, dir: string}> plugins found at build time (sources and layers), by id */
    public function plugins(): array
    {
        $plugins = [];
        foreach ($this->file('plugins')->entries() as $id => $plugin) {
            $plugins[$id] = ['kind' => $plugin->get('kind')->string() ?? 'source', 'factory' => (string) $plugin->get('factory')->string(), 'dir' => (string) $plugin->get('dir')->string()];
        }

        return $plugins;
    }

    /** Stable official codes of the regions (contract/data/regions.json, concept V2). */
    public function regionCodes(): RegionCodes
    {
        $byScheme = [];
        foreach ($this->file('region-codes')->get('schemes')->entries() as $scheme => $codes) {
            foreach ($codes->entries() as $code => $regionId) {
                $byScheme[(string) $scheme][(string) $code] = RegionId::fromString((string) $regionId->string());
            }
        }
        $names = [];
        foreach ($this->file('region-codes')->get('names')->entries() as $id => $name) {
            $names[(string) $id] = (string) $name->string();
        }

        return new RegionCodes($byScheme, $names);
    }

    /** @return array<string, Country> */
    public function countries(): array
    {
        $countries = [];
        foreach ($this->file('countries')->entries() as $code => $c) {
            $countries[$code] = new Country(Scope::from($code), (string) $c->get('name')->string(), $this->bounds($c->get('bounds')));
        }

        return $countries;
    }

    /** @return list<City> */
    public function cities(): array
    {
        return array_map(static fn(Decoded $c): City => new City(
            (string) $c->get('id')->string(),
            Scope::from((string) $c->get('country')->string()),
            (string) $c->get('name')->string(),
            Coordinate::fromLatLon((float) $c->get('lat')->float(), (float) $c->get('lon')->float()),
            RegionId::fromString((string) $c->get('regionId')->string()),
        ), $this->file('cities')->list());
    }

    /** @return list<City> places of the border zone beyond DACH for weather and air, without region (scope border) */
    public function borderPlaces(): array
    {
        return array_map(static fn(Decoded $p): City => new City(
            (string) $p->get('id')->string(),
            Scope::Border,
            (string) $p->get('name')->string(),
            Coordinate::fromLatLon((float) $p->get('lat')->float(), (float) $p->get('lon')->float()),
            null,
            (string) $p->get('country')->string(),
        ), $this->file('border-places')->list());
    }

    /** How far the map area reaches beyond DACH (border-zone.json, ADR 0038): the vicinity cannot reach further. */
    public function borderMapKm(): float
    {
        return (float) $this->file('border-zone')->get('mapKm')->float();
    }

    /** @return list<Region> in the order of the grid */
    public function regions(Scope $country): array
    {
        return $this->regions[$country->value] ??= array_map(fn(Decoded $r): Region => new Region(
            RegionId::fromString((string) $r->get('id')->string()),
            $country,
            (string) $r->get('name')->string(),
            $r->get('aliases')->strings(),
            new BoundingBox((float) $r->get('bbox', 0)->float(), (float) $r->get('bbox', 1)->float(), (float) $r->get('bbox', 2)->float(), (float) $r->get('bbox', 3)->float()),
            Coordinate::fromLonLat((float) $r->get('refPoint', 0)->float(), (float) $r->get('refPoint', 1)->float()),
            $this->polygons($r->get('polygons')),
        ), $this->file('regions-' . $country->value)->get('regions')->list());
    }

    public function grid(Scope $country): RegionGrid
    {
        $g = $this->file('regions-' . $country->value)->get('grid');
        $candidates = [];
        foreach ($g->get('candidates')->entries() as $cell => $list) {
            $candidates[(int) $cell] = array_values(array_map(static fn(Decoded $i): int => (int) $i->int(), $list->list()));
        }

        return new RegionGrid(
            (float) $g->get('west')->float(),
            (float) $g->get('south')->float(),
            (float) $g->get('cellSize')->float(),
            (int) $g->get('cols')->int(),
            (int) $g->get('rows')->int(),
            (string) $g->get('cells')->string(),
            $candidates,
        );
    }

    private function bounds(Decoded $b): BoundingBox
    {
        return new BoundingBox((float) $b->get('west')->float(), (float) $b->get('south')->float(), (float) $b->get('east')->float(), (float) $b->get('north')->float());
    }

    /**
     * The areas come from our own build and are already validated; here they are only brought into the expected shape.
     *
     * @return list<list<list<array{float, float}>>>
     */
    private function polygons(Decoded $polygons): array
    {
        /** @var list<list<list<array{float, float}>>> $raw */
        $raw = $polygons->array();

        return $raw;
    }

    private function file(string $name): Decoded
    {
        if (!isset($this->loaded[$name])) {
            $path = $this->directory . '/' . $name . '.php';
            if (!is_file($path)) {
                throw new \RuntimeException('Generated master data missing: ' . $path . ' (run bin/build-contract.php)');
            }
            $this->loaded[$name] = Decoded::of(require $path);
        }

        return $this->loaded[$name];
    }
}
