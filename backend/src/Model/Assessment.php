<?php

declare(strict_types=1);

namespace CommonSight\Model;

use CommonSight\Model\Value\UtcInstant;

/** Assessment of a measurement with reasoning, origin and validity (D-20, D-21). */
final readonly class Assessment implements \JsonSerializable
{
    public function __construct(
        public Level $level,
        public Msg $label,
        public Msg $basis,
        public AssessmentOrigin $origin,
        public ?UtcInstant $validUntil = null,
        public string|int|float|null $sourceValue = null,
        public ?PreviousAssessment $previous = null,
    ) {}

    /**
     * Placeholder of a source for a value that its layer classifies with its own display thresholds (e.g. dose rate);
     * the layer replaces it (concept: sources as plugins, P5).
     */
    public static function byLayer(): self
    {
        return new self(Level::Unknown, new Msg('assessment.label.none'), new Msg('assessment.basis.byLayer'), AssessmentOrigin::Display);
    }

    public function withValidUntil(UtcInstant $validUntil): self
    {
        return new self($this->level, $this->label, $this->basis, $this->origin, $validUntil, $this->sourceValue, $this->previous);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = [
            'level' => $this->level->value,
            'label' => $this->label,
            'basis' => $this->basis,
            'origin' => $this->origin->value,
        ];
        if ($this->validUntil !== null) {
            $data['validUntil'] = $this->validUntil;
        }
        if ($this->sourceValue !== null) {
            $data['sourceValue'] = $this->sourceValue;
        }
        if ($this->previous !== null) {
            $data['previous'] = $this->previous;
        }

        return $data;
    }
}
