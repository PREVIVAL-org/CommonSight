<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Source\DriftPolicy;
use CommonSight\Domain\Source\RegisteredSource;
use CommonSight\Domain\Source\SourceResult;
use CommonSight\Domain\Source\SourceSelection;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\Logger;
use CommonSight\Port\MemoryUsage;
use CommonSight\Sdk\Plugin\SourceRun;
use CommonSight\Sdk\Source\Deficit;
use CommonSight\Sdk\Source\DeficitKind;
use CommonSight\Sdk\Source\ParseStatistics;
use CommonSight\Sdk\Source\SourceExpectations;

/**
 * Runs one source for a scope, isolated: whatever goes wrong in it becomes its failed result, never a failure of the
 * layer or of the other sources (concept: sources as plugins, 6.2). Skips a source when too little memory is left,
 * because running out of memory cannot be caught. Judges the source's numbers against its expectations (F-18).
 */
final class PluginRunner
{
    /** A source starts only while less than this share of the memory limit is used. */
    private const MAX_MEMORY_SHARE = 0.75;

    public function __construct(
        private readonly SourceSelection $selection,
        private readonly DriftPolicy $drift,
        private readonly MemoryUsage $memory,
        private readonly Logger $log,
    ) {}

    public function run(RegisteredSource $source, Scope $scope, UtcInstant $now): SourceResult
    {
        $description = $source->description;
        if (!$this->selection->isEnabled($description->id)) {
            return SourceResult::disabled($description);
        }
        if ($source->missingSecrets !== []) {
            $this->log->log('notice', 'source.unconfigured', ['source' => $description->id, 'scope' => $scope->value, 'missing' => $source->missingSecrets]);

            return SourceResult::unconfigured($description);
        }
        if ($this->lacksMemory()) {
            $this->log->log('warning', 'source.skippedMemory', ['source' => $description->id, 'scope' => $scope->value, 'usedMb' => round($this->memory->usedBytes() / 1_048_576, 1)]);

            return SourceResult::failure($description, 'memory');
        }
        try {
            $outcome = $source->plugin()->fetch(new SourceRun($scope, $now));
        } catch (\Throwable $e) {
            // An error in a source must not end the run of the others: the source counts as failed.
            $this->log->log('error', 'source.exception', ['source' => $description->id, 'scope' => $scope->value, 'error' => $e::class . ': ' . $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);

            return SourceResult::failure($description, 'internal: ' . $e::class);
        }
        foreach ($outcome->diagnostics as $diagnostic) {
            $this->log->log($diagnostic->level, $diagnostic->event, ['source' => $description->id, 'scope' => $scope->value] + $diagnostic->context);
        }
        if (!$outcome->succeeded()) {
            $this->log->log('warning', 'source.failed', ['source' => $description->id, 'scope' => $scope->value, 'reason' => $outcome->failure, 'retryAfterSec' => $outcome->retryAfterSec]);

            return SourceResult::failure($description, (string) $outcome->failure, $outcome->retryAfterSec);
        }
        $statistics = $outcome->statistics ?? new ParseStatistics(0, 0, 0);
        $drift = $this->drift($description->id, $scope, $statistics, $description->expectations);

        return SourceResult::success($description, $outcome->items, [...$drift, ...$outcome->deficits], $statistics, $outcome->sourceUpdatedAt, $outcome->coverage);
    }

    private function lacksMemory(): bool
    {
        $limit = $this->memory->limitBytes();

        return $limit !== null && $this->memory->usedBytes() > $limit * self::MAX_MEMORY_SHARE;
    }

    /** @return list<Deficit> */
    private function drift(string $sourceId, Scope $scope, ParseStatistics $statistics, SourceExpectations $expectations): array
    {
        $deficits = $this->drift->evaluate($statistics, $expectations);
        foreach ($deficits as $deficit) {
            if ($deficit->kind === DeficitKind::FormatDrift || $deficit->kind === DeficitKind::BelowExpected) {
                $this->log->log('warning', 'drift', ['source' => $sourceId, 'scope' => $scope->value, 'kind' => $deficit->kind->value] + $deficit->params);
            }
        }

        return $deficits;
    }
}
