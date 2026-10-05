<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Storage;

use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Port\StateReader;

/**
 * Deletes old snapshot versions: per layer the current and the last 3 versions remain; older ones are deleted
 * once they have not been current for more than 15 minutes (Architecture 4.6). Snapshots of layers that no longer
 * exist are deleted after the same 15 minutes, and so are compressed variants left without their JSON by a writer that
 * died in between (the JSON is written last).
 */
final class SnapshotCleaner
{
    private const KEEP = 3;
    private const GRACE_SEC = 900;

    public function __construct(private readonly string $dataDir, private readonly StateReader $states) {}

    /**
     * @param list<LayerTarget> $targets
     * @return int number of deleted versions
     */
    public function clean(array $targets, int $now): int
    {
        $deleted = 0;
        foreach ($targets as $target) {
            $current = $this->states->read($target->layer, $target->scope)?->file;
            $deleted += $this->cleanLayer($target, $current !== null ? basename($current) : null, $now);
        }

        return $deleted + $this->cleanRemovedLayers($targets, $now) + $this->cleanOrphans($now);
    }

    /**
     * Snapshots of a layer (or scope of a layer) that no longer exists, e.g. after its package was removed: nothing
     * serves them any more, but they would stay downloadable. Deleted after the same grace period.
     *
     * @param list<LayerTarget> $targets
     */
    private function cleanRemovedLayers(array $targets, int $now): int
    {
        $known = array_fill_keys(array_map(static fn(LayerTarget $t): string => $t->scope->value . '/' . $t->layer->value, $targets), true);
        $deleted = 0;
        foreach (glob($this->dataDir . '/*/*.json') ?: [] as $file) {
            if (preg_match('#/([a-z]+|[A-Z]{2})/([a-z][a-z0-9-]*)\.[0-9a-f]+\.json$#', $file, $m) !== 1 || isset($known[$m[1] . '/' . $m[2]])) {
                continue;
            }
            if ($now - (int) filemtime($file) < self::GRACE_SEC) {
                continue;
            }
            $this->delete($file);
            $deleted++;
        }

        return $deleted;
    }

    private function cleanOrphans(int $now): int
    {
        $deleted = 0;
        foreach ([...glob($this->dataDir . '/*/*.json.gz') ?: [], ...glob($this->dataDir . '/*/*.json.br') ?: []] as $variant) {
            if (is_file(substr($variant, 0, -3)) || $now - (int) filemtime($variant) < self::GRACE_SEC) {
                continue;
            }
            unlink($variant);
            $deleted++;
        }

        return $deleted;
    }

    private function cleanLayer(LayerTarget $target, ?string $current, int $now): int
    {
        $files = glob(sprintf('%s/%s/%s.*.json', $this->dataDir, $target->scope->value, $target->layer->value)) ?: [];
        $mtimes = [];
        foreach ($files as $file) {
            $mtimes[$file] = (int) filemtime($file);
        }
        arsort($mtimes);
        $ordered = array_keys($mtimes);
        $deleted = 0;
        foreach ($ordered as $position => $file) {
            $replacedAt = $position > 0 ? $mtimes[$ordered[$position - 1]] : $now;
            if ($position < self::KEEP || basename($file) === $current || $now - $replacedAt < self::GRACE_SEC) {
                continue;
            }
            $this->delete($file);
            $deleted++;
        }

        return $deleted;
    }

    private function delete(string $file): void
    {
        foreach ([$file, $file . '.gz', $file . '.br'] as $variant) {
            if (is_file($variant)) {
                unlink($variant);
            }
        }
    }
}
