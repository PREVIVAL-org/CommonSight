<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Sdk\Plugin\SourceOutcome;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourceRun;

/** A plugin of the common shape: its run is the RequestParseMap of its parts; a factory needs no plugin class of its own. */
final class StandardSourcePlugin implements SourcePlugin
{
    public function __construct(private readonly RequestParseMap $flow) {}

    public function fetch(SourceRun $run): SourceOutcome
    {
        return $this->flow->run($run);
    }
}
