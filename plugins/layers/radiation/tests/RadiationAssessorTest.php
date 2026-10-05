<?php

declare(strict_types=1);

namespace CommonSight\Plugin\RadiationLayer\Tests;

use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Model\Value\DoseRate;
use CommonSight\Plugin\RadiationLayer\RadiationAssessor;
use CommonSight\Plugin\RadiationLayer\RadiationThresholds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** B-13, B-14: display thresholds of the ambient dose rate, units normalized. */
final class RadiationAssessorTest extends TestCase
{
    /** @return iterable<string, array{float, string, Level}> */
    public static function cases(): iterable
    {
        yield 'normal µSv/h' => [0.089, 'µSv/h', Level::Normal];
        yield 'exactly elevated' => [0.3, 'µSv/h', Level::Elevated];
        yield 'exactly high' => [1.0, 'µSv/h', Level::High];
        yield 'nSv/h normal' => [95.0, 'nSv/h', Level::Normal];
        yield 'nSv/h elevated' => [310.0, 'nSv/h', Level::Elevated];
        yield 'Greek μ' => [0.5, 'μSv/h', Level::Elevated];
        yield 'uSv/h' => [2.0, 'uSv/h', Level::High];
        yield 'mSv/h' => [0.002, 'mSv/h', Level::High];
        yield 'Sv/h' => [0.0000001, 'Sv/h', Level::Normal];
        yield 'unknown unit' => [0.1, 'Gy', Level::Unknown];
        yield 'negative' => [-0.1, 'µSv/h', Level::Unknown];
    }

    #[DataProvider('cases')]
    public function testClassifiesByDisplayThresholds(float $value, string $unit, Level $level): void
    {
        $assessment = (new RadiationAssessor(new RadiationThresholds()))->assess(DoseRate::fromValueAndUnit($value, $unit));

        self::assertSame($level, $assessment->level);
        self::assertSame(AssessmentOrigin::Display, $assessment->origin);
    }

    public function testBasisNamesConfiguredThresholds(): void
    {
        $assessment = (new RadiationAssessor(new RadiationThresholds(0.25, 0.8)))->assess(DoseRate::fromValueAndUnit(0.26, 'µSv/h'));

        self::assertSame(Level::Elevated, $assessment->level);
        self::assertSame(['elevated' => 0.25, 'high' => 0.8], $assessment->basis->params);
    }

    /** @return iterable<string, array{float, float}> */
    public static function invalidThresholds(): iterable
    {
        yield 'high below elevated' => [1.0, 0.3];
        yield 'equal' => [0.3, 0.3];
        yield 'zero' => [0.0, 1.0];
        yield 'infinite' => [0.3, INF];
    }

    #[DataProvider('invalidThresholds')]
    public function testRejectsInvalidThresholds(float $elevated, float $high): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RadiationThresholds($elevated, $high);
    }
}
