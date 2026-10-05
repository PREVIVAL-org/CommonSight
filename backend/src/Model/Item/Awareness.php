<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

/** Color level of a warning for sources that have one (GeoSphere). */
enum Awareness: string
{
    case Yellow = 'yellow';
    case Orange = 'orange';
    case Red = 'red';
}
