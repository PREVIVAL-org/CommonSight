<?php

declare(strict_types=1);

namespace CommonSight\Config;

/** Directories for snapshots, state files, caches, locks and logs (Architecture 7). */
final readonly class Paths
{
    public function __construct(
        public string $data,
        public string $state,
        public string $cache,
        public string $locks,
        public string $logs,
    ) {
        foreach (['data' => $data, 'state' => $state, 'cache' => $cache, 'locks' => $locks, 'logs' => $logs] as $name => $path) {
            if ($path === '' || $path[0] !== '/') {
                throw new ConfigError(sprintf('paths.%s must be an absolute path', $name));
            }
        }
    }
}
