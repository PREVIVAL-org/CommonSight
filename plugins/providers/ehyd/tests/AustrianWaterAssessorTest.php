<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Ehyd\Tests;

use CommonSight\Model\Level;
use CommonSight\Plugin\Ehyd\AustrianWaterAssessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** B-11: Austrian gauges by the flood code of eHYD. */
final class AustrianWaterAssessorTest extends TestCase
{
    private const LABEL = 'source.ehyd.assessment.label.';

    /** @return iterable<string, array{?int, Level, string}> */
    public static function cases(): iterable
    {
        yield 'low water 130' => [130, Level::Normal, self::LABEL . 'atClass1'];
        yield 'mean water 230' => [230, Level::Normal, self::LABEL . 'atClass2'];
        yield 'elevated 310' => [310, Level::Normal, self::LABEL . 'atClass3'];
        yield 'HQ1 400' => [400, Level::Elevated, self::LABEL . 'atClass4'];
        yield 'HQ10 520' => [520, Level::High, self::LABEL . 'atClass5'];
        yield 'HQ30 610' => [610, Level::High, self::LABEL . 'atClass6'];
        yield 'restricted currency 131' => [131, Level::Unknown, 'assessment.label.none'];
        yield 'class 1 with trend 0' => [100, Level::Unknown, 'assessment.label.none'];
        yield 'class 4 with trend 3' => [430, Level::Unknown, 'assessment.label.none'];
        yield 'class 9' => [930, Level::Unknown, 'assessment.label.none'];
        yield 'missing' => [null, Level::Unknown, 'assessment.label.none'];
    }

    #[DataProvider('cases')]
    public function testGauges(?int $code, Level $level, string $label): void
    {
        $assessment = (new AustrianWaterAssessor())->assess($code);

        self::assertSame($level, $assessment->level);
        self::assertSame($label, $assessment->label->key);
        self::assertSame($code, $assessment->sourceValue, 'D-21: original level (code) is kept');
    }

    public function testUnknownCodeIsNamed(): void
    {
        self::assertSame(['code' => 930], (new AustrianWaterAssessor())->assess(930)->basis->params);
    }
}
