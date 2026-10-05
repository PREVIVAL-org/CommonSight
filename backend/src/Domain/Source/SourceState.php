<?php

declare(strict_types=1);

namespace CommonSight\Domain\Source;

/** State of a source in a run, as the assembly of its layer sees it. */
enum SourceState
{
    case Succeeded;
    case Failed;
    case Disabled;
    /** a secret it needs (e.g. an API key) is not configured; shown as "to be set up" */
    case Unconfigured;
    /** no outcome yet, e.g. right after an update, until its lane has run it */
    case Pending;
}
