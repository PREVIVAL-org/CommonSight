<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Value\Scope;

/**
 * Who a layer is (concept: layers as plugins): id, scopes, how the frontend shows it and the settings it reads. The
 * contract build writes the layer registry (contract/catalog/layers.json) from these descriptions.
 */
final readonly class LayerDescription
{
    public const VIEWS = ['measurements', 'events'];

    /** @param list<LayerSetting> $settings */
    public function __construct(
        public string $id,
        /** one layer for everyone (space, news) instead of one per country */
        public bool $global,
        /** also in the border zone beyond DACH (scope border) */
        public bool $border,
        /** default color, e.g. #398ed4; overridable by the host page (--cs-layer-<id>) */
        public string $color,
        /** name of a lucide icon (L-D8) */
        public string $icon,
        public bool $onMap,
        /** items can be filtered by region (U-13) */
        public bool $regionFilter,
        /** list view the layer belongs to: measurements, events or none */
        public ?string $view,
        public bool $defaultActive,
        /** position in the layer list and the registry */
        public int $order,
        public array $settings = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{1,30}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Invalid layer id: ' . $id);
        }
        if (preg_match('/^#[0-9a-f]{6}$/', $color) !== 1 || trim($icon) === '') {
            throw new \InvalidArgumentException($id . ': color as #rrggbb and an icon name are needed');
        }
        if ($view !== null && !in_array($view, self::VIEWS, true)) {
            throw new \InvalidArgumentException($id . ': unknown list view ' . $view);
        }
        if ($global && $border) {
            throw new \InvalidArgumentException($id . ': a global layer has no border zone');
        }
        $names = array_map(static fn(LayerSetting $s): string => $s->name, $settings);
        if (count($names) !== count(array_unique($names))) {
            throw new \InvalidArgumentException($id . ': settings declared twice');
        }
    }

    /** @return list<Scope> the scopes the layer has snapshots for */
    public function scopes(): array
    {
        if ($this->global) {
            return [Scope::Global];
        }

        return $this->border ? [...Scope::countries(), Scope::Border] : Scope::countries();
    }

    /** @return array<string, mixed> the entry of the layer registry, as the frontend reads it */
    public function registryEntry(): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->global ? 'global' : 'country',
            'color' => $this->color,
            'icon' => $this->icon,
            'onMap' => $this->onMap,
            'regionFilter' => $this->regionFilter,
            'view' => $this->view,
            'defaultActive' => $this->defaultActive,            'border' => $this->border,
        ];
    }
}
