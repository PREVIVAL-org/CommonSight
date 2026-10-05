<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk;

use CommonSight\Model\Level;
use CommonSight\Sdk\Station\FloodStages;
use CommonSight\Sdk\Station\WithoutStages;
use PHPUnit\Framework\TestCase;

/** ADR 0038: a gauge of a source without flood stages stays unassessed and names its source. */
final class WithoutStagesTest extends TestCase
{
    public function testTheSourceIsNamed(): void
    {
        $assessment = (new WithoutStages())->assess(FloodStages::none('Rijkswaterstaat'));

        self::assertSame(Level::Unknown, $assessment->level);
        self::assertSame('assessment.basis.noStages', $assessment->basis->key);
        self::assertSame(['source' => 'Rijkswaterstaat'], $assessment->basis->params);
    }
}
