<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere\Record;

use CommonSight\Model\Item\WarningSection;
use CommonSight\Model\Value\UtcInstant;

/** Assembled German detail text of a GeoSphere warning with issue time (Q-W-AT-08). */
final readonly class WarningDetail
{
    /** @param list<WarningSection> $sections */
    public function __construct(public array $sections, public ?UtcInstant $create) {}
}
