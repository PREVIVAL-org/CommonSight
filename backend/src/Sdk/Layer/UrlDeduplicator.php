<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Value\UtcInstant;

/** Of items with the same link, keeps only the first (duplicates in news, Q-NE-03). */
final class UrlDeduplicator implements PipelineStep
{
    public function apply(array $items, UtcInstant $now): array
    {
        $unique = [];
        foreach ($items as $item) {
            $unique[$item->common()->url] ??= $item;
        }

        return array_values($unique);
    }
}
