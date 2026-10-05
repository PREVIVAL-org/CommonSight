<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Http;

use CommonSight\Model\Http\ResponseHeaders;

/** Reads the usable headers from the raw header lines of the final response; Retry-After as seconds or as HTTP date. */
final class ResponseHeaderParser
{
    /** @param list<string> $lines header lines of the final response, without the status line */
    public function parse(array $lines, int $now): ResponseHeaders
    {
        $values = [];
        foreach ($lines as $line) {
            $colon = strpos($line, ':');
            if ($colon !== false) {
                $values[strtolower(trim(substr($line, 0, $colon)))] = trim(substr($line, $colon + 1));
            }
        }

        return new ResponseHeaders(
            self::nonEmpty($values['etag'] ?? null),
            self::nonEmpty($values['last-modified'] ?? null),
            self::retryAfter(self::nonEmpty($values['retry-after'] ?? null), $now),
            self::nonEmpty($values['link'] ?? null),
            self::nonEmpty($values['content-type'] ?? null),
        );
    }

    private static function retryAfter(?string $value, int $now): ?int
    {
        if ($value === null) {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $time = strtotime($value);

        return $time === false ? null : max(0, $time - $now);
    }

    private static function nonEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
