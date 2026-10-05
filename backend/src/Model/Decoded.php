<?php

declare(strict_types=1);

namespace CommonSight\Model;

/**
 * Type-safe read access to decoded, untrusted data (JSON, PHP arrays): missing or wrongly
 * typed values yield null instead of an error.
 */
final readonly class Decoded
{
    private function __construct(private mixed $value) {}

    public static function of(mixed $value): self
    {
        return new self($value);
    }

    public function get(string|int ...$path): self
    {
        $value = $this->value;
        foreach ($path as $key) {
            $value = is_array($value) && array_key_exists($key, $value) ? $value[$key] : null;
        }

        return new self($value);
    }

    public function isNull(): bool
    {
        return $this->value === null;
    }

    public function isArray(): bool
    {
        return is_array($this->value);
    }

    public function isList(): bool
    {
        return is_array($this->value) && array_is_list($this->value);
    }

    /** Only real strings. */
    public function string(): ?string
    {
        return is_string($this->value) ? $this->value : null;
    }

    /** Strings and numbers as text, otherwise null. */
    public function text(): ?string
    {
        return is_string($this->value) || is_int($this->value) || is_float($this->value) ? (string) $this->value : null;
    }

    /** Integers, also as text without decimal places. */
    public function int(): ?int
    {
        if (is_int($this->value)) {
            return $this->value;
        }
        $filtered = is_string($this->value) || is_float($this->value) ? filter_var($this->value, FILTER_VALIDATE_INT) : false;

        return is_int($filtered) ? $filtered : null;
    }

    /** Finite numbers, also as text with a decimal point. */
    public function float(): ?float
    {
        if (is_int($this->value) || is_float($this->value)) {
            return is_finite((float) $this->value) ? (float) $this->value : null;
        }
        if (is_string($this->value) && is_numeric(trim($this->value))) {
            $number = (float) trim($this->value);

            return is_finite($number) ? $number : null;
        }

        return null;
    }

    public function bool(): ?bool
    {
        return is_bool($this->value) ? $this->value : null;
    }

    /** @return list<self> elements of a list, empty for other values */
    public function list(): array
    {
        return is_array($this->value) ? array_map(static fn(mixed $v): self => new self($v), array_values($this->value)) : [];
    }

    /** @return array<string, self> entries of an object, empty for other values */
    public function entries(): array
    {
        $entries = [];
        foreach (is_array($this->value) ? $this->value : [] as $key => $v) {
            $entries[(string) $key] = new self($v);
        }

        return $entries;
    }

    /** @return list<string> only the strings of a list */
    public function strings(): array
    {
        return array_values(array_filter(array_map(static fn(self $v): ?string => $v->text(), $this->list()), static fn(?string $s): bool => $s !== null));
    }

    /** @return array<mixed> the value if it is an array, otherwise empty */
    public function array(): array
    {
        return is_array($this->value) ? $this->value : [];
    }

    public function raw(): mixed
    {
        return $this->value;
    }
}
