<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Ehyd;

use CommonSight\Model\Assessment;
use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Model\Msg;

/**
 * Classifies an eHYD water gauge by the three-digit gesamtcode (B-11).
 *
 * Hundreds = class, tens = trend, ones != 0 = limited timeliness.
 * Classes 1-2 are only valid with trend 3, classes 3-6 only with trend 0-2.
 */
final class AustrianWaterAssessor
{
    private const LEVELS = [1 => Level::Normal, 2 => Level::Normal, 3 => Level::Normal, 4 => Level::Elevated, 5 => Level::High, 6 => Level::High];

    public function assess(?int $code): Assessment
    {
        if ($code === null) {
            return $this->unknown(new Msg('source.ehyd.assessment.basis.atMissing'), null);
        }
        $class = intdiv($code, 100);
        $trend = intdiv($code % 100, 10);
        if ($code % 10 !== 0) {
            return $this->unknown(new Msg('source.ehyd.assessment.basis.atReducedFreshness', ['code' => $code]), $code);
        }
        if (!isset(self::LEVELS[$class]) || !in_array($trend, $class <= 2 ? [3] : [0, 1, 2], true)) {
            return $this->unknown(new Msg('source.ehyd.assessment.basis.atUnknownCode', ['code' => $code]), $code);
        }

        return new Assessment(
            self::LEVELS[$class],
            new Msg('source.ehyd.assessment.label.atClass' . $class),
            new Msg('source.ehyd.assessment.basis.atClass' . $class),
            AssessmentOrigin::Source,
            null,
            $code,
        );
    }

    private function unknown(Msg $basis, ?int $code): Assessment
    {
        return new Assessment(Level::Unknown, new Msg('assessment.label.none'), $basis, AssessmentOrigin::Source, null, $code);
    }
}
