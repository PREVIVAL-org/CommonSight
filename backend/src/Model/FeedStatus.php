<?php

declare(strict_types=1);

namespace CommonSight\Model;

/** State of a layer (D-02). */
enum FeedStatus: string
{
    case Ok = 'ok';
    case Partial = 'partial';
    case Error = 'error';
    case Setup = 'setup';
}
