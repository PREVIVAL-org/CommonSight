<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Alertswiss\Record;

use CommonSight\Model\Geometry;
use CommonSight\Model\Value\UtcInstant;

/** A current alert of alert.swiss with its texts and its area. */
final readonly class AlertswissAlert
{
    public function __construct(
        public string $id,
        public string $title,
        /** kind of event as Alertswiss names it, e.g. "Feuerverbot" */
        public string $event,
        public string $severity,
        public ?UtcInstant $sent,
        public string $description,
        public string $instructions,
        public string $areaDescription,
        /** canton or authority that issued it, e.g. "Kanton Uri" */
        public ?string $publisher,
        public ?string $link,
        public ?Geometry $geometry,
    ) {}
}
