<?php

declare(strict_types=1);

namespace CommonSight\Model\Value;

/** Identifier of a region: ISO 3166-2 for DE and CH, state code AT-1 to AT-9. */
final readonly class RegionId
{
    private function __construct(public string $value)
    {
        if (preg_match('/^(?:(?:DE|CH)-[A-Z]{2}|AT-[1-9])$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid region ID: ' . $value);
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function austrianState(int $digit): self
    {
        return new self('AT-' . $digit);
    }
}
