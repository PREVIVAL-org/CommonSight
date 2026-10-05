<?php

declare(strict_types=1);

namespace CommonSight\Tests\Support;

use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Model\Http\HttpRequest;
use CommonSight\Model\Http\HttpResponse;

/** Loads recorded source responses, shared test cases and the generated master data for tests. */
final class Fixtures
{
    public static function backendDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function contractDir(): string
    {
        return dirname(__DIR__, 3) . '/contract';
    }

    /** Response of a test case of a plugin, from plugins/<group>/<source>/tests/cases/<file>. */
    public static function response(string $source, string $file, string $url = 'https://example.org/'): HttpResponse
    {
        return self::body(self::caseBody($source, $file), $source, $url);
    }

    /** Body of a test case of a plugin (edge cases next to its recordings): plugins/<group>/<source>/tests/cases/<file>. */
    public static function caseBody(string $source, string $file): string
    {
        $path = self::pluginDir($source) . '/tests/cases/' . $file;
        $body = is_file($path) ? file_get_contents($path) : false;
        if ($body === false) {
            throw new \RuntimeException('Test case missing: ' . $path);
        }

        return $body;
    }

    /** The folder of a plugin in the repository, whatever its group: plugins/<group>/<id>. */
    public static function pluginDir(string $id): string
    {
        $dirs = glob(dirname(self::backendDir()) . '/plugins/*/' . $id, GLOB_ONLYDIR) ?: [];

        return $dirs[0] ?? throw new \RuntimeException('No plugin ' . $id . ' in plugins/<group>/');
    }

    public static function body(string $body, string $sourceId = 'test', string $url = 'https://example.org/'): HttpResponse
    {
        return new HttpResponse(new HttpRequest($url, 'application/json', $sourceId), 200, $body);
    }

    /** @param array<mixed>|list<mixed> $data */
    public static function json(array $data, string $sourceId = 'test'): HttpResponse
    {
        return self::body(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $sourceId);
    }

    /** @return array<mixed> */
    public static function contractJson(string $relative): array
    {
        $data = json_decode((string) file_get_contents(self::contractDir() . '/' . $relative), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Not a JSON array: ' . $relative);
        }

        return $data;
    }

    /** @return array<mixed> JSON file relative to backend/ */
    public static function contractJsonFromBackend(string $relative): array
    {
        $data = json_decode((string) file_get_contents(self::backendDir() . '/' . $relative), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Not a JSON array: ' . $relative);
        }

        return $data;
    }

    public static function generated(): GeneratedData
    {
        return new GeneratedData(self::backendDir() . '/generated');
    }
}
