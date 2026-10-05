<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Recording;

use CommonSight\Model\Http\ResponseHeaders;
use CommonSight\Model\Value\UtcInstant;

/**
 * Recorded responses of one plugin for one scope, as files: <dir>/index.json lists the requests with status and headers
 * and the time of the recording; the bodies lie next to it (001.json, 002.xml, ...). A run replayed at the time of the
 * recording builds the same requests, also those with time windows.
 */
final class Cassette
{
    /** @param list<RecordedExchange> $exchanges */
    public function __construct(public readonly UtcInstant $recordedAt, public readonly array $exchanges) {}

    public static function read(string $dir): ?self
    {
        $index = is_file($dir . '/index.json') ? json_decode((string) file_get_contents($dir . '/index.json'), true) : null;
        if (!is_array($index) || !is_string($index['recordedAt'] ?? null) || !is_array($index['requests'] ?? null)) {
            return null;
        }
        $exchanges = [];
        foreach ($index['requests'] as $entry) {
            if (!is_array($entry) || !is_string($entry['signature'] ?? null) || !is_string($entry['file'] ?? null)) {
                throw new \RuntimeException('Invalid entry in ' . $dir . '/index.json');
            }
            $headers = is_array($entry['headers'] ?? null) ? $entry['headers'] : [];
            $exchanges[] = new RecordedExchange(
                $entry['signature'],
                is_int($entry['status'] ?? null) ? $entry['status'] : 200,
                new ResponseHeaders(self::text($headers, 'etag'), self::text($headers, 'lastModified'), null, self::text($headers, 'link'), self::text($headers, 'contentType')),
                (string) file_get_contents($dir . '/' . basename($entry['file'])),
            );
        }

        return new self(UtcInstant::fromIso($index['recordedAt']), $exchanges);
    }

    public function write(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create ' . $dir);
        }
        foreach (glob($dir . '/[0-9][0-9][0-9].*') ?: [] as $old) {
            unlink($old);
        }
        $requests = [];
        // Identical bodies (e.g. many gauges out of operation) share one file.
        $files = [];
        foreach ($this->exchanges as $exchange) {
            $hash = sha1($exchange->body);
            if (!isset($files[$hash])) {
                $files[$hash] = sprintf('%03d.%s', count($files) + 1, self::extensionOf($exchange->body));
                file_put_contents($dir . '/' . $files[$hash], $exchange->body);
            }
            $file = $files[$hash];
            $requests[] = [
                'signature' => $exchange->signature,
                'status' => $exchange->status,
                'headers' => (object) array_filter(['etag' => $exchange->headers->etag, 'lastModified' => $exchange->headers->lastModified, 'link' => $exchange->headers->link, 'contentType' => $exchange->headers->contentType]),
                'file' => $file,
            ];
        }
        $index = ['recordedAt' => $this->recordedAt->toIso(), 'requests' => $requests];
        file_put_contents($dir . '/index.json', json_encode($index, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }

    private static function extensionOf(string $body): string
    {
        json_decode($body);
        if (json_last_error() === JSON_ERROR_NONE) {
            return 'json';
        }

        return str_starts_with(ltrim($body, "\xEF\xBB\xBF \t\r\n"), '<') ? 'xml' : 'txt';
    }

    /** @param array<mixed> $values */
    private static function text(array $values, string $key): ?string
    {
        return is_string($values[$key] ?? null) ? $values[$key] : null;
    }
}
