<?php

declare(strict_types=1);

namespace CommonSight\Plugin\GeoSphere;

use CommonSight\Model\Item\Awareness;
use CommonSight\Model\Item\Severity;

/** Translates GeoSphere warning type and warning level into warning kind, color level and severity (Q-W-AT-03). */
final class GeoSphereWarnType
{
    private const HAZARDS = [1 => 'wind', 2 => 'rain', 3 => 'snow', 4 => 'ice', 5 => 'thunderstorm', 6 => 'heat', 7 => 'cold'];
    private const NAMES = [1 => 'Wind', 2 => 'Regen', 3 => 'Schnee', 4 => 'Glatteis', 5 => 'Gewitter', 6 => 'Hitze', 7 => 'Kälte'];
    private const LEVEL_NAMES = [1 => 'Gelb', 2 => 'Orange', 3 => 'Rot'];

    public function hazardKey(int $wtype): string
    {
        return 'hazard.' . (self::HAZARDS[$wtype] ?? throw new \InvalidArgumentException('Unknown warning type ' . $wtype));
    }

    /** Title in the source's language, e.g. "Gewitterwarnung · Orange". */
    public function title(int $wtype, int $wlevel): string
    {
        return (self::NAMES[$wtype] ?? '') . 'warnung · ' . (self::LEVEL_NAMES[$wlevel] ?? '');
    }

    public function severity(int $wlevel): Severity
    {
        return match ($wlevel) {
            3 => Severity::Extreme,
            2 => Severity::Severe,
            1 => Severity::Moderate,
            default => Severity::Unknown,
        };
    }

    public function awareness(int $wlevel): ?Awareness
    {
        return match ($wlevel) {
            3 => Awareness::Red,
            2 => Awareness::Orange,
            1 => Awareness::Yellow,
            default => null,
        };
    }
}
