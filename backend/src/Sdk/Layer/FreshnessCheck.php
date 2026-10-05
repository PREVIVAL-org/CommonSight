<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Layer;

use CommonSight\Model\Assessment;
use CommonSight\Model\Level;
use CommonSight\Model\Msg;
use CommonSight\Model\PreviousAssessment;
use CommonSight\Model\Value\UtcInstant;

/** Checks whether an assessment is still valid for its measurement time (B-01, B-03, shared test cases Architecture 3.4). */
final class FreshnessCheck
{
    /** Measurement times up to 15 minutes in the future still count as plausible. */
    private const FUTURE_TOLERANCE_SEC = 900;

    /** Re-checks a delivered assessment against the current time. */
    public function recheck(Assessment $assessment, UtcInstant $now): FreshnessState
    {
        if ($assessment->level === Level::Unknown || $assessment->validUntil === null) {
            return FreshnessState::Missing;
        }

        return $now->isBefore($assessment->validUntil) ? FreshnessState::Current : FreshnessState::Expired;
    }

    /** On fetch, sets the validity from measurement time and maximum age and classifies stale values as unknown. */
    public function atFetch(Assessment $assessment, ?UtcInstant $measuredAt, int $maxAgeHours, UtcInstant $now): Assessment
    {
        if ($measuredAt === null || $measuredAt->isAfter($now->plusSeconds(self::FUTURE_TOLERANCE_SEC))) {
            return new Assessment(
                Level::Unknown,
                new Msg('assessment.label.none'),
                new Msg('assessment.basis.timeMissing'),
                $assessment->origin,
                null,
                $assessment->sourceValue,
            );
        }
        $dated = $assessment->withValidUntil($measuredAt->plusSeconds($maxAgeHours * 3600));
        if ($this->recheck($dated, $now) !== FreshnessState::Expired) {
            return $dated;
        }

        return new Assessment(
            Level::Unknown,
            new Msg('assessment.label.none'),
            new Msg('assessment.basis.stale', ['hours' => $maxAgeHours]),
            $assessment->origin,
            $dated->validUntil,
            $assessment->sourceValue,
            new PreviousAssessment($assessment->level, $assessment->label),
        );
    }
}
