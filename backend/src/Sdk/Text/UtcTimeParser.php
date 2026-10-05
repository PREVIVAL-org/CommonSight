<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Text;

use CommonSight\Model\Value\UtcInstant;

/**
 * Converts time values of a source to UTC using the source's time zone (D-18).
 *
 * Values with a zone or offset keep it; local time without a zone is interpreted in the source's zone
 * (time zone database, summer and winter time). PHP's default time zone is never used.
 */
final class UtcTimeParser
{
    /** From this value on, a number counts as milliseconds since 1970. */
    private const MILLISECONDS_THRESHOLD = 100_000_000_000;

    public function parse(mixed $value, \DateTimeZone $sourceZone): ?UtcInstant
    {
        if (is_int($value) || is_float($value)) {
            return $this->fromEpoch((float) $value);
        }
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if (is_numeric($trimmed)) {
            return $this->fromEpoch((float) $trimmed);
        }

        return $this->fromText($trimmed, $sourceZone);
    }

    public function parseUtc(mixed $value): ?UtcInstant
    {
        return $this->parse($value, new \DateTimeZone('UTC'));
    }

    private function fromEpoch(float $value): ?UtcInstant
    {
        if (!is_finite($value) || $value <= 0) {
            return null;
        }
        $seconds = $value >= self::MILLISECONDS_THRESHOLD ? $value / 1000 : $value;

        return UtcInstant::fromTimestamp((int) floor($seconds));
    }

    private function fromText(string $value, \DateTimeZone $sourceZone): ?UtcInstant
    {
        // Only absolute values: ISO 8601 or RFC 2822, no relative expressions like "tomorrow".
        if (preg_match('/^(\d{4}-\d{2}-\d{2}|(\w{3},\s*)?\d{1,2}\s+\w{3}\s+\d{4})/u', $value) !== 1) {
            return null;
        }
        try {
            $parsed = new \DateTimeImmutable($value, $sourceZone);
        } catch (\Exception) {
            return null;
        }
        $errors = \DateTimeImmutable::getLastErrors();
        if ($errors !== false && $errors['warning_count'] + $errors['error_count'] > 0) {
            return null;
        }
        $hasOwnZone = $parsed->getTimezone()->getName() !== $sourceZone->getName();

        return UtcInstant::fromTimestamp($hasOwnZone ? $parsed->getTimestamp() : $this->earliestMatch($parsed, $sourceZone));
    }

    /**
     * In the hour that occurs twice when switching to winter time, chooses the earlier possibility.
     * PHP chooses the later one; an instant that shows the same local time half an hour or an hour earlier takes precedence.
     */
    private function earliestMatch(\DateTimeImmutable $parsed, \DateTimeZone $zone): int
    {
        $wallTime = $parsed->format('Y-m-d H:i:s');
        foreach ([3600, 1800] as $shift) {
            $candidate = $parsed->getTimestamp() - $shift;
            if ((new \DateTimeImmutable('@' . $candidate))->setTimezone($zone)->format('Y-m-d H:i:s') === $wallTime) {
                return $candidate;
            }
        }

        return $parsed->getTimestamp();
    }
}
