<?php

declare(strict_types=1);

namespace CommonSight\Plugin\AirLayer\Tests;

use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\LayerHarness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Layer air: CAMS model values at the places of the countries and of the border zone (Q-AI-*). */
final class AirLayerTest extends TestCase
{
    /** Shortly after the recordings of the sources (tests/responses of each source plugin). */
    private const MEASURED_NOW = '2026-09-28T14:30:00Z';

    /** @return iterable<string, array{Scope, string}> */
    public static function scopes(): iterable
    {
        yield 'DE' => [Scope::DE, self::MEASURED_NOW];
        yield 'AT' => [Scope::AT, self::MEASURED_NOW];
        yield 'CH' => [Scope::CH, self::MEASURED_NOW];
        yield 'border' => [Scope::Border, self::MEASURED_NOW];
    }

    /** The recordings of the sources give valid snapshots; they become the examples of the frontend tests (contract/fixtures). */
    #[DataProvider('scopes')]
    public function testRecordingsGiveValidSnapshots(Scope $scope, string $now): void
    {
        (new LayerHarness())->record(LayerId::from('air'), $scope, $now);
    }
}
