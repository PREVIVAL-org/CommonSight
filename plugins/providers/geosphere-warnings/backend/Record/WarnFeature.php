<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere\Record;

/** A warning from getWarnstatus in GeoSphere's vocabulary; area in EPSG:31287, optionally with German detail. */
final readonly class WarnFeature
{
    /**
     * @param list<string> $gemeinden five-digit municipality codes
     * @param array<mixed>|null $coordinates coordinates in EPSG:31287
     */
    public function __construct(
        public string $warnid,
        public int $wtype,
        public int $wlevel,
        public int $start,
        public int $end,
        public array $gemeinden,
        public ?string $geometryType,
        public ?array $coordinates,
        public ?WarningDetail $detail = null,
    ) {}

    public function withDetail(WarningDetail $detail): self
    {
        return new self($this->warnid, $this->wtype, $this->wlevel, $this->start, $this->end, $this->gemeinden, $this->geometryType, $this->coordinates, $detail);
    }
}
