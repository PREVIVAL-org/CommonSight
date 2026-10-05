<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Imgw\Tests;

use CommonSight\Model\Level;
use CommonSight\Plugin\Imgw\ImgwStageAssessor;
use CommonSight\Sdk\Station\FloodStages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** ADR 0038: IMGW-PIB gauges by their warning and alarm level. */
final class ImgwStageAssessorTest extends TestCase
{
    private const LABEL = 'source.imgw.assessment.label.';

    /** @return iterable<string, array{?float, ?float, float, Level, string}> */
    public static function cases(): iterable
    {
        yield 'below warning' => [300.0, 340.0, 226.0, Level::Normal, self::LABEL . 'plBelow'];
        yield 'warning reached' => [300.0, 340.0, 300.0, Level::Elevated, self::LABEL . 'plWarning'];
        yield 'alarm' => [300.0, 340.0, 351.0, Level::High, self::LABEL . 'plAlarm'];
        yield 'only alarm known' => [null, 340.0, 320.0, Level::Normal, self::LABEL . 'plBelow'];
        yield 'no levels' => [null, null, 320.0, Level::Unknown, 'assessment.label.none'];
    }

    #[DataProvider('cases')]
    public function testGauges(?float $warning, ?float $alarm, float $level, Level $expected, string $label): void
    {
        $assessment = (new ImgwStageAssessor())->assess(new FloodStages([$warning, $alarm], $level, 'cm', 'Warn-/Alarmstufe', 'IMGW-PIB'));

        self::assertSame($expected, $assessment->level);
        self::assertSame($label, $assessment->label->key);
    }
}
