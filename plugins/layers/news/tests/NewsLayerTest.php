<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NewsLayer\Tests;

use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\LayerHarness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Layer news: the headlines of the feeds (Q-NE-*). */
final class NewsLayerTest extends TestCase
{
    /** Shortly after the recordings of the sources (tests/responses of each source plugin). */
    private const MEASURED_NOW = '2026-09-28T14:30:00Z';

    /** @return iterable<string, array{Scope, string}> */
    public static function scopes(): iterable
    {
        yield 'global' => [Scope::Global, self::MEASURED_NOW];
    }

    /** The recordings of the sources give valid snapshots; they become the examples of the frontend tests (contract/fixtures). */
    #[DataProvider('scopes')]
    public function testRecordingsGiveValidSnapshots(Scope $scope, string $now): void
    {
        (new LayerHarness())->record(LayerId::from('news'), $scope, $now);
    }
}
