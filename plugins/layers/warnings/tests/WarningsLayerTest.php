<?php

declare(strict_types=1);

namespace CommonSight\Plugin\WarningsLayer\Tests;

use CommonSight\Model\FeedStatus;
use CommonSight\Model\Item\WarningItem;
use CommonSight\Model\Snapshot;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Tests\Support\LayerHarness;
use CommonSight\Tests\Support\SnapshotContract;
use PHPUnit\Framework\TestCase;

/**
 * Q-W-*: the layer warnings with the edge cases of its sources (cancel, expired, broken area, missing details); the
 * snapshots become the examples of the frontend tests (contract/fixtures).
 */
final class WarningsLayerTest extends TestCase
{
    private const CASES_NOW = '2026-09-28T12:00:00Z';

    public function testWarningsGermany(): void
    {
        $snapshot = $this->record(Scope::DE);

        $ids = array_map(static fn($i): string => $i->common()->id, $snapshot->items);
        self::assertContains('dwd:Warnungen_Landkreise.stormarn-1', $ids);
        self::assertNotContains('dwd:Warnungen_Landkreise.cancel-1', $ids, 'Q-00: cancellation');
        self::assertNotContains('dwd:Warnungen_Landkreise.expired-1', $ids, 'Q-00: expired');
        self::assertNotContains('dwd:Warnungen_Landkreise.exercise-1', $ids, 'CAP status Exercise is no real warning');
        // The further feeds of NINA: KATWARN as civil protection, the flood portals as floods, each naming its feed.
        $byId = array_combine($ids, $snapshot->items);
        $katwarn = $byId['katwarn:kat.0f1e2d3c4b5a69788796a5b4_public_topics'] ?? null;
        $flood = $byId['lhp:lhp.HOCHWASSERZENTRALEN.DE.NI'] ?? null;
        self::assertInstanceOf(WarningItem::class, $katwarn);
        self::assertInstanceOf(WarningItem::class, $flood);
        self::assertSame(['BBK / NINA · KATWARN', 'civilProtection'], [$katwarn->common->source, $katwarn->category->value]);
        self::assertSame(['BBK / NINA · Hochwasserportal', 'flood', 'source.bbk-mowas.hazard.flood'], [$flood->common->source, $flood->category->value, $flood->hazard->key]);
        self::assertSame(FeedStatus::Partial, $snapshot->status, 'one DWD warning without usable area');
        self::assertInstanceOf(WarningItem::class, $snapshot->items[0]);
        self::assertSame('Severe', $snapshot->items[0]->severity->value, 'Q-W-01: most severe first');
    }

    public function testWarningsAustria(): void
    {
        $snapshot = $this->record(Scope::AT);

        self::assertSame(FeedStatus::Partial, $snapshot->status);
        $first = $snapshot->items[0];
        self::assertInstanceOf(WarningItem::class, $first);
        self::assertSame('Hitzewarnung · Rot', $first->common->title, 'Q-W-AT-11: highest level first');
        $thunder = array_values(array_filter($snapshot->items, static fn($i): bool => $i->common()->id === 'geosphere:w12345c1v2:1790586000:1790625600'))[0];
        self::assertInstanceOf(WarningItem::class, $thunder);
        self::assertSame('Gewitter mit Starkregen, Hagel und Sturmböen.', $thunder->sections[0]->text, 'Q-W-AT-07: exactly matching detail text');
        self::assertSame('Überflutungen von Straßen & Unterführungen möglich.', $thunder->sections[2]->text);
        self::assertSame('Niederösterreich, Wien', $thunder->area, 'Q-W-AT-06: by state code');
        self::assertSame('2026-09-28T08:45:00Z', $thunder->common->time?->toIso());
        $keys = array_map(static fn($m): string => $m->key, $snapshot->issues);
        self::assertContains('issue.missingGeometry', $keys);
        self::assertContains('issue.missingDetailText', $keys);
        self::assertContains('issue.invalidRecords', $keys, 'Q-W-AT-02: invalid records counted');
        // AT-Alert: civil protection; the Zivilschutz-Probealarm came at the highest level and is shown as a test.
        $byId = array_combine(array_map(static fn($i): string => $i->common()->id, $snapshot->items), $snapshot->items);
        $test = $byId['at-alert:AlertLevel1.German.20656.20261003'] ?? null;
        self::assertInstanceOf(WarningItem::class, $test);
        self::assertSame(['source.at-alert.level.test', 'Minor', 'Burgenland'], [$test->hazard->key, $test->severity->value, $test->area]);
        self::assertStringStartsWith('Probewarnung - Österreichweite Testauslösung', $test->sections[0]->text, 'the repeated title is cut off');
        self::assertSame(['AT-1'], array_map(static fn($id): string => $id->value, $test->common->regionIds));
        $systemTest = $byId['at-alert:AlertLevel3.German.21500.20261002'] ?? null;
        self::assertInstanceOf(WarningItem::class, $systemTest);
        self::assertSame('source.at-alert.level.test', $systemTest->hazard->key, 'a system test named only in the text');
        $flood = $byId['at-alert:AlertLevel2.German.30001.20261003'] ?? null;
        self::assertInstanceOf(WarningItem::class, $flood);
        self::assertSame(['source.at-alert.level.AlertLevel2', 'Extreme', '', 'civilProtection'], [$flood->hazard->key, $flood->severity->value, $flood->area, $flood->category->value]);
        self::assertSame('Die Mur tritt in Graz über die Ufer. Meiden Sie das Ufer <100 m.', $flood->sections[0]->text);
        self::assertSame('2026-10-03T21:00:00Z', $flood->expires?->toIso());
    }

