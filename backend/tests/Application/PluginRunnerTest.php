<?php

declare(strict_types=1);

namespace CommonSight\Tests\Application;

use CommonSight\Application\PluginRunner;
use CommonSight\Domain\Snapshot\IssueCollector;
use CommonSight\Domain\Snapshot\SnapshotAssembler;
use CommonSight\Domain\Source\DriftPolicy;
use CommonSight\Domain\Source\RegisteredSource;
use CommonSight\Domain\Source\SourceSelection;
use CommonSight\Domain\Source\SourceState;
use CommonSight\Model\FeedStatus;
use CommonSight\Model\Item\EarthquakeItem;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Sdk\Layer\EmptyStatsBuilder;
use CommonSight\Sdk\Layer\LayerDefinition;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourceOutcome;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourceRun;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\DeficitKind;
use CommonSight\Sdk\Source\ParseStatistics;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Tests\Support\CollectingLogger;
use CommonSight\Tests\Support\FixedMemory;
use PHPUnit\Framework\TestCase;

/** Concept "sources as plugins", 6.2: a failing source never cancels the others; drift is judged by the core (F-18). */
final class PluginRunnerTest extends TestCase
{
    private CollectingLogger $log;

    protected function setUp(): void
    {
        $this->log = new CollectingLogger();
    }

    public function testAnExceptionInOnePluginDoesNotCancelTheOthers(): void
    {
        $results = [
            $this->runner()->run($this->source('broken', static fn(): SourceOutcome => throw new \RuntimeException('parser crashed')), Scope::AT, $this->now()),
            $this->runner()->run($this->source('working', fn(): SourceOutcome => SourceOutcome::success([$this->quake('q1')], new ParseStatistics(1, 0, 0))), Scope::AT, $this->now()),
        ];
        $layer = new LayerDefinition(LayerId::from('nature'), Scope::AT, 'Test', 'https://example.org/', new Msg('note.nature'), [], new EmptyStatsBuilder());

        $snapshot = (new SnapshotAssembler(new IssueCollector()))->assemble($layer, $results, $this->now())->snapshot;

        self::assertNotNull($snapshot);
        self::assertSame(FeedStatus::Partial, $snapshot->status);
        self::assertSame(['q1'], array_map(static fn($item): string => $item->common()->id, $snapshot->items));
        self::assertSame(['issue.sourceFailed'], array_map(static fn(Msg $m): string => $m->key, $snapshot->issues));
        self::assertSame(['source.exception'], $this->log->events());
        self::assertStringContainsString('parser crashed', (string) $this->log->entries[0][2]['error']);
    }

    public function testAPluginErrorIsCaughtAsWell(): void
    {
        $result = $this->runner()->run($this->source('typo', static fn(): SourceOutcome => throw new \TypeError('wrong type')), Scope::AT, $this->now());

        self::assertSame(SourceState::Failed, $result->outcome);
        self::assertSame('internal: TypeError', $result->failureReason);
    }

    public function testAFailureOutcomeIsLoggedWithTheWaitingTime(): void
    {
        $result = $this->runner()->run($this->source('limited', static fn(): SourceOutcome => SourceOutcome::failure('rateLimited: HTTP 429', 600)), Scope::AT, $this->now());

        self::assertSame(SourceState::Failed, $result->outcome);
        self::assertSame([['warning', 'source.failed', ['source' => 'limited', 'scope' => 'AT', 'reason' => 'rateLimited: HTTP 429', 'retryAfterSec' => 600]]], $this->log->entries);
    }

    public function testADisabledSourceIsNotRun(): void
    {
        $runner = new PluginRunner(new SourceSelection(['off' => false]), new DriftPolicy(), new FixedMemory(), $this->log);

        $result = $runner->run($this->source('off', static fn(): SourceOutcome => throw new \LogicException('must not run')), Scope::AT, $this->now());

        self::assertSame(SourceState::Disabled, $result->outcome);
    }

    /** Concept "sources as plugins", 5: without its API key a source does not run; its layer shows "to be set up". */
    public function testASourceWithoutItsSecretsIsNotRunAndShownAsNotSetUp(): void
    {
        $plain = $this->source('keyed', static fn(): SourceOutcome => throw new \LogicException('must not run'));
        $source = new RegisteredSource($plain->description, static fn(): SourcePlugin => $plain->plugin(), null, ['apiKey']);

        $result = $this->runner()->run($source, Scope::AT, $this->now());
        $layer = new LayerDefinition(LayerId::from('nature'), Scope::AT, 'Test', 'https://example.org/', new Msg('note.nature'), [], new EmptyStatsBuilder());
        $snapshot = (new SnapshotAssembler(new IssueCollector()))->assemble($layer, [$result], $this->now())->snapshot;

        self::assertSame(SourceState::Unconfigured, $result->outcome);
        self::assertSame(FeedStatus::Setup, $snapshot?->status);
        self::assertSame(['issue.sourceUnconfigured'], array_map(static fn(Msg $m): string => $m->key, $snapshot->issues ?? []));
    }

    public function testSkipsASourceWhenMemoryRunsShort(): void
    {
        $runner = new PluginRunner(new SourceSelection([]), new DriftPolicy(), new FixedMemory(200 * 1_048_576, 256 * 1_048_576), $this->log);

        $result = $runner->run($this->source('big', static fn(): SourceOutcome => throw new \LogicException('must not run')), Scope::AT, $this->now());

        self::assertSame(SourceState::Failed, $result->outcome);
        self::assertSame('memory', $result->failureReason);
        self::assertSame(['source.skippedMemory'], $this->log->events());
    }

    public function testJudgesDriftBeforeTheDeficitsOfThePlugin(): void
    {
        $outcome = fn(): SourceOutcome => SourceOutcome::success([$this->quake('q1')], new ParseStatistics(1, 3, 0));

        $result = $this->runner()->run($this->source('drifting', $outcome), Scope::AT, $this->now());

        self::assertSame([DeficitKind::FormatDrift], array_map(static fn($d): DeficitKind => $d->kind, $result->deficits));
        self::assertSame(['drift'], $this->log->events());
    }

    private function runner(): PluginRunner
    {
        return new PluginRunner(new SourceSelection([]), new DriftPolicy(), new FixedMemory(), $this->log);
    }

    /** @param \Closure(): SourceOutcome $fetch */
    private function source(string $id, \Closure $fetch): RegisteredSource
    {
        $description = new SourceDescription(
            id: $id,
            name: ucfirst($id),
            attribution: new Attribution(ucfirst($id), 'https://example.org/'),
            layer: 'nature',
            scopes: [Scope::AT],
            schedule: new SourceSchedule(60),
            expectations: SourceExpectations::events(),
        );
        $plugin = new class ($fetch) implements SourcePlugin {
            /** @param \Closure(): SourceOutcome $fetch */
            public function __construct(private readonly \Closure $fetch) {}

            public function fetch(SourceRun $run): SourceOutcome
            {
                return ($this->fetch)();
            }
        };

        return new RegisteredSource($description, static fn(): SourcePlugin => $plugin);
    }

    private function quake(string $id): EarthquakeItem
    {
        return new EarthquakeItem(new ItemCommon($id, 'Beben', 'https://example.org/' . $id), 3.1, 10.0, 'Tirol');
    }

    private function now(): UtcInstant
    {
        return UtcInstant::fromIso('2026-10-03T12:00:00Z');
    }
}
