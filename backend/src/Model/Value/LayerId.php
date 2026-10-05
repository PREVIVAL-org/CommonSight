<?php

declare(strict_types=1);

namespace CommonSight\Model\Value;

/**
 * Identifier of a thematic data layer, e.g. "water". An open id since layers can come as plugins (concept: layers as
 * plugins, L1): the format is checked here, whether the layer exists is checked against the layer registry where ids
 * come in. One instance per id, so that ids can be compared with === and used in match.
 */
final class LayerId
{
    private const PATTERN = '/^[a-z][a-z0-9-]{1,30}$/D';

    /** @var array<string, self> */
    private static array $instances = [];

    private function __construct(public readonly string $value) {}

    /** @throws \InvalidArgumentException for an id of the wrong format */
    public static function from(string $value): self
    {
        return self::tryFrom($value) ?? throw new \InvalidArgumentException('Invalid layer id: ' . $value);
    }

    public static function tryFrom(string $value): ?self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            return null;
        }

        return self::$instances[$value] ??= new self($value);
    }
}
