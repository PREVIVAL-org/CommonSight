<?php

declare(strict_types=1);

namespace CommonSight\Model\Value;

/** Absolute instant, to the second, output only as ISO 8601 in UTC with Z (D-12). */
final readonly class UtcInstant implements \JsonSerializable
{
    private function __construct(public int $timestamp) {}

    public static function fromTimestamp(int $timestamp): self
    {
        return new self($timestamp);
    }

    public static function fromDateTime(\DateTimeInterface $dateTime): self
    {
        return new self($dateTime->getTimestamp());
    }

    /** Reads exactly this class's output format back in (e.g. from state files). */
    public static function fromIso(string $iso): self
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $iso, new \DateTimeZone('UTC'));
        if ($parsed === false) {
            throw new \InvalidArgumentException('Not a UTC point in time: ' . $iso);
        }

        return new self($parsed->getTimestamp());
    }

    public function toIso(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $this->timestamp);
    }

    public function plusSeconds(int $seconds): self
    {
        return new self($this->timestamp + $seconds);
    }

    public function secondsSince(self $earlier): int
    {
        return $this->timestamp - $earlier->timestamp;
    }

    public function isAfter(self $other): bool
    {
        return $this->timestamp > $other->timestamp;
    }

    public function isBefore(self $other): bool
    {
        return $this->timestamp < $other->timestamp;
    }

    public function jsonSerialize(): string
    {
        return $this->toIso();
    }

    /** @param list<self|null> $instants */
    public static function latest(array $instants): ?self
    {
        $latest = null;
        foreach ($instants as $instant) {
            if ($instant !== null && ($latest === null || $instant->isAfter($latest))) {
                $latest = $instant;
            }
        }

        return $latest;
    }
}
