<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Imgw;

use CommonSight\Model\Assessment;
use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Model\Msg;
use CommonSight\Sdk\Station\FloodStageAssessor;
use CommonSight\Sdk\Station\FloodStages;

/** Classifies an IMGW-PIB gauge by its warning level (elevated) and alarm level (high) (ADR 0038). */
final class ImgwStageAssessor implements FloodStageAssessor
{
    private const TEXTS = 'source.imgw.assessment.';

    public function assess(FloodStages $stages): Assessment
    {
        [$warning, $alarm] = $stages->thresholds + [null, null];
        $value = (float) $stages->compared;
        if ($warning === null && $alarm === null) {
            return new Assessment(Level::Unknown, new Msg('assessment.label.none'), new Msg(self::TEXTS . 'basis.plMissing'), AssessmentOrigin::Source, null, $stages->describe());
        }

        return match (true) {
            $alarm !== null && $value >= $alarm => $this->result(Level::High, 'plAlarm', $stages),
            $warning !== null && $value >= $warning => $this->result(Level::Elevated, 'plWarning', $stages),
            default => $this->result(Level::Normal, 'plBelow', $stages),
        };
    }

    private function result(Level $level, string $key, FloodStages $stages): Assessment
    {
        return new Assessment($level, new Msg(self::TEXTS . 'label.' . $key), new Msg(self::TEXTS . 'basis.' . $key), AssessmentOrigin::Source, null, $stages->describe());
    }
}
