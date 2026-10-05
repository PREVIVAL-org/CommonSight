<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Value\Scope;

/**
 * The mechanics of the core that a layer composes into its steps, because they need the region data of the core
 * (V2, ADR 0038). The core adds the nearby countries and regions in a country scope itself.
 */
interface LayerMechanics
{
    /** Assigns the items to the regions of a country (U-14). */
    public function regions(Scope $country): PipelineStep;

    /**
     * The steps of the border zone: only items outside DACH, with the countries and regions near them, within the zone.
     *
     * @return list<PipelineStep>
     */
    public function borderZone(): array;
}
