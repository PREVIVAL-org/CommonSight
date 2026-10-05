<?php

declare(strict_types=1);

namespace CommonSight\Tests\Contract;

use CommonSight\Infrastructure\Contract\LayerSchemaBuilder;
use CommonSight\Sdk\Layer\LayerDescription;
use CommonSight\Tests\Support\Fixtures;
use CommonSight\Tests\Support\SnapshotContract;
use PHPUnit\Framework\TestCase;

/** The generated layer schema pins each layer to its own key figures and scopes (concept: layers as plugins, 5). */
final class LayerSchemaTest extends TestCase
{
    public function testTheFixturesOfEveryLayerAreValid(): void
    {
        foreach (glob(Fixtures::contractDir() . '/fixtures/*.json') ?: [] as $file) {
            self::assertNull(SnapshotContract::violations((string) file_get_contents($file), 'snapshot.schema.json'), basename($file));
        }
    }

    public function testALayerCannotCarryTheKeyFiguresOfAnother(): void
    {
        $weather = $this->fixture('weather-AT');
        $weather['stats'] = $this->fixture('radiation-AT')['stats'];

        self::assertNotNull($this->violations($weather), 'weather has no key figures');
    }

    public function testALayerHasOnlyItsScopes(): void
    {
        $warnings = $this->fixture('warnings-AT');
        $warnings['scope'] = 'border';
        $space = $this->fixture('space-global');
        $space['scope'] = 'AT';

        self::assertNotNull($this->violations($warnings), 'warnings have no border zone');
        self::assertNotNull($this->violations($space), 'space is global');
    }

    public function testALayerIdMustNotTakeANameOfTheCore(): void
    {
        $this->expectExceptionMessage('Layer layer: its key figures would be named layerStats');

        (new LayerSchemaBuilder())->schema([new LayerDescription('layer', false, false, '#123456', 'circle', true, true, null, false, 1)], ['layer' => ['type' => 'object']]);
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        return (array) json_decode((string) file_get_contents(Fixtures::contractDir() . '/fixtures/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $snapshot */
    private function violations(array $snapshot): ?string
    {
        return SnapshotContract::violations((string) json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), 'snapshot.schema.json');
    }
}
