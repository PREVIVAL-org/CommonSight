<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere;

use CommonSight\Plugin\GeoSphere\Record\DetailWarning;
use CommonSight\Plugin\GeoSphere\Record\WarnFeature;

/**
 * Finds exactly the message for the warning in a detail response: same type, same level, same start and
 * same end, and for a warnid in the format w{warnid}c{chgid}v{verlaufid} also these three parts (Q-W-AT-07).
 */
final class GeoSphereDetailMatcher
{
    /** @param list<DetailWarning> $candidates */
    public function match(WarnFeature $warning, array $candidates): ?DetailWarning
    {
        foreach ($candidates as $candidate) {
            if ($this->matches($warning, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function matches(WarnFeature $warning, DetailWarning $candidate): bool
    {
        if ($candidate->wtype !== $warning->wtype || $candidate->wlevel !== $warning->wlevel
            || $candidate->start !== $warning->start || $candidate->end !== $warning->end) {
            return false;
        }
        if (preg_match('/^w(\d+)c(\d+)v(\d+)$/D', $warning->warnid, $parts) !== 1) {
            return true;
        }

        return $candidate->warnid === $parts[1] && $candidate->chgid === $parts[2] && $candidate->verlaufid === $parts[3];
    }
}
