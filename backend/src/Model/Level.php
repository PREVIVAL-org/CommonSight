<?php

declare(strict_types=1);

namespace CommonSight\Model;

/** Level of an assessment (display category, not an official warning level). */
enum Level: string
{
    case Normal = 'normal';
    case Elevated = 'elevated';
    case High = 'high';
    case Unknown = 'unknown';
}
