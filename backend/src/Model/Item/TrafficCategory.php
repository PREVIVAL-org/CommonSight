<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

/**
 * What a traffic notice is about, the same for every source: the map colours jams apart from the many closures and
 * roadworks. The source's own wording stays in noticeType.
 */
enum TrafficCategory: string
{
    /** Jam, slow or heavy traffic */
    case Jam = 'jam';
    /** Road, lane, entry or exit closed */
    case Closure = 'closure';
    case Roadworks = 'roadworks';
    /** Any other disruption */
    case Other = 'other';
}
