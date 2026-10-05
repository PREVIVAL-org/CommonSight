<?php

declare(strict_types=1);

namespace CommonSight\Model\Stats;

/** Key figures of a layer without its own key figures; serialized as an empty object. */
final readonly class EmptyStats implements LayerStats
{
    public function jsonSerialize(): \stdClass
    {
        return new \stdClass();
    }
}
