<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Plugin;

use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Model\Decoded;
use CommonSight\Model\Place\RegionCodes;
use CommonSight\Port\PluginData;

/** Master data for one plugin: JSON files of its own data/ folder and the places of the core. */
final class FilePluginData implements PluginData
{
    public function __construct(private readonly string $dataDirectory, private readonly GeneratedData $core) {}

    public function readOwnJson(string $file): Decoded
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.json$/', $file) !== 1) {
            throw new \RuntimeException('Invalid name of a data file: ' . $file);
        }
        $path = $this->dataDirectory . '/' . $file;
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new \RuntimeException('Data file of the plugin missing: ' . $path);
        }
        try {
            return Decoded::of(json_decode($contents, true, 512, JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            throw new \RuntimeException('Data file of the plugin is not valid JSON: ' . $path, 0, $e);
        }
    }

    public function cities(): array
    {
        return $this->core->cities();
    }

    public function borderPlaces(): array
    {
        return $this->core->borderPlaces();
    }

    public function regionCodes(): RegionCodes
    {
        return $this->core->regionCodes();
    }

    public function countries(): array
    {
        return $this->core->countries();
    }
}
