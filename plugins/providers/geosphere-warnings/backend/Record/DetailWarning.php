<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere\Record;

/** A message from getWarningsForCoords in GeoSphere's vocabulary (properties and rawinfo). */
final readonly class DetailWarning
{
    public function __construct(
        public string $warnid,
        public string $chgid,
        public string $verlaufid,
        public int $wtype,
        public int $wlevel,
        public ?int $start,
        public ?int $end,
        public string $text,
        public string $meteotext,
        public string $auswirkungen,
        public string $empfehlungen,
        public string $updategrund,
        public ?string $create,
    ) {}
}
