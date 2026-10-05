<?php

declare(strict_types=1);

namespace CommonSight\Plugin\OpenMeteoPollen\Tests;

use CommonSight\Model\Item\ModelValueItem;
use CommonSight\Model\Place\City;
use CommonSight\Model\Value\Coordinate;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Plugin\OpenMeteoPollen\PollenMapper;
use CommonSight\Plugin\OpenMeteoPollen\Record\PollenValues;
use CommonSight\Sdk\Source\ParseContext;
use PHPUnit\Framework\TestCase;

/** The model value of a place: the strongest pollen type and every type as a fact, without a health assessment. */
final class PollenMapperTest extends TestCase
{
    public function testNamesTheStrongestTypeAndListsAll(): void
    {
        $item = $this->map(['grass' => 0.4, 'mugwort' => 5.5, 'ragweed' => 0.3]);

        self::assertSame(5.5, $item->value);
        self::assertSame('Pollen/m³', $item->unit);
        self::assertSame('source.open-meteo-pollen.summary.strongest.mugwort', $item->summary->key);
        self::assertSame(['source.open-meteo-pollen.type.grass', 'source.open-meteo-pollen.type.mugwort', 'source.open-meteo-pollen.type.ragweed'], array_map(static fn($f): string => $f->label->key, $item->facts));
        self::assertSame('pollen:at-wien', $item->common->id);
    }

    public function testWithoutPollenItSaysSo(): void
    {
        $item = $this->map(['alder' => 0.0, 'birch' => 0.0]);

        self::assertSame(0.0, $item->value);
        self::assertSame('source.open-meteo-pollen.summary.none', $item->summary->key);
    }

    /** @param non-empty-array<string, float> $concentrations */
    private function map(array $concentrations): ModelValueItem
    {
        $now = UtcInstant::fromIso('2026-10-03T19:00:00Z');
        $city = new City('at-wien', Scope::AT, 'Wien', Coordinate::fromLonLat(16.3738, 48.2082), null);
        $item = (new PollenMapper())->map(new PollenValues($city, $now, $concentrations), new ParseContext(Scope::AT, $now));
        self::assertInstanceOf(ModelValueItem::class, $item);

        return $item;
    }
}
