<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Station;

use CommonSight\Model\Assessment;

/** Classifies a gauge by the flood stages of its source; source knowledge, so each plugin brings its own (ADR 0038). */
interface FloodStageAssessor
{
    public function assess(FloodStages $stages): Assessment;
}
