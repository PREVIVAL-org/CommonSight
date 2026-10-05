<?php

declare(strict_types=1);

namespace CommonSight\Domain\Snapshot;

use CommonSight\Model\Snapshot;

/** Serializes a snapshot and derives its version from the content without generatedAt (Architecture 4.6). */
final class ContentHash
{
    public function json(Snapshot $snapshot): string
    {
        return json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** First 8 hex characters of the SHA-256 over the content; the same content yields the same version. */
    public function version(Snapshot $snapshot): string
    {
        $content = $snapshot->jsonSerialize();
        unset($content['generatedAt']);
        $serialized = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return substr(hash('sha256', $serialized), 0, 8);
    }
}
