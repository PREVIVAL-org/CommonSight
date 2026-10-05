<?php

declare(strict_types=1);

namespace CommonSight\Tests\Application;

use CommonSight\Application\OutcomeArchive;
use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Domain\Source\RegisteredSource;
use CommonSight\Domain\Source\SourceResult;
use CommonSight\Domain\Source\SourceState;
use CommonSight\Model\Assessment;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\MeasurementItem;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\ParseStatistics;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Tests\Support\InMemoryOutcomes;
use PHPUnit\Framework\TestCase;

/** Concept "sources as plugins", 6: the latest outcome of a source is kept for its layer, readable by its release only. */
final class OutcomeArchiveTest extends TestCase
{
    public function testKeepsTheOutcomeForTheSameReleaseOnly(): void
    {
        $store = new InMemoryOutcomes();
        $plan = $this->plan();
        $item = new MeasurementItem(new ItemCommon('m:1', 'Station', 'https://example.org/'), new CatalogTerm('waterLevel'), 1.5, 'm', new Msg('reference.gaugeZero'), Assessment::byLayer());
        (new OutcomeArchive($store, 'r1'))->save($plan, SourceResult::success($plan->source->description, [$item], [], new ParseStatistics(1, 0, 0), null));

        $loaded = (new OutcomeArchive($store, 'r1'))->load($plan);
        self::assertNotNull($loaded);
        self::assertSame(SourceState::Succeeded, $loaded->outcome);
        self::assertEquals([$item], $loaded->items);
        self::assertNull((new OutcomeArchive($store, 'r2'))->load($plan), 'written by another release');

        $store->outcomes['demo-DE'] = 'O:8:"stdClass":0:{}';
        self::assertNull((new OutcomeArchive($store, 'r1'))->load($plan), 'not an outcome');
        $store->outcomes['demo-DE'] = 'broken';
        self::assertNull((new OutcomeArchive($store, 'r1'))->load($plan), 'unreadable');
    }

    private function plan(): SourcePlan
    {
        $description = new SourceDescription(
            id: 'demo',
            name: 'Demo',
            attribution: new Attribution('Demo', 'https://example.org/'),
            layer: 'water',
            scopes: [Scope::DE],
            schedule: new SourceSchedule(600),
            expectations: SourceExpectations::measurements(),
        );

        return new SourcePlan(new RegisteredSource($description, static fn(): SourcePlugin => throw new \LogicException('not needed')), Scope::DE, 'heavy', 600);
    }
}
