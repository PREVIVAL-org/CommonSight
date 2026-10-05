<?php

declare(strict_types=1);

namespace CommonSight\Model\Value;

/** Gamma ambient dose rate, normalized to µSv/h. */
final readonly class DoseRate
{
    private const FACTORS = ['nSv/h' => 0.001, 'uSv/h' => 1.0, 'mSv/h' => 1000.0, 'Sv/h' => 1_000_000.0];

    private function __construct(public float $microSievertPerHour) {}

    /** Returns null for an unknown unit or an invalid value (B-13). */
    public static function fromValueAndUnit(float $value, string $unit): ?self
    {
        $normalizedUnit = str_replace(['μ', 'µ', ' '], ['u', 'u', ''], $unit);
        $factor = self::FACTORS[$normalizedUnit] ?? null;
        if ($factor === null || !is_finite($value) || $value < 0) {
            return null;
        }
        $normalized = $value * $factor;

        return is_finite($normalized) ? new self($normalized) : null;
    }
}
