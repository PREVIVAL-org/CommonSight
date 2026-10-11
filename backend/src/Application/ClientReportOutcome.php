<?php

declare(strict_types=1);

namespace CommonSight\Application;

/** What became of an error report (Architecture 6.6). */
enum ClientReportOutcome
{
    case Accepted;
    /** the visitor is not admitted to the start page */
    case Refused;
    case Invalid;
    /** the log of the day is full */
    case Full;
}
