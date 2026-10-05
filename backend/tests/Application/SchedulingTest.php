<?php

declare(strict_types=1);

namespace CommonSight\Tests\Application;

use CommonSight\Application\RunOutcome;
use CommonSight\Application\StatusQuery;
use CommonSight\Domain\Schedule\StalenessPolicy;
use CommonSight\Domain\Status\ConditionalResponse;
use CommonSight\Domain\Status\StatusView;
use CommonSight\Model\FeedStatus;
use CommonSight\Model\Http\FailureKind;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\State\SourceHealth;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Tests\Support\CollectingLogger;
use CommonSight\Tests\Support\FakeClock;
use CommonSight\Tests\Support\FakeHttpClient;
use CommonSight\Tests\Support\Fixtures;
use CommonSight\Tests\Support\InMemoryTriggerStamp;
use CommonSight\Tests\Support\RefreshChain;
use CommonSight\Tests\Support\SnapshotContract;
use PHPUnit\Framework\TestCase;

/**
 * Concept "sources as plugins", 6.1, 6.2, V1; F-03 to F-05, A-02, A-04: the source is the unit of scheduling; a lane runs
 * its due sources and reassembles their layers; health, backoff, self-healing, fallback and status per source. Weather
 * AT has one source (Open-Meteo, lane heavy, every 1800 s by the terms of its free API); all other plugins answer from their recordings.
 */
