<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Domain\Geo\AreaNameMatcher;
use CommonSight\Domain\Geo\GeometrySamples;
use CommonSight\Domain\Geo\InteriorSamples;
use CommonSight\Domain\Geo\NearbyFinder;
use CommonSight\Domain\Geo\PointInPolygon;
use CommonSight\Domain\Geo\RegionLocator;
use CommonSight\Domain\Geo\RegionMatcher;
use CommonSight\Domain\Pipeline\BeyondZoneFilter;
use CommonSight\Domain\Pipeline\NearbyAssigner;
use CommonSight\Domain\Pipeline\OutsideDachFilter;
use CommonSight\Domain\Pipeline\RegionAssigner;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Geo\ScanlineIntervals;
use CommonSight\Sdk\Layer\LayerMechanics;
use CommonSight\Sdk\Layer\PipelineStep;

/**
 * The region mechanics of the core for the layers: assignment to the regions of a country (V2), the border zone and the
 * nearby countries and regions (ADR 0038), built once from the generated region data.
 */
final class CoreLayerMechanics implements LayerMechanics
{
    /** @var array<string, RegionAssigner> */
    private array $regionAssigners = [];

    private ?NearbyFinder $nearbyFinder = null;

    /** @param float $vicinityKm radius around a country or region within which items count as its vicinity */
    public function __construct(private readonly GeneratedData $data, private readonly float $vicinityKm) {}

    public function regions(Scope $country): PipelineStep
    {
        return $this->regionAssigners[$country->value] ??= new RegionAssigner(new RegionMatcher(
            new RegionLocator($this->data->grid($country), $this->data->regions($country), new PointInPolygon()),
            new GeometrySamples(new InteriorSamples(new ScanlineIntervals())),
            new PointInPolygon(),
            new AreaNameMatcher(),
        ));
    }

    public function borderZone(): array
    {
        return [$this->outsideDach(), $this->nearby(), new BeyondZoneFilter()];
    }

    /** In a country: the DACH countries and regions near each item, for the section "Grenzgebiet" of the others. */
    public function nearby(): PipelineStep
    {
        if ($this->nearbyFinder === null) {
            $countries = [];
            foreach (Scope::countries() as $country) {
                $countries[$country->value] = [$this->data->grid($country), $this->data->regions($country)];
            }
            $this->nearbyFinder = new NearbyFinder($countries, $this->vicinityKm);
        }

        return new NearbyAssigner($this->nearbyFinder);
    }

    private function outsideDach(): OutsideDachFilter
    {
        return new OutsideDachFilter(array_map(
            fn(Scope $country): RegionLocator => new RegionLocator($this->data->grid($country), $this->data->regions($country), new PointInPolygon()),
            Scope::countries(),
        ));
    }
}
