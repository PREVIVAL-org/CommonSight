<?php

declare(strict_types=1);

namespace CommonSight\Plugin\Lindas\Tests;

use CommonSight\Model\Level;
use CommonSight\Plugin\Lindas\SwissWaterAssessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** B-12: Swiss gauges by the danger levels of the BAFU. */
final class SwissWaterAssessorTest extends TestCase
{
    /** @return iterable<string, array{?int, Level}> */
    public static function cases(): iterable
    {
        yield 'level 1' => [1, Level::Normal];
        yield 'level 2' => [2, Level::Elevated];
        yield 'level 3' => [3, Level::Elevated];
        yield 'level 4' => [4, Level::High];
        yield 'level 5' => [5, Level::High];
        yield 'missing' => [null, Level::Unknown];
        yield 'out of range' => [6, Level::Unknown];
    }

    #[DataProvider('cases')]
    public function testGauges(?int $danger, Level $level): void
    {
        $assessment = (new SwissWaterAssessor())->assess($danger);

        self::assertSame($level, $assessment->level);
        if ($level !== Level::Unknown) {
            self::assertSame('source.bafu-lindas.assessment.label.chLevel' . $danger, $assessment->label->key);
            self::assertSame($danger, $assessment->sourceValue);
        }
    }
}