final class SchedulingTest extends TestCase
{
    private FakeHttpClient $http;
    private CollectingLogger $log;
    private RefreshChain $chain;
    private LayerTarget $weather;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->http->respond('https://api.open-meteo.com/', Fixtures::caseBody('open-meteo-weather', 'at.json'));
        $this->log = new CollectingLogger();
        $this->chain = new RefreshChain($this->http, $this->log, new FakeClock($this->at('12:00:00')));
        $this->weather = new LayerTarget(LayerId::from('weather'), Scope::AT);
    }

    public function testLaneRunsItsSourcesAndAssemblesTheirLayers(): void
    {
        $report = $this->lane('12:00:00');

        self::assertSame(RunOutcome::Updated, $report->outcomes['AT-weather']);
        self::assertArrayNotHasKey('AT-warnings', $report->outcomes, 'warnings run in the fast lane');
        $state = $this->chain->states->read(LayerId::from('weather'), Scope::AT);
        self::assertNotNull($state);
        self::assertSame(9, $state->itemCount);
        self::assertSame(1800, $state->intervalSec, 'shortest interval of its sources');
        self::assertSame('2026-09-28T13:15:00Z', $state->staleAfter?->toIso(), 'last success + 2.5 × 1800 s');
        self::assertSame('2026-09-28T12:00:00Z', $this->chain->health->read('open-meteo-weather', Scope::AT)?->lastSuccessAt?->toIso());
        self::assertSame([], $this->chain->locks->held, 'locks free again');
        self::assertSame([], $this->chain->marker->running, 'no source left marked as running');
    }

    public function testSourcesRunOnlyWhenDue(): void
    {
        $this->lane('12:00:00');

        self::assertArrayNotHasKey('AT-weather', $this->lane('12:05:00')->outcomes, 'Open-Meteo not due yet');
        $later = $this->lane('12:30:00');
        self::assertSame(RunOutcome::Unchanged, $later->outcomes['AT-weather'], 'due after 1800 s, same content');
        self::assertCount(1, array_filter(array_keys($this->chain->snapshots->files), static fn(string $f): bool => str_starts_with($f, 'AT/weather.')));
    }

    /** A lane and a fallback pick their due sources independently: one that the other ran meanwhile is not fetched twice. */
    public function testASourceRunMeanwhileByAnotherProcessIsNotFetchedAgain(): void
    {
        $now = $this->at('12:00:00');
        $picked = $this->chain->due->among($this->chain->plans->of($this->weather), $now);
        self::assertCount(1, $picked);
        $this->chain->refresh->refresh($picked[0], $now, 'fallback-weather');
        $requests = count($this->http->requests);

        $this->chain->batch->run($picked, static fn(): UtcInstant => $now->plusSeconds(240), 'cron', 'heavy', $this->chain->due);

        self::assertCount($requests, $this->http->requests);
    }

    public function testBudgetAndBusyLane(): void
    {
        $report = $this->chain->laneRunner()->run('heavy', $this->at('12:00:00'));

        self::assertSame(count($this->chain->plans->inLane('heavy')), $report->skippedForBudget, 'no source starts after the deadline');
        $this->chain->locks->held['lane-heavy'] = true;
        self::assertTrue($this->lane('12:01:00')->busy);
    }

    public function testFailedSourceBacksOffAndTheLastSnapshotStays(): void
    {
        $this->lane('12:00:00');
        $this->http->respond('https://api.open-meteo.com/', FailureKind::ServerError);

        self::assertSame(RunOutcome::Failed, $this->lane('12:30:00')->outcomes['AT-weather'], 'its only source failed');
        self::assertArrayNotHasKey('AT-weather', $this->lane('12:30:30')->outcomes, 'backoff of the source');
        self::assertArrayNotHasKey('AT-weather', $this->lane('12:31:01')->outcomes, 'backoff over, but the terms allow no retry yet');
        self::assertSame(RunOutcome::Failed, $this->lane('13:00:01')->outcomes['AT-weather']);

        $health = $this->chain->health->read('open-meteo-weather', Scope::AT);
        self::assertSame(2, $health?->consecutiveFailures);
        self::assertSame('2026-09-28T13:02:01Z', $health?->backoffUntil?->toIso(), 'second failure: 2 min');
        $state = $this->chain->states->read(LayerId::from('weather'), Scope::AT);
        self::assertSame('error.allSourcesFailed', $state?->lastError?->message->key);
        self::assertSame('2026-09-28T12:00:00Z', $state?->checkedAt?->toIso(), 'last good state stays');
        self::assertSame('2026-09-28T13:15:00Z', $state?->staleAfter?->toIso(), 'stale from the last success on');
    }

    /** No source starts when its typical run time no longer fits before the deadline; it stays due (D8). */
    public function testASourceDoesNotStartShortlyBeforeTheDeadline(): void
    {
        $this->chain->clock->now = $this->at('12:00:00');
        $report = $this->chain->laneRunner()->run('heavy', $this->at('12:00:02'));

        self::assertSame(count($this->chain->plans->inLane('heavy')), $report->skippedForBudget, 'under 3 s left: none starts');
        self::assertNull($this->chain->health->read('open-meteo-weather', Scope::AT), 'not run, not failed');
    }

    /** A run that ran out of the process's time is not the source's failure: last outcome and health stay. */
    public function testRunningOutOfTimeKeepsTheLastOutcome(): void
    {
        $this->lane('12:00:00');
        $this->http->respond('https://api.open-meteo.com/', FailureKind::BudgetExhausted);

        $this->lane('12:30:00');

        $health = $this->chain->health->read('open-meteo-weather', Scope::AT);
        self::assertSame(0, $health?->consecutiveFailures, 'no failure, no backoff');
        self::assertSame('2026-09-28T12:00:00Z', $health?->lastSuccessAt?->toIso());
        self::assertSame(9, $this->chain->states->read(LayerId::from('weather'), Scope::AT)?->itemCount, 'the layer keeps its items');
        self::assertContains('source.outOfTime', array_column($this->log->entries, 1));
    }

    public function testASourceLeftRunningByADeadProcessCountsAsFailed(): void
    {
        $this->chain->marker->start('heavy', 'open-meteo-weather-AT', $this->at('11:59:00'));

        $report = $this->lane('12:00:00');

        $health = $this->chain->health->read('open-meteo-weather', Scope::AT);
        self::assertSame(1, $health?->consecutiveFailures);
        self::assertStringStartsWith('crashed', (string) $health?->lastFailure);
        self::assertArrayNotHasKey('AT-weather', $report->outcomes, 'in backoff, the others ran');
        self::assertArrayHasKey('DE-weather', $report->outcomes);
        self::assertContains('source.crashed', $this->log->events());
    }

    /** A fallback ran the source after the lane died in it: its fresh result is not overwritten with a crash. */
    public function testACrashIsNotRecordedOverALaterRun(): void
    {
        $this->chain->marker->start('heavy', 'open-meteo-weather-AT', $this->at('11:50:00'));
        $this->chain->health->write(new SourceHealth('open-meteo-weather', Scope::AT, $this->at('11:55:00'), $this->at('11:55:00'), 0, null, null, 9, 1200, RefreshChain::RELEASE));

        $this->lane('12:00:00');

        $health = $this->chain->health->read('open-meteo-weather', Scope::AT);
        self::assertSame(0, $health?->consecutiveFailures);
        self::assertSame(1200, $health?->typicalDurationMs);
        self::assertSame([], $this->chain->marker->running, 'the marker is cleared all the same');
    }

    public function testLayerWithoutSourcesIsAssembledOnce(): void
    {
        self::assertSame(RunOutcome::Updated, $this->lane('12:00:00')->outcomes['CH-traffic']);
        self::assertSame(FeedStatus::Setup, $this->chain->states->read(LayerId::from('traffic'), Scope::CH)?->status);
        self::assertArrayNotHasKey('CH-traffic', $this->lane('12:20:00')->outcomes);
    }

    public function testFallbackRefreshesTheDueSourcesOfTheMostStaleLayer(): void
    {
        $stamps = new InMemoryTriggerStamp();
        $trigger = $this->chain->fallbackTrigger($stamps);

        self::assertSame(RunOutcome::Updated, $trigger->trigger([[$this->weather, null]], $this->at('12:00:00')));
        self::assertNull($trigger->trigger([[$this->weather, null]], $this->at('12:00:30')), 'nothing due any more');
        $fresh = [[$this->weather, $this->chain->states->read(LayerId::from('weather'), Scope::AT)]];
        self::assertNull($trigger->trigger($fresh, $this->at('12:10:00')), 'not stale');
    }

    /** A process that died in a fallback (e.g. PHP-FPM's memory limit) is recorded by the next one, with backoff. */
    public function testAFallbackThatDiedIsRecordedAsACrash(): void
    {
        $this->chain->marker->running['fallback-at-weather'] = 'open-meteo-weather-AT';
        $trigger = $this->chain->fallbackTrigger(new InMemoryTriggerStamp());

        self::assertNull($trigger->trigger([[$this->weather, null]], $this->at('12:00:00')), 'the crashed source is in backoff now');
        $health = $this->chain->health->read('open-meteo-weather', Scope::AT);
        self::assertSame(1, $health?->consecutiveFailures);
        self::assertNotNull($health?->backoffUntil);
        self::assertSame([], $this->chain->marker->running);
    }

    /** A fallback still running in another request is not taken for a dead one. */
    public function testARunningFallbackIsNotHealedByAnother(): void
    {
        $this->chain->marker->running['fallback-at-weather'] = 'open-meteo-weather-AT';
        $this->chain->locks->held['fallback-at-weather'] = true;

        self::assertNull($this->chain->fallbackTrigger(new InMemoryTriggerStamp())->trigger([[$this->weather, null]], $this->at('12:00:00')));
        self::assertNull($this->chain->health->read('open-meteo-weather', Scope::AT), 'no crash recorded');
        self::assertSame(['fallback-at-weather' => 'open-meteo-weather-AT'], $this->chain->marker->running);
    }

    /** A layer whose snapshot file was removed (e.g. reinstalled after housekeeping) gets it written again. */
    public function testAMissingSnapshotFileIsWrittenAgain(): void
    {
        $this->lane('12:00:00');
        $file = $this->chain->states->read(LayerId::from('weather'), Scope::AT)?->file;
        self::assertNotNull($file);
        unset($this->chain->snapshots->files[$file]);

        self::assertSame(RunOutcome::Updated, $this->chain->batch->publishLayer($this->weather, 'manual'), 'same content, but the file is gone');
        self::assertArrayHasKey($file, $this->chain->snapshots->files);
        self::assertSame(RunOutcome::Unchanged, $this->chain->batch->publishLayer($this->weather, 'manual'));
    }

    public function testFallbackSkipsAStaleLayerWhoseSourcesAreInBackoff(): void
    {
        $this->chain->health->write(new SourceHealth('open-meteo-weather', Scope::AT, $this->at('11:59:00'), null, 3, $this->at('12:10:00'), 'timeout', 0, null, RefreshChain::RELEASE));
        $space = new LayerTarget(LayerId::from('space'), Scope::Global);

        $outcome = $this->chain->fallbackTrigger(new InMemoryTriggerStamp())->trigger([[$this->weather, null], [$space, null]], $this->at('12:00:00'));

        self::assertSame(RunOutcome::Updated, $outcome, 'the next stale layer with due sources');
        self::assertNull($this->chain->states->read(LayerId::from('weather'), Scope::AT));
        self::assertNotNull($this->chain->states->read(LayerId::from('space'), Scope::Global));
    }

    /** A layer whose fallback another request is running does not hold up the next stale layer. */
    public function testFallbackOfABusyLayerGoesOnToTheNextStaleLayer(): void
    {
        $space = new LayerTarget(LayerId::from('space'), Scope::Global);
        $this->chain->locks->held['fallback-at-weather'] = true;

        $outcome = $this->chain->fallbackTrigger(new InMemoryTriggerStamp())->trigger([[$this->weather, null], [$space, null]], $this->at('12:00:00'));

        self::assertSame(RunOutcome::Updated, $outcome);
        self::assertNull($this->chain->states->read(LayerId::from('weather'), Scope::AT));
        self::assertNotNull($this->chain->states->read(LayerId::from('space'), Scope::Global));
    }

    public function testFallbackLeavesOutLanesWithoutFallback(): void
    {
        $chain = new RefreshChain($this->http, $this->log, new FakeClock($this->at('12:00:00')), ['sources' => ['open-meteo-weather' => ['lane' => 'slow']]]);

        self::assertNull($chain->fallbackTrigger(new InMemoryTriggerStamp())->trigger([[$this->weather, null]], $this->at('12:00:00')));
        self::assertSame(RunOutcome::Updated, $chain->laneRunner()->run('slow', $this->at('12:10:00'))->outcomes['AT-weather'] ?? null, 'the slow lane runs it');
    }

    public function testStatusAnswerIsValidConditionalAndComesFromTheSources(): void
    {
        $this->lane('12:00:00');
        $query = new StatusQuery($this->chain->states, new StatusView(new StalenessPolicy(2.5)), new ConditionalResponse(), Fixtures::generated()->layerMetas());

        $answer = $query->answer(Scope::AT, $this->at('12:01:00'), null);
        SnapshotContract::assertJsonValid($answer->body, 'status.schema.json');
        $body = json_decode($answer->body, true);
        self::assertSame('ok', $body['layers']['weather']['status']);
        self::assertSame(1800, $body['layers']['weather']['intervalSec']);
        self::assertFalse($body['layers']['weather']['stale']);
        self::assertSame('data/v1/' . $this->chain->states->read(LayerId::from('weather'), Scope::AT)?->file, $body['layers']['weather']['url']);
        self::assertSame('pending', $body['layers']['warnings']['status'], 'A-04: the fast lane has not run');
        self::assertTrue($body['layers']['warnings']['stale']);
        self::assertSame('global', $body['layers']['news']['scope']);
        self::assertEquals(200, $body['vicinityKm'], 'the vicinity for the texts of the frontend');
        $borderLayers = array_values(array_map(static fn($meta): string => $meta->id->value, array_filter(Fixtures::generated()->layerMetas(), static fn($meta): bool => $meta->border)));
        self::assertNotSame([], $borderLayers);
        self::assertSame($borderLayers, array_keys($body['border']), 'the layers with data from the border zone');
        self::assertSame('border', $body['border']['weather']['scope']);
        self::assertSame(304, $query->answer(Scope::AT, $this->at('12:01:30'), $answer->etag)->status);
        $late = json_decode($query->answer(Scope::AT, $this->at('13:16:00'), null)->body, true);
        self::assertTrue($late['layers']['weather']['stale'], 'no success for 2.5 × its interval');
    }

    private function lane(string $time): \CommonSight\Application\LaneReport
    {
        $this->chain->clock->now = $this->at($time);

        return $this->chain->laneRunner()->run('heavy', $this->at($time)->plusSeconds(240));
    }

    private function at(string $time): UtcInstant
    {
        return UtcInstant::fromIso('2026-09-28T' . $time . 'Z');
    }
}
