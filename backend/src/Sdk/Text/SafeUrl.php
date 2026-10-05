<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Text;

/** Lets only valid http and https links from source data through, otherwise the fallback (F-12). */
final class SafeUrl
{
    public function orFallback(mixed $url, string $fallback): string
    {
        return $this->valid($url) ?? $fallback;
    }

    /** Returns the link if it is valid, otherwise null. */
    public function valid(mixed $url): ?string
    {
        if (!is_string($url)) {
            return null;
        }
        $trimmed = trim($url);
        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $trimmed : null;
    }
}
