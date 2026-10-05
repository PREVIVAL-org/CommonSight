<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

/** Result of the freshness check of an assessment. */
enum FreshnessState: string
{
    case Current = 'current';
    case Expired = 'expired';
    case Missing = 'missing';
}
