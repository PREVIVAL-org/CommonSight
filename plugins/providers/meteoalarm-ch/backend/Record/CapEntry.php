<?php

declare(strict_types=1);

namespace CommonSight\Plugin\MeteoAlarm\Record;

use CommonSight\Model\Value\UtcInstant;

/** An entry of the MeteoAlarm Atom feed with its CAP fields. */
final readonly class CapEntry
{
    public function __construct(
        public string $id,
        public string $title,
        public string $headline,
        public string $description,
        public ?string $alternateLink,
        public ?UtcInstant $updated,
        public ?UtcInstant $onset,
        public ?UtcInstant $expires,
        public string $severity,
        public string $event,
        public string $areaDesc,
        public string $polygon,
        public string $instruction = '',
        /** language of the texts if known: de once the German texts of the CAP message replaced those of the feed */
        public ?string $language = null,
    ) {}

    /** The warning with the German texts of its CAP message; an empty text keeps the one of the feed. */
    public function inGerman(string $headline, string $event, string $description, string $instruction): self
    {
        return new self(
            $this->id,
            $headline !== '' ? $headline : $this->title,
            $headline !== '' ? $headline : $this->headline,
            $description !== '' ? $description : $this->description,
            $this->alternateLink,
            $this->updated,
            $this->onset,
            $this->expires,
            $this->severity,
            $event !== '' ? $event : $this->event,
            $this->areaDesc,
            $this->polygon,
            $instruction,
            'de',
        );
    }
}
