<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Storage;

use CommonSight\Model\Decoded;
use CommonSight\Model\State\SourceHealth;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\SourceHealthStore;

/** Health of a source per scope in state/sources/<id>-<scope>.json; an unreadable file counts like a missing one. */
final class SourceHealthFile implements SourceHealthStore
{
    public function __construct(private readonly string $stateDir, private readonly AtomicFile $files) {}

    public function read(string $sourceId, Scope $scope): ?SourceHealth
    {
        $contents = $this->files->read($this->path($sourceId . '-' . $scope->value));
        if ($contents === null) {
            return null;
        }
        try {
            $data = Decoded::of(json_decode($contents, true, 8, JSON_THROW_ON_ERROR));

            return new SourceHealth(
                $sourceId,
                $scope,
                $this->instant($data->get('lastAttemptAt')),
                $this->instant($data->get('lastSuccessAt')),
                $data->get('consecutiveFailures')->int() ?? 0,
                $this->instant($data->get('backoffUntil')),
                $data->get('lastFailure')->string(),
                $data->get('itemCount')->int() ?? 0,
                $data->get('typicalDurationMs')->int(),
                $data->get('outcomeRelease')->string(),
            );
        } catch (\JsonException|\InvalidArgumentException) {
            return null; // Broken file: like "never ran", the next run rewrites it.
        }
    }

    public function write(SourceHealth $health): void
    {
        $this->files->write($this->path($health->key()), json_encode($health, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function path(string $key): string
    {
        return $this->stateDir . '/sources/' . $key . '.json';
    }

    private function instant(Decoded $value): ?UtcInstant
    {
        $iso = $value->string();

        return $iso === null ? null : UtcInstant::fromIso($iso);
    }
}
