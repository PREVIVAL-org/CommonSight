<?php

declare(strict_types=1);

namespace CommonSight\Tests\Application;

use CommonSight\Application\HealthRecorder;
use CommonSight\Application\LayerSchedule;
use CommonSight\Domain\Schedule\BackoffPolicy;
use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Schedule\StalenessPolicy;
use CommonSight\Domain\Source\RegisteredSource;
use CommonSight\Domain\Source\SourceSelection;
use CommonSight\Model\State\SourceHealth;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Tests\Support\FixedRandom;
use CommonSight\Tests\Support\InMemoryHealth;
use PHPUnit\Framework\TestCase;

/** The rhythm of a layer comes from its active sources only (concept: sources as plugins, V1). */
final class LayerScheduleTest extends TestCase
{
    public function testSourcesWithoutTheirSecretsDoNotKeepALayerStale(): void
    {
        $now = UtcInstant::fromIso('2026-10-03T12:00:00Z');
        $health = new InMemoryHealth();
        $schedule = new LayerSchedule(new HealthRecorder($health, new BackoffPolicy(), new FixedRandom(0.0), 'test'), new StalenessPolicy(2.5), new SourceSelection([]));
        $working = $this->plan('working', []);
        $health->write((new SourceHealth('working', Scope::CH))->succeeded($now, 3, 100, 'test'));

        self::assertEquals([600, $now->plusSeconds(1500)], $schedule->of([$working, $this->plan('keyless', ['apiKey'])], $now), 'only the working source counts');
        self::assertSame([null, null], $schedule->of([$this->plan('keyless', ['apiKey'])], $now), 'not set up: never stale');
    }

    /** A 60 s source in a lane that runs every 5 min is not stale between two runs of its lane. */
    public function testALayerIsNotStaleBeforeItsLaneCameAround(): void
    {
        $now = UtcInstant::fromIso('2026-10-03T12:00:00Z');
        $health = new InMemoryHealth();
        $schedule = new LayerSchedule(new HealthRecorder($health, new BackoffPolicy(), new FixedRandom(0.0), 'test'), new StalenessPolicy(2.5), new SourceSelection([]), ['heavy' => 300]);
        $health->write((new SourceHealth('working', Scope::CH))->succeeded($now, 3, 100, 'test'));

        self::assertEquals([600, $now->plusSeconds(1500)], $schedule->of([$this->plan('working', [])], $now), 'interval 600 > lane 300');
        self::assertEquals([60, $now->plusSeconds(750)], $schedule->of([$this->plan('working', [], 60)], $now), '2.5 × the lane, not 2.5 × 60 s');
    }

    /** @param list<string> $missingSecrets */
    private function plan(string $id, array $missingSecrets, int $intervalSec = 600): SourcePlan
    {
        $description = new SourceDescription(
            id: $id,
            name: $id,
            attribution: new Attribution($id, 'https://example.org/'),
            layer: 'traffic',
            scopes: [Scope::CH],
            schedule: new SourceSchedule(600),
            expectations: SourceExpectations::events(),
        );

        return new SourcePlan(new RegisteredSource($description, static fn(): SourcePlugin => throw new \LogicException('not needed'), null, $missingSecrets), Scope::CH, 'heavy', $intervalSec);
    }
}
