<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Chmi;

use CommonSight\Model\Assessment;
use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Model\Msg;
use CommonSight\Sdk\Station\FloodStageAssessor;
use CommonSight\Sdk\Station\FloodStages;

/** Classifies a ČHMÚ gauge by its flood stages (SPA): SPA 1-2 elevated, SPA 3 high (ADR 0038). */
final class ChmiStageAssessor implements FloodStageAssessor
{
    private const TEXTS = 'source.chmi.assessment.';

    public function assess(FloodStages $stages): Assessment
    {
        $value = $stages->compared;
        if ($value === null) {
            return new Assessment(Level::Unknown, new Msg('assessment.label.none'), new Msg(self::TEXTS . 'basis.czMissing'), AssessmentOrigin::Source, null, $stages->describe());
        }
        $reached = count(array_filter($stages->thresholds, static fn(?float $t): bool => $t !== null && $value >= $t));
        if ($reached === 0) {
            return $this->result(Level::Normal, 'czBelowSpa', new Msg(self::TEXTS . 'basis.czBelowSpa'), $stages);
        }

        return $this->result($reached >= 3 ? Level::High : Level::Elevated, 'czSpa' . $reached, new Msg(self::TEXTS . 'basis.czSpa', ['stage' => $reached]), $stages);
    }

    private function result(Level $level, string $label, Msg $basis, FloodStages $stages): Assessment
    {
        return new Assessment($level, new Msg(self::TEXTS . 'label.' . $label), $basis, AssessmentOrigin::Source, null, $stages->describe());
    }
}
