<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Pegelonline\Tests;

use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Plugin\Pegelonline\GermanWaterAssessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** B-10: German gauges by the MHW and HSW classification of PEGELONLINE. */
final class GermanWaterAssessorTest extends TestCase
{
    private const LABEL = 'source.pegelonline.assessment.label.';

    /** @return iterable<string, array{?string, ?string, Level, string}> */
    public static function cases(): iterable
    {
        yield 'HSW high' => ['normal', 'high', Level::High, self::LABEL . 'deHsw'];
        yield 'HSW high despite MHW low (HSW can be below MHW)' => ['low', 'high', Level::High, self::LABEL . 'deHsw'];
        yield 'MHW high' => ['high', 'normal', Level::Elevated, self::LABEL . 'deMhw'];
        yield 'MHW high without HSW' => ['high', null, Level::Elevated, self::LABEL . 'deMhw'];
        yield 'MHW normal' => ['normal', 'normal', Level::Normal, self::LABEL . 'deBelowMhw'];
        yield 'MHW low' => ['low', null, Level::Normal, self::LABEL . 'deLowWater'];
        yield 'only HSW normal' => ['unknown', 'normal', Level::Normal, self::LABEL . 'deBelowHsw'];
        yield 'commented' => ['commented', 'high', Level::Unknown, 'assessment.label.none'];
        yield 'out-dated' => ['normal', 'out-dated', Level::Unknown, 'assessment.label.none'];
        yield 'nothing' => [null, null, Level::Unknown, 'assessment.label.none'];
    }

    #[DataProvider('cases')]
    public function testGauges(?string $mhw, ?string $hsw, Level $level, string $label): void
    {
        $assessment = (new GermanWaterAssessor())->assess($mhw, $hsw);

        self::assertSame($level, $assessment->level);
        self::assertSame($label, $assessment->label->key);
        self::assertSame(AssessmentOrigin::Source, $assessment->origin);
    }
}
