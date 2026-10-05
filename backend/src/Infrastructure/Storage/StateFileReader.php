<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Storage;

use CommonSight\Model\Decoded;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Port\StateReader;

/** Reads state/<scope>/<layer>.json; an unreadable file counts like a missing one and is rewritten on the next run. */
final class StateFileReader implements StateReader
{
    public function __construct(
        private readonly string $stateDir,
        private readonly AtomicFile $files,
        private readonly LayerStateCodec $codec,
    ) {}

    public function read(LayerId $layer, Scope $scope): ?LayerState
    {
        $contents = $this->files->read(sprintf('%s/%s/%s.json', $this->stateDir, $scope->value, $layer->value));
        if ($contents === null) {
            return null;
        }
        try {
            return $this->codec->decode(Decoded::of(json_decode($contents, true, 32, JSON_THROW_ON_ERROR)));
        } catch (\JsonException|\InvalidArgumentException|\ValueError) {
            return null; // Broken state file: like "no state yet", the next run rewrites it.
        }
    }
}
