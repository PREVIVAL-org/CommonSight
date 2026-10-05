<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Mowas\Record;

use CommonSight\Model\Geometry;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\Mowas\NinaFeed;

/** A message from the mapData.json of a NINA feed, optionally supplemented by its warning area. */
final readonly class MowasWarning
{
    public function __construct(
        public string $id,
        public int $version,
        public ?string $titleDe,
        public ?UtcInstant $startDate,
        public ?UtcInstant $expiresDate,
        public string $severity,
        public NinaFeed $feed = NinaFeed::Mowas,
        public ?Geometry $geometry = null,
    ) {}

    public function withGeometry(Geometry $geometry): self
    {
        return new self($this->id, $this->version, $this->titleDe, $this->startDate, $this->expiresDate, $this->severity, $this->feed, $geometry);
    }
}
