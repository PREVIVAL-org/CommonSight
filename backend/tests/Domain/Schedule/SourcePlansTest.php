<?php

declare(strict_types=1);

namespace CommonSight\Tests\Domain\Schedule;

use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Schedule\SourcePlans;
use CommonSight\Domain\Source\RegisteredSource;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\SourceExpectations;
use PHPUnit\Framework\TestCase;

/** Sources name their layer themselves; the plans find and order them (concept: sources as plugins, 6.1, D6). */
final class SourcePlansTest extends TestCase
{
    public function testFindsTheSourcesOfALayerByRankThenId(): void
    {
        $plans = new SourcePlans([
            $this->plan('zeta', 'water', Scope::DE, 100),
            $this->plan('alpha', 'water', Scope::DE, 100),
            $this->plan('first', 'water', Scope::DE, 10),
            $this->plan('alpha', 'water', Scope::AT, 100),
            $this->plan('other-layer', 'radiation', Scope::DE, 1),
            $this->plan('moved', 'water', Scope::DE, 100, 5),
        ], [new LayerTarget(LayerId::from('water'), Scope::DE), new LayerTarget(LayerId::from('traffic'), Scope::CH)]);

        self::assertSame(['moved', 'first', 'alpha', 'zeta'], $this->ids($plans->of(new LayerTarget(LayerId::from('water'), Scope::DE))), 'rank from the configuration first');
        self::assertSame(['alpha'], $this->ids($plans->of(new LayerTarget(LayerId::from('water'), Scope::AT))));
        self::assertEquals([new LayerTarget(LayerId::from('traffic'), Scope::CH)], $plans->targetsWithoutSources());
    }

    public function testSelectsTheSourcesOfALane(): void
    {
        $plans = new SourcePlans([$this->plan('quick', 'water', Scope::DE, 1, null, 'fast'), $this->plan('bulky', 'water', Scope::DE, 2)], []);

        self::assertSame(['quick'], $this->ids($plans->inLane('fast')));
        self::assertSame(['bulky'], $this->ids($plans->inLane('heavy')));
        self::assertSame([], $plans->inLane('slow'));
    }

    public function testRejectsTheSameSourceAndScopeTwice(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SourcePlans([$this->plan('twice', 'water', Scope::DE, 1), $this->plan('twice', 'water', Scope::DE, 1)], []);
    }

    private function plan(string $id, string $layer, Scope $scope, int $order, ?int $configuredOrder = null, string $lane = 'heavy'): SourcePlan
    {
        $description = new SourceDescription(
            id: $id,
            name: $id,
            attribution: new Attribution($id, 'https://example.org/'),
            layer: $layer,
            scopes: [$scope],
            schedule: new SourceSchedule(600),
            expectations: SourceExpectations::measurements(),
            order: $order,
        );

        return new SourcePlan(new RegisteredSource($description, static fn(): SourcePlugin => throw new \LogicException('not needed'), $configuredOrder), $scope, $lane, 600);
    }

    /**
     * @param list<SourcePlan> $plans
     * @return list<string>
     */
    private function ids(array $plans): array
    {
        return array_map(static fn(SourcePlan $p): string => $p->id(), $plans);
    }
}
