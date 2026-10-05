<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

/**
 * A source of data as a plugin: fetches its data for a scope as items of the internal model. It does its own API calls
 * through the HTTP client of its environment; its description comes from its factory; the core knows no source.
 */
interface SourcePlugin
{
    /** Fetches the source for one scope. Expected failures are returned as SourceOutcome::failure(), not thrown. */
    public function fetch(SourceRun $run): SourceOutcome;
}
