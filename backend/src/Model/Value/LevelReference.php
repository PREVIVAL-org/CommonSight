<?php

declare(strict_types=1);

namespace CommonSight\Model\Value;

/** Reference height of a water level. */
enum LevelReference: string
{
    case GaugeZero = 'gaugeZero';
    case SeaLevel = 'seaLevel';
}
