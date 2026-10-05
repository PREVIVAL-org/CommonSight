<?php

declare(strict_types=1);

namespace CommonSight\Domain\Status;

/** Derives the ETag of the status response from the state of the layers and decides between 200 and 304 (U-52). */
final class ConditionalResponse
{
    /** @param array<string, mixed> $layers part of the response without serverTime */
    public function etag(array $layers): string
    {
        return '"' . substr(hash('sha256', json_encode($layers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), 0, 20) . '"';
    }

    public function isNotModified(?string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === null || $ifNoneMatch === '') {
            return false;
        }
        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $tag = $this->normalized(trim($candidate));
            if ($tag === '*' || $tag === $etag) {
                return true;
            }
        }

        return false;
    }

    /**
     * Without the prefix for weak ETags and without the suffix Apache appends when compressing: mod_deflate turns
     * "abc" into "abc-gzip", mod_brotli into "abc-br" (DeflateAlterETag cannot be switched off via .htaccess).
     * The browser sends back the modified ETag; without this comparison there would never be a 304.
     */
    private function normalized(string $tag): string
    {
        $tag = str_starts_with($tag, 'W/') ? substr($tag, 2) : $tag;

        return preg_replace('/-(gzip|br|deflate)"$/', '"', $tag) ?? $tag;
    }
}
