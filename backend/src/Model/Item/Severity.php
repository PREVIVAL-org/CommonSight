<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

/** Severity of a warning according to CAP. */
enum Severity: string
{
    case Extreme = 'Extreme';
    case Severe = 'Severe';
    case Moderate = 'Moderate';
    case Minor = 'Minor';
    case Unknown = 'Unknown';

    /** Rank for sorting, 0 = most severe (Q-W-01). */
    public function rank(): int
    {
        return match ($this) {
            self::Extreme => 0,
            self::Severe => 1,
            self::Moderate => 2,
            self::Minor => 3,
            self::Unknown => 4,
        };
    }

    public static function fromSource(?string $value): self
    {
        return self::tryFrom(ucfirst(strtolower(trim((string) $value)))) ?? self::Unknown;
    }
}