    public function testWarningsSwitzerland(): void
    {
        $snapshot = $this->record(Scope::CH);

        self::assertCount(5, $snapshot->items, 'MeteoAlarm: expired and cancel dropped; Alertswiss: test, all-clear and the alert without id dropped');
        self::assertSame('2026-09-28T11:05:15Z', $snapshot->updatedAt?->toIso(), 'Q-W-CH-02: updated of the feed');
        $bern = array_values(array_filter($snapshot->items, static fn($i): bool => str_ends_with($i->common()->id, 'bern-1')))[0];
        self::assertSame([[7.3, 46.85], [7.6, 46.85], [7.6, 47.05], [7.3, 47.05], [7.3, 46.85]], $bern->common()->geometry?->coordinates[0], 'Q-W-CH-03: rotated and closed');
        self::assertSame(['CH-BE'], array_map(static fn($id): string => $id->value, $bern->common()->regionIds));
        // The German block of the CAP message replaces the English texts of the feed; without one the feed's texts stay.
        self::assertInstanceOf(WarningItem::class, $bern);
        self::assertSame(['Gewitterwarnung Stufe 2', 'Gewitter', 'de'], [$bern->common->title, $bern->hazard->params['text'] ?? null, $bern->common->lang]);
        self::assertSame(['Gewitter mit Hagel möglich.', 'Schutz in Gebäuden suchen.'], array_map(static fn($s): string => $s->text, $bern->sections));
        // Alertswiss: civil protection named after its event and canton, the time from the CAP reference, circles as areas.
        $byId = array_combine(array_map(static fn($i): string => $i->common()->id, $snapshot->items), $snapshot->items);
        $fireBan = $byId['alertswiss:POA-1-1'] ?? null;
        self::assertInstanceOf(WarningItem::class, $fireBan);
        self::assertSame(['Alertswiss · Kanton Bern', 'civilProtection', '2026-09-28T08:00:00Z'], [$fireBan->common->source, $fireBan->category->value, $fireBan->common->time?->toIso()]);
        self::assertSame(['CH-BE'], array_map(static fn($id): string => $id->value, $fireBan->common->regionIds));
        self::assertSame('Erhebliche Waldbrandgefahr. Feuer nur auf offiziellen Feuerstellen.', $fireBan->sections[0]->text, 'line breaks of Alertswiss');
        self::assertSame('Keine Feuer im Wald <50 m.', $fireBan->sections[1]->text);
        $road = $byId['alertswiss:POA-2-1'] ?? null;
        self::assertInstanceOf(WarningItem::class, $road);
        self::assertSame('Polygon', $road->common->geometry?->type, 'the circle as a polygon');
        self::assertSame(['CH-UR'], array_map(static fn($id): string => $id->value, $road->common->regionIds));
        self::assertArrayHasKey('alertswiss:POA-3-1', $byId, 'the whole country, without an area');
        self::assertArrayNotHasKey('alertswiss:TEST-1', $byId, 'technical test');
        self::assertArrayNotHasKey('alertswiss:POA-4-2', $byId, 'all-clear');
    }

    private function record(Scope $scope): Snapshot
    {
        $snapshot = (new LayerHarness())
            ->respond('https://maps.dwd.de/', 'dwd-warnings', 'de-cases.json')
            ->respond('https://warnung.bund.de/api31/mowas/', 'bbk-mowas', 'de.json')
            ->respond('https://warnung.bund.de/api31/katwarn/', 'bbk-mowas', 'katwarn.json')
            ->respond('https://warnung.bund.de/api31/lhp/', 'bbk-mowas', 'lhp.json')
            ->respond('https://warnung.bund.de/api31/warnings/', 'bbk-mowas', 'warning.geojson')
            ->respond('https://warnungen.zamg.at/wsapp/api/getWarnstatus', 'geosphere-warnings', 'at-cases.json')
            ->respond('https://warnungen.zamg.at/wsapp/api/getWarningsForCoords', 'geosphere-warnings', 'at-detail.json')
            ->respond('https://warnungen.at-alert.at/', 'at-alert', 'at-cases.json')
            ->respond('https://feeds.meteoalarm.org/feeds/', 'meteoalarm-ch', 'ch-cases.xml')
            ->respond('https://feeds.meteoalarm.org/api/v1/warnings/feeds-switzerland/bern-1', 'meteoalarm-ch', 'ch-cap-bern.xml')
            ->respond('https://www.alert.swiss/', 'alertswiss', 'ch-cases.json')
            ->snapshot(LayerId::from('warnings'), $scope, self::CASES_NOW);
        SnapshotContract::record('warnings-' . $scope->value, $snapshot);

        return $snapshot;
    }
}
