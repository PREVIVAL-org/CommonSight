<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

/** Kind of gap; the value is also the key part of the issue (issue.<value>), except for Own. */
enum DeficitKind: string
{
    case RequestsFailed = 'requestsFailed';
    case PagingIncomplete = 'pagingIncomplete';
    case MissingDetailText = 'missingDetailText';
    case MissingGeometry = 'missingGeometry';
    case InvalidRecords = 'invalidRecords';
    case FormatDrift = 'formatDrift';
    case BelowExpected = 'belowExpected';
    /** a gap only the source knows, with a text of its own (Deficit::own) */
    case Own = 'own';
}
