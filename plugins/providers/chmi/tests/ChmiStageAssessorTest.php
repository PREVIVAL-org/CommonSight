<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Chmi\Tests;

use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Plugin\Chmi\ChmiStageAssessor;
use CommonSight\Sdk\Station\FloodStages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** ADR 0038: ČHMÚ gauges by their flood stages SPA 1-3. */
final class ChmiStageAssessorTest extends TestCase
{
    private const LABEL = 'source.chmi.assessment.label.';

    /** @return iterable<string, array{?float, Level, string}> */
    public static function cases(): iterable
    {
        yield 'below SPA 1' => [120.0, Level::Normal, self::LABEL . 'czBelowSpa'];
        yield 'SPA 1 reached' => [165.0, Level::Elevated, self::LABEL . 'czSpa1'];
        yield 'SPA 2' => [210.0, Level::Elevated, self::LABEL . 'czSpa2'];
        yield 'SPA 3' => [250.0, Level::High, self::LABEL . 'czSpa3'];
        yield 'discharge missing' => [null, Level::Unknown, 'assessment.label.none'];
    }

    #[DataProvider('cases')]
    public function testGauges(?float $value, Level $level, string $label): void
    {
        $assessment = (new ChmiStageAssessor())->assess(new FloodStages([165.0, 200.0, 220.0], $value, 'cm', 'SPA', 'ČHMÚ'));

        self::assertSame($level, $assessment->level);
        self::assertSame($label, $assessment->label->key);
        self::assertSame(AssessmentOrigin::Source, $assessment->origin);
        self::assertSame('SPA 165 / 200 / 220 cm', $assessment->sourceValue, 'D-21: stages of the source stay visible');
    }
}
