<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Dwd\Record;

use CommonSight\Model\Geometry;
use CommonSight\Model\Value\UtcInstant;

/** A DWD warning for a district in the vocabulary of the WFS dwd:Warnungen_Landkreise. */
final readonly class DwdWarningFeature
{
    public function __construct(
        public string $id,
        public string $HEADLINE,
        public string $AREADESC,
        public string $DESCRIPTION,
        public string $INSTRUCTION,
        public string $EVENT,
        public string $SEVERITY,
        public ?string $WEB,
        public ?UtcInstant $SENT,
        public ?UtcInstant $ONSET,
        public ?UtcInstant $EXPIRES,
        public ?Geometry $geometry,
    ) {}
}
