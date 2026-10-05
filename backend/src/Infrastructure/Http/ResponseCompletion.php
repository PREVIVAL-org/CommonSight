<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Http;

/** Finishes the response to the browser before the fallback reloads (fastcgi_finish_request or substitute, Architecture 6.3). */
final class ResponseCompletion
{
    public function finish(int $timeLimitSec): void
    {
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        set_time_limit($timeLimitSec);
    }

    /** Without FPM the response must state its length and close the connection so that the browser does not wait. */
    public function needsExplicitClose(): bool
    {
        return !function_exists('fastcgi_finish_request');
    }
}
