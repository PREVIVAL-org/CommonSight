<?php

declare(strict_types=1);

namespace CommonSight\Tests\Domain\Schedule;

use CommonSight\Domain\Schedule\BackoffPolicy;
use CommonSight\Domain\Schedule\DueSourcePolicy;
use CommonSight\Domain\Schedule\StalenessPolicy;
use CommonSight\Model\FeedStatus;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\State\SourceHealth;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use PHPUnit\Framework\TestCase;

/** F-05 (backoff), concept "sources as plugins" 6.1 (due per source) and V1 (stale from the sources), Architecture 6.3. */
final class SchedulePoliciesTest extends TestCase
{
    public function testBackoffDoublesUpToThirtyMinutesAndHonoursRetryAfter(): void
    {
        $now = $this->at('12:00:00');
        $policy = new BackoffPolicy();

        self::assertSame(60, $policy->until(1, $now, 0.0)->secondsSince($now));
        self::assertSame(120, $policy->until(2, $now, 0.0)->secondsSince($now));
        self::assertSame(480, $policy->until(4, $now, 0.0)->secondsSince($now));
        self::assertSame(1800, $policy->until(10, $now, 0.99)->secondsSince($now));
        self::assertSame(72, $policy->until(1, $now, 0.999)->secondsSince($now), 'random share up to 20 %');
        self::assertSame(3600, $policy->until(1, $now, 0.0, 3600)->secondsSince($now), 'Retry-After of the provider');
        self::assertSame(60, $policy->until(1, $now, 0.0, 10)->secondsSince($now), 'never shorter than the backoff');
        self::assertSame(86_400, $policy->until(1, $now, 0.0, 999_999)->secondsSince($now), 'at most a day');
    }

    public function testASourceIsDueAfterItsIntervalWithoutBackoffOrWithoutOutcomeOfThisRelease(): void
    {
        $due = new DueSourcePolicy();
        $now = $this->at('12:00:00');

        self::assertTrue($due->isDue(null, 600, $now, 'r1'), 'never ran');
        self::assertFalse($due->isDue($this->health('11:55:00'), 600, $now, 'r1'));
        self::assertTrue($due->isDue($this->health('11:50:00'), 600, $now, 'r1'));
        self::assertTrue($due->isDue($this->health('11:59:00'), 600, $now, 'r2'), 'outcome of another release');
        self::assertFalse($due->isDue($this->health('11:00:00', '12:01:00'), 600, $now, 'r1'), 'backoff active');
        self::assertTrue($due->isDue($this->health('11:59:00', '11:59:30', 1), 600, $now, 'r1'), 'after a failure as soon as the backoff has ended');
        self::assertFalse($due->isDue($this->health('11:59:00', '11:59:30', 1), 600, $now, 'r1', 300), 'a failure does not undercut the terms');
        self::assertFalse($due->isDue($this->health('11:59:00'), 600, $now, 'r2', 300), 'nor does a new release');
        self::assertTrue($due->isDue($this->health('11:54:00', '11:59:30', 1), 600, $now, 'r1', 300), 'once the terms allow it');
    }

    public function testStalenessComesFromTheSourcesOfTheLayer(): void
    {
        $policy = new StalenessPolicy(2.5);
        $now = $this->at('12:00:00');

        self::assertSame('2026-09-28T12:02:00Z', $policy->staleAfter([[$this->health('11:57:00'), 120], [$this->health('11:59:00'), 900]], $now)?->toIso(), 'the earliest: 11:57 + 2.5 × 120 s');
        self::assertSame('2026-09-28T12:00:00Z', $policy->staleAfter([[null, 120], [$this->health('11:59:00'), 900]], $now)?->toIso(), 'a source that never succeeded');
        self::assertNull($policy->staleAfter([], $now), 'no active source');
        self::assertFalse($policy->isStale($this->state('12:05:00'), $now));
        self::assertTrue($policy->isStale($this->state('11:59:59'), $now));
        self::assertTrue($policy->isStale(null, $now), 'never assembled');
        self::assertFalse($policy->isStale($this->state(null), $now), 'no sources: never stale');
    }

    public function testTheMostStaleLayerFirst(): void
    {
        $policy = new StalenessPolicy(2.5);
        $now = $this->at('12:00:00');
        $warnings = new LayerTarget(LayerId::from('warnings'), Scope::AT);
        $water = new LayerTarget(LayerId::from('water'), Scope::AT);
        $news = new LayerTarget(LayerId::from('news'), Scope::Global);

        self::assertSame($news, $policy->mostStale([[$warnings, $this->state('11:50:00')], [$water, $this->state('11:00:00')], [$news, null]], $now), 'without state first');
        self::assertSame($water, $policy->mostStale([[$warnings, $this->state('11:50:00')], [$water, $this->state('11:00:00')]], $now));
        self::assertNull($policy->mostStale([[$warnings, $this->state('12:30:00')]], $now));
    }

    private function health(string $lastAttempt, ?string $backoffUntil = null, int $failures = 0): SourceHealth
    {
        return new SourceHealth('demo', Scope::AT, $this->at($lastAttempt), $this->at($lastAttempt), $failures, $backoffUntil === null ? null : $this->at($backoffUntil), null, 1, null, 'r1');
    }

    private function state(?string $staleAfter): LayerState
    {
        $checked = $this->at('11:00:00');

        return new LayerState(LayerId::from('warnings'), Scope::AT, 'abcdef12', 'AT/warnings.abcdef12.json', FeedStatus::Ok, $checked, $checked, null, 1, [], null, 0, null, 120, $staleAfter === null ? null : $this->at($staleAfter));
    }

    private function at(string $time): UtcInstant
    {
        return UtcInstant::fromIso('2026-09-28T' . $time . 'Z');
    }
}
