<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Storage;

use CommonSight\Model\State\LayerState;
use CommonSight\Port\StateWriter;

/** Writes state/<scope>/<layer>.json atomically. */
final class StateFileWriter implements StateWriter
{
    public function __construct(
        private readonly string $stateDir,
        private readonly AtomicFile $files,
        private readonly LayerStateCodec $codec,
    ) {}

    public function write(LayerState $state): void
    {
        $this->files->write(sprintf('%s/%s/%s.json', $this->stateDir, $state->scope->value, $state->layer->value), $this->codec->encode($state));
    }
}
