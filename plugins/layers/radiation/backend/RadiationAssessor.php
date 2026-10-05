<?php

declare(strict_types=1);

namespace CommonSight\Plugin\RadiationLayer;

use CommonSight\Model\Assessment;
use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\DoseRate;

/** Classifies an ambient dose rate by our own display thresholds (B-13); not an official alert level. */
final class RadiationAssessor
{
    public function __construct(private readonly RadiationThresholds $thresholds) {}

    public function assess(?DoseRate $doseRate): Assessment
    {
        $basisParams = ['elevated' => $this->thresholds->elevated, 'high' => $this->thresholds->high];
        if ($doseRate === null) {
            return new Assessment(Level::Unknown, new Msg('assessment.label.none'), new Msg('layer.radiation.assessment.invalid'), AssessmentOrigin::Display);
        }
        $value = $doseRate->microSievertPerHour;
        [$level, $label] = match (true) {
            $value >= $this->thresholds->high => [Level::High, 'high'],
            $value >= $this->thresholds->elevated => [Level::Elevated, 'elevated'],
            default => [Level::Normal, 'normal'],
        };

        return new Assessment($level, new Msg('layer.radiation.assessment.' . $label), new Msg('layer.radiation.assessment.basis', $basisParams), AssessmentOrigin::Display);
    }
}
