<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AtAlert\Record;

use CommonSight\Model\Geometry;
use CommonSight\Model\Value\UtcInstant;

/** A warning of AT-Alert with its texts and its area. */
final readonly class AtAlertWarning
{
    public function __construct(
        /** consolidation_identifier, e.g. AlertLevel1.German.20656.20261003 */
        public string $id,
        /** AlertLevel1 to AlertLevel4 or Amber */
        public string $level,
        public string $title,
        public string $text,
        /** states or districts the warning was sent to, e.g. "Burgenland" */
        public string $area,
        public ?UtcInstant $sent,
        public ?UtcInstant $expires,
        /** a test of the warning system sent at a real level, recognised by its title (e.g. the Zivilschutz-Probealarm) */
        public bool $test,
        public ?Geometry $geometry,
    ) {}
}
