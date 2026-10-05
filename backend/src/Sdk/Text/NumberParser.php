<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Text;

/** Reads finite numbers from source data, also with a decimal comma. */
final class NumberParser
{
    public function parse(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }
        if (!is_string($value)) {
            return null;
        }
        $normalized = str_replace(',', '.', trim($value));
        if (!is_numeric($normalized)) {
            return null;
        }
        $number = (float) $normalized;

        return is_finite($number) ? $number : null;
    }

    /** Returns an integer only if the value really is integral. */
    public function parseInt(mixed $value): ?int
    {
        $number = $this->parse($value);
        if ($number === null || $number !== floor($number) || abs($number) > PHP_INT_MAX) {
            return null;
        }

        return (int) $number;
    }
}
