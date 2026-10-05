<?php

declare(strict_types=1);

namespace CommonSight\Model;

/** Last assessment of a measurement before it became stale (B-01). */
final readonly class PreviousAssessment implements \JsonSerializable
{
    public function __construct(public Level $level, public Msg $label) {}

    /** @return array{level: string, label: Msg} */
    public function jsonSerialize(): array
    {
        return ['level' => $this->level->value, 'label' => $this->label];
    }
}
