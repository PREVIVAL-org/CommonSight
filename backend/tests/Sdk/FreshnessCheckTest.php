<?php

declare(strict_types=1);

namespace CommonSight\Tests\Sdk;

use CommonSight\Model\Assessment;
use CommonSight\Model\AssessmentOrigin;
use CommonSight\Model\Level;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Layer\FreshnessCheck;
use CommonSight\Sdk\Layer\FreshnessState;
use CommonSight\Tests\Support\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** B-01, B-02, B-03: test cases shared with the frontend (contract/testcases/freshness.json, Architecture 3.4). */
final class FreshnessCheckTest extends TestCase
{
    /** @return iterable<string, array{array<mixed>}> */
    public static function recheckCases(): iterable
    {
        foreach (Fixtures::contractJson('testcases/freshness.json')['recheck'] as $case) {
            yield (string) $case['name'] => [$case];
        }
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function atFetchCases(): iterable
    {
        foreach (Fixtures::contractJson('testcases/freshness.json')['atFetch'] as $case) {
            yield (string) $case['name'] => [$case];
        }
    }

    /** @param array<mixed> $case */
    #[DataProvider('recheckCases')]
    public function testRecheckMatchesSharedCases(array $case): void
    {
        $validUntil = $case['assessment']['validUntil'] ?? null;
        $assessment = new Assessment(
            Level::from($case['assessment']['level']),
            new Msg('assessment.label.none'),
            new Msg('assessment.basis.timeMissing'),
            AssessmentOrigin::Source,
            $validUntil === null ? null : UtcInstant::fromIso($validUntil),
        );

        self::assertSame($case['expected'], (new FreshnessCheck())->recheck($assessment, UtcInstant::fromIso($case['now']))->value);
    }

    /** @param array<mixed> $case */
    #[DataProvider('atFetchCases')]
    public function testAtFetchMatchesSharedCases(array $case): void
    {
        $original = new Assessment(Level::Elevated, new Msg('assessment.label.deMhw'), new Msg('assessment.basis.deMhw'), AssessmentOrigin::Source, null, 'MHW: high');
        $time = $case['time'] === null ? null : UtcInstant::fromIso($case['time']);

        $result = (new FreshnessCheck())->atFetch($original, $time, $case['maxAgeHours'], UtcInstant::fromIso($case['now']));

        self::assertSame($case['validUntil'], $result->validUntil?->toIso());
        match ($case['expected']) {
            'current' => self::assertSame(Level::Elevated, $result->level),
            'expired' => $this->assertStale($result, $case['maxAgeHours']),
            'timeMissing' => self::assertSame('assessment.basis.timeMissing', $result->basis->key),
        };
        self::assertSame('MHW: high', $result->sourceValue, 'D-21: original level is kept');
    }

    public function testRecheckOfFreshAssessmentIsCurrent(): void
    {
        $check = new FreshnessCheck();
        $assessment = $check->atFetch(
            new Assessment(Level::High, new Msg('layer.radiation.assessment.high'), new Msg('layer.radiation.assessment.basis'), AssessmentOrigin::Display),
            UtcInstant::fromIso('2026-09-28T11:00:00Z'),
            12,
            UtcInstant::fromIso('2026-09-28T12:00:00Z'),
        );

        self::assertSame(FreshnessState::Current, $check->recheck($assessment, UtcInstant::fromIso('2026-09-28T22:59:59Z')));
        self::assertSame(FreshnessState::Expired, $check->recheck($assessment, UtcInstant::fromIso('2026-09-28T23:00:00Z')));
    }

    private function assertStale(Assessment $result, int $hours): void
    {
        self::assertSame(Level::Unknown, $result->level);
        self::assertSame('assessment.basis.stale', $result->basis->key);
        self::assertSame(['hours' => $hours], $result->basis->params);
        self::assertSame(Level::Elevated, $result->previous?->level, 'B-01: last assessment stays visible');
    }
}
