<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WarningsLayer;

use CommonSight\Model\Item\Awareness;
use CommonSight\Model\Item\Severity;
use CommonSight\Model\Item\WarningItem;

/**
 * A warning on one scale for all official sources, after the colours of the DWD (concept: situation level, L-D2): the
 * colour level where the source names one (GeoSphere, MeteoAlarm: yellow is Moderate there), otherwise the CAP severity
 * as the DWD, NINA, AT-Alert and Alertswiss read it (yellow is Minor).
 */
enum WarningGrade: int
{
    case Yellow = 1;
    case Orange = 2;
    case Red = 3;
    case Violet = 4;

    public static function of(WarningItem $warning): self
    {
        if ($warning->awareness !== null) {
            return match ($warning->awareness) {
                Awareness::Yellow => self::Yellow,
                Awareness::Orange => self::Orange,
                Awareness::Red => self::Red,
            };
        }

        return match ($warning->severity) {
            Severity::Extreme => self::Violet,
            Severity::Severe => self::Red,
            Severity::Moderate => self::Orange,
            Severity::Minor, Severity::Unknown => self::Yellow,
        };
    }

    public function key(): string
    {
        return strtolower($this->name);
    }
}
