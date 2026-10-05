<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

/** Kind of a warning text section. */
enum SectionHeading: string
{
    case Description = 'description';
    case Situation = 'situation';
    case Impact = 'impact';
    case Advice = 'advice';
    case Update = 'update';
}
