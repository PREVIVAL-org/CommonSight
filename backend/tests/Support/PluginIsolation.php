<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

/**
 * Finds violations of the isolation of plugins (V13): each plugin has its own namespace CommonSight\Plugin\<Name>,
 * shared by none, and refers to no other plugin. deptrac checks the rest (only Model, Sdk and ports).
 */
final class PluginIsolation
{
    private const PREFIX = 'CommonSight\\Plugin\\';

    /** @return list<string> one line per violation */
    public function violations(string $pluginsDir): array
    {
        $owners = [];
        $violations = [];
        foreach (glob($pluginsDir . '/*/*', GLOB_ONLYDIR) ?: [] as $pluginDir) {
            $files = $this->phpFiles($pluginDir . '/backend');
            $names = array_unique(array_map(fn(string $file): string => $this->pluginName($file), $files));
            $plugin = basename($pluginDir);
            if (count($names) > 1 || in_array('', $names, true)) {
                $violations[] = sprintf('%s: all classes must be in one namespace %s<Name>, found: %s', $plugin, self::PREFIX, implode(', ', $names));
                continue;
            }
            $name = $names === [] ? null : reset($names);
            if ($name !== null && isset($owners[$name])) {
                $violations[] = sprintf('%s: namespace %s%s is already used by %s', $plugin, self::PREFIX, $name, $owners[$name]);
            }
            if ($name !== null) {
                $owners[$name] = $plugin;
                array_push($violations, ...$this->foreignReferences($plugin, $name, $files));
            }
        }

        return $violations;
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    private function foreignReferences(string $plugin, string $name, array $files): array
    {
        $violations = [];
        foreach ($files as $file) {
            preg_match_all('/CommonSight\\\\Plugin\\\\([A-Za-z0-9_]+)/', (string) file_get_contents($file), $matches);
            foreach (array_unique($matches[1]) as $other) {
                if ($other !== $name) {
                    $violations[] = sprintf('%s: %s refers to the plugin %s%s', $plugin, basename($file), self::PREFIX, $other);
                }
            }
        }

        return $violations;
    }

    /** Name of the plugin namespace a file declares, '' if it declares none below CommonSight\Plugin. */
    private function pluginName(string $file): string
    {
        $found = preg_match('/^namespace CommonSight\\\\Plugin\\\\([A-Za-z0-9_]+)[\\\\;]/m', (string) file_get_contents($file), $match);

        return $found === 1 ? $match[1] : '';
    }

    /** @return list<string> */
    private function phpFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $entry) {
            if ($entry instanceof \SplFileInfo && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
