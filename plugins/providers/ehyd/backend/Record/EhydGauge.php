<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Ehyd\Record;

/** A water gauge from eHYD PegelAktuell in the source's vocabulary; zp is local time Europe/Vienna without a zone. */
final readonly class EhydGauge
{
    public function __construct(
        public string $hzbnr,
        public string $messstelle,
        public string $gewasser,
        public string $hd,
        public string $parameter,
        public float $wert,
        public string $einheit,
        public string $zp,
        public ?int $gesamtcode,
        public ?string $internet,
        public float $lon,
        public float $lat,
    ) {}
}
