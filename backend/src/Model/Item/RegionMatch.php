<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

/** How the regions of an item were determined (U-14, Architecture 4.11). */
enum RegionMatch: string
{
    case Geometry = 'geometry';
    case Point = 'point';
    case Source = 'source';
    case Area = 'area';
    case None = 'none';
}
