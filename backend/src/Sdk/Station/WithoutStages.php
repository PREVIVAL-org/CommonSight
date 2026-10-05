<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Station;

use CommonSight\Model\Assessment;
use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Model\Msg;

/** For sources without flood stages (Hub'Eau, Rijkswaterstaat): the gauge stays unassessed, only the value is shown. */
final class WithoutStages implements FloodStageAssessor
{
    public function assess(FloodStages $stages): Assessment
    {
        return new Assessment(Level::Unknown, new Msg('assessment.label.none'), new Msg('assessment.basis.noStages', ['source' => $stages->source]), AssessmentOrigin::Source, null, null);
    }
}
