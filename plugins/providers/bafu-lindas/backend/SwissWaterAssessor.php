<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Lindas;

use CommonSight\Model\Assessment;
use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Model\Msg;

/** Classifies a BAFU water gauge by danger level 1-5: 1 normal, 2-3 elevated, 4-5 high (B-12). */
final class SwissWaterAssessor
{
    public function assess(?int $dangerLevel): Assessment
    {
        if ($dangerLevel === null || $dangerLevel < 1 || $dangerLevel > 5) {
            return new Assessment(
                Level::Unknown,
                new Msg('assessment.label.none'),
                new Msg('source.bafu-lindas.assessment.basis.chMissing'),
                AssessmentOrigin::Source,
                null,
                $dangerLevel,
            );
        }
        $level = match (true) {
            $dangerLevel >= 4 => Level::High,
            $dangerLevel >= 2 => Level::Elevated,
            default => Level::Normal,
        };

        return new Assessment(
            $level,
            new Msg('source.bafu-lindas.assessment.label.chLevel' . $dangerLevel),
            new Msg('source.bafu-lindas.assessment.basis.chLevel', ['level' => $dangerLevel]),
            AssessmentOrigin::Source,
            null,
            $dangerLevel,
        );
    }
}
