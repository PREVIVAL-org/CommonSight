<?php

declare(strict_types=1);

namespace CommonSight\Tests\Application;

use CommonSight\Application\SituationQuery;
use CommonSight\Domain\Schedule\StalenessPolicy;
use CommonSight\Domain\Status\ConditionalResponse;
use CommonSight\Domain\Status\SituationView;
use CommonSight\Model\FeedStatus;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Tests\Support\InMemorySnapshots;
use CommonSight\Tests\Support\InMemoryStates;
use CommonSight\Tests\Support\SnapshotContract;
use PHPUnit\Framework\TestCase;

/** L-D4: the situation level per country from the warnings snapshots; unknown, never 1, without a current one. */
final class SituationQueryTest extends TestCase
{
    private InMemoryStates $states;
    private InMemorySnapshots $snapshots;
    private SituationQuery $query;

    protected function setUp(): void
    {
        $this->states = new InMemoryStates();
        $this->snapshots = new InMemorySnapshots();
        $this->query = new SituationQuery($this->states, $this->snapshots, new SituationView(new StalenessPolicy()), new ConditionalResponse(), LayerId::from('warnings'));
    }

    public function testLevelPerCountryAndUnknownWithoutACurrentOne(): void
    {
        $this->store(Scope::DE, FeedStatus::Ok, '{"stats":{"situation":{"level":3,"regionCount":16,"grades":{"yellow":{"warnings":4,"regions":5}}}}}');
        $this->store(Scope::AT, FeedStatus::Error, '{"stats":{"situation":{"level":1,"regionCount":9,"grades":{}}}}');
        // A snapshot of an older release, without the situation.
        $this->store(Scope::CH, FeedStatus::Ok, '{"stats":{}}');

        $answer = $this->query->answer($this->at('12:01:00'), null);
        SnapshotContract::assertJsonValid($answer->body, 'situation.schema.json');
        $countries = json_decode($answer->body, true)['countries'];
        self::assertSame([3, 16], [$countries['DE']['level'], $countries['DE']['regionCount']]);
        self::assertSame(5, $countries['DE']['grades']['yellow']['regions']);
        self::assertSame([null, 'error', null], [$countries['AT']['level'], $countries['AT']['status'], $countries['AT']['grades']], 'a failed layer is no all-clear');
        self::assertNull($countries['CH']['level']);
        self::assertSame(304, $this->query->answer($this->at('12:01:30'), $answer->etag)->status);
        // The file of an unchanged version cannot be read for a moment: no 304 for the answer of a good read.
        $this->snapshots->files = [];
        self::assertSame(200, $this->query->answer($this->at('12:01:40'), $answer->etag)->status);
    }

    public function testUnknownWithoutSnapshotAndWhenStale(): void
    {
        $this->store(Scope::DE, FeedStatus::Ok, '{"stats":{"situation":{"level":2,"regionCount":16,"grades":{}}}}');

        $answer = $this->query->answer($this->at('12:01:00'), null);
        SnapshotContract::assertJsonValid($answer->body, 'situation.schema.json');
        self::assertStringContainsString('"grades":{}', $answer->body, 'empty grades stay an object');
        $now = json_decode($answer->body, true)['countries'];
        self::assertSame([2, null], [$now['DE']['level'], $now['AT']['level']]);
        self::assertSame('pending', $now['AT']['status']);
        $late = json_decode($this->query->answer($this->at('14:00:00'), null)->body, true)['countries'];
        self::assertSame([null, true], [$late['DE']['level'], $late['DE']['stale']], 'no success since long');
    }

    private function store(Scope $scope, FeedStatus $status, string $json): void
    {
        $file = $this->snapshots->write(LayerId::from('warnings'), $scope, 'a1b2c3d4', $json);
        $at = $this->at('12:00:00');
        $this->states->write(new LayerState(LayerId::from('warnings'), $scope, 'a1b2c3d4', $file, $status, $at, $at, $at, 1, [], null, 0, null, 60, $at->plusSeconds(600)));
    }

    private function at(string $time): UtcInstant
    {
        return UtcInstant::fromIso('2026-10-28T' . $time . 'Z');
    }
}
