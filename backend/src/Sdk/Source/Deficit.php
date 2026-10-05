<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

/** A detected gap in the result of a source, e.g. missing detail texts; the SnapshotAssembler decides the consequence. */
final readonly class Deficit
{
    /** @param array<string, string|int|float> $params */
    public function __construct(public DeficitKind $kind, public array $params = [], private ?string $key = null) {}

    /**
     * A gap only this source knows, described by a text of its own messages (source.<id>.…).
     *
     * @param array<string, string|int|float> $params
     */
    public static function own(string $key, array $params = []): self
    {
        return new self(DeficitKind::Own, $params, $key);
    }

    /** Text key of the issue: issue.<kind>, or the source's own key. */
    public function messageKey(): string
    {
        return $this->key ?? 'issue.' . $this->kind->value;
    }
}
