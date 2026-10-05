<?php

declare(strict_types=1);

namespace CommonSight\Model;

/** Origin of an assessment: classification by the source or our own display threshold. */
enum AssessmentOrigin: string
{
    case Source = 'source';
    case Display = 'display';
}
