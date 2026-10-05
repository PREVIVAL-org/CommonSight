<?php

declare(strict_types=1);

namespace CommonSight\Model\Http;

/** Kind of an HTTP failure. */
enum FailureKind: string
{
    case Timeout = 'timeout';
    case Network = 'network';
    case ClientError = 'clientError';
    /** HTTP 429: not retried within the run; the waiting time feeds the backoff of the source. */
    case RateLimited = 'rateLimited';
    case ServerError = 'serverError';
    case TooLarge = 'tooLarge';
    case BudgetExhausted = 'budgetExhausted';

    public function isRetryable(): bool
    {
        return $this === self::Timeout || $this === self::ServerError;
    }
}
