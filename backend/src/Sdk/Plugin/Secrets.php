<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

/** The secrets of one source (API keys, tokens) from the configuration; only the ones the source declared. */
final readonly class Secrets
{
    /** @param array<string, string> $values name -> value */
    public function __construct(private array $values = []) {}

    /** @throws \OutOfBoundsException if the secret is not configured; the core does not run a source with missing secrets */
    public function get(string $name): string
    {
        return $this->values[$name] ?? throw new \OutOfBoundsException('Secret not configured: ' . $name);
    }

    public function has(string $name): bool
    {
        return isset($this->values[$name]) && $this->values[$name] !== '';
    }

    /** @return array<string, string> the names only, so that no dump or error output shows a value */
    public function __debugInfo(): array
    {
        return array_map(static fn(): string => '***', $this->values);
    }
}
