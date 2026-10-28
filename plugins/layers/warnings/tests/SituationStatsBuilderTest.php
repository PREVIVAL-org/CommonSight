<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WarningsLayer\Tests;

use CommonSight\Model\Item\Awareness;
use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\Severity;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\RegionId;
use CommonSight\Plugin\WarningsLayer\SituationStatsBuilder;
use PHPUnit\Framework\TestCase;

/** L-D2, L-D3: the grade of a warning on the common scale and the situation level of a country from 1 to 5. */
final class SituationStatsBuilderTest extends TestCase
{
    /** Eight regions: a quarter is two. */
    private const REGIONS = ['DE-BB', 'DE-BE', 'DE-BW', 'DE-BY', 'DE-HB', 'DE-HE', 'DE-HH', 'DE-MV'];

    public function testNoWarningsIsLevelOne(): void
    {
        self::assertSame(1, $this->level([]));
    }

    public function testYellowEverywhereIsLevelOne(): void
    {
        self::assertSame(1, $this->level([$this->warning(Severity::Minor, self::REGIONS)]));
    }

    public function testOrangeLocalAndWidespread(): void
    {
        self::assertSame(2, $this->level([$this->warning(Severity::Moderate, ['DE-BY'])]));
        self::assertSame(3, $this->level([$this->warning(Severity::Moderate, ['DE-BY']), $this->warning(Severity::Moderate, ['DE-BW'])]));
    }

    public function testRedLocalAndWidespread(): void
    {
        self::assertSame(3, $this->level([$this->warning(Severity::Severe, ['DE-BY'])]));
        self::assertSame(4, $this->level([$this->warning(Severity::Severe, ['DE-BY', 'DE-BW'])]));
    }

    public function testVioletLocalAndWidespread(): void
    {
        self::assertSame(4, $this->level([$this->warning(Severity::Extreme, ['DE-BY'])]));
        self::assertSame(5, $this->level([$this->warning(Severity::Extreme, ['DE-BY', 'DE-BW'])]));
    }

    public function testHigherGradesCountForTheSpreadOfLowerOnes(): void
    {
        // Orange in one state and red in another: orange or more in a quarter, level 3 also from orange widespread.
        $stats = (new SituationStatsBuilder($this->regions()))->build([$this->warning(Severity::Moderate, ['DE-BY']), $this->warning(Severity::Severe, ['DE-BW'])]);

        self::assertSame(3, $stats->level);
        self::assertSame(['yellow' => 2, 'orange' => 2, 'red' => 1], $stats->regions);
        self::assertSame(['orange' => 1, 'red' => 1], $stats->warnings);
    }

    public function testColourLevelOfTheSourceComesBeforeTheSeverity(): void
    {
        // MeteoAlarm and GeoSphere: yellow with severity Moderate is yellow, not orange.
        self::assertSame(1, $this->level([$this->warning(Severity::Moderate, self::REGIONS, Awareness::Yellow)]));
        self::assertSame(3, $this->level([$this->warning(Severity::Extreme, ['DE-BY'], Awareness::Red)]));
    }

    public function testRegionsOfOtherCountriesAndWarningsWithoutRegionDoNotSpread(): void
    {
        self::assertSame(2, $this->level([$this->warning(Severity::Moderate, ['DE-BY', 'AT-1', 'AT-2']), $this->warning(Severity::Moderate, [])]));
    }

    public function testJsonHasEveryGrade(): void
    {
        $stats = (new SituationStatsBuilder($this->regions()))->build([$this->warning(Severity::Minor, ['DE-BY'])]);

        self::assertSame(['situation' => [
            'level' => 1,
            'regionCount' => 8,
            'grades' => [
                'yellow' => ['warnings' => 1, 'regions' => 1],
                'orange' => ['warnings' => 0, 'regions' => 0],
                'red' => ['warnings' => 0, 'regions' => 0],
                'violet' => ['warnings' => 0, 'regions' => 0],
            ],
        ]], $stats->jsonSerialize());
    }

    /** @param list<WarningItem> $warnings */
    private function level(array $warnings): int
    {
        return (new SituationStatsBuilder($this->regions()))->build($warnings)->level;
    }

    /** @return list<RegionId> */
    private function regions(): array
    {
        return array_map(RegionId::fromString(...), self::REGIONS);
    }

    /** @param list<string> $regions */
    private function warning(Severity $severity, array $regions, ?Awareness $awareness = null): WarningItem
    {
        static $n = 0;
        $common = new ItemCommon('test:' . ++$n, 'Warnung', 'https://example.org/', regionIds: array_map(RegionId::fromString(...), $regions));

        return new WarningItem($common, new Msg('hazard.weather'), $severity, '', null, null, [], new CatalogTerm('weather'), $awareness);
    }
}
