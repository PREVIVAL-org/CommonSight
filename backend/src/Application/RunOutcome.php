<?php

declare(strict_types=1);

namespace CommonSight\Application;

/** Outcome of a layer run. */
enum RunOutcome: string
{
    case Updated = 'updated';
    case Unchanged = 'unchanged';
    case Failed = 'failed';
    case Locked = 'locked';
}
