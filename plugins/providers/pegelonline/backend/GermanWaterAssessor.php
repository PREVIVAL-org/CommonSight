<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Pegelonline;

use CommonSight\Model\Assessment;
use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Model\Msg;

/**
 * Classifies a PEGELONLINE water level by the station states stateMnwMhw and stateNswHsw (B-10).
 *
 * HSW and MHW are evaluated independently, because HSW can be below MHW.
 */
final class GermanWaterAssessor
{
    private const DISTURBED = ['commented', 'out-dated'];

    public function assess(?string $mnwMhw, ?string $nswHsw): Assessment
    {
        $sourceValue = sprintf('MHW: %s · HSW: %s', $mnwMhw ?? '-', $nswHsw ?? '-');
        if (in_array($mnwMhw, self::DISTURBED, true) || in_array($nswHsw, self::DISTURBED, true)) {
            return $this->result(Level::Unknown, 'none', 'deDisturbed', $sourceValue);
        }

        return match (true) {
            $nswHsw === 'high' => $this->result(Level::High, 'deHsw', 'deHsw', $sourceValue),
            $mnwMhw === 'high' => $this->result(Level::Elevated, 'deMhw', 'deMhw', $sourceValue),
            $mnwMhw === 'normal' => $this->result(Level::Normal, 'deBelowMhw', 'deBelowMhw', $sourceValue),
            $mnwMhw === 'low' => $this->result(Level::Normal, 'deLowWater', 'deBelowMhw', $sourceValue),
            $nswHsw === 'normal' => $this->result(Level::Normal, 'deBelowHsw', 'deBelowHsw', $sourceValue),
            default => $this->result(Level::Unknown, 'none', 'deNoState', $sourceValue),
        };
    }

    private function result(Level $level, string $label, string $basis, string $sourceValue): Assessment
    {
        return new Assessment(
            $level,
            new Msg($label === 'none' ? 'assessment.label.none' : 'source.pegelonline.assessment.label.' . $label),
            new Msg('source.pegelonline.assessment.basis.' . $basis),
            AssessmentOrigin::Source,
            null,
            $sourceValue,
        );
    }
}
