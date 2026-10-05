<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Application\LaneReport;
use CommonSight\Config\Config;
use CommonSight\Config\ConfigError;
use CommonSight\Domain\Schedule\SourcePlan;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Infrastructure\Storage\SnapshotCleaner;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;

/**
 * Command-line entry point: run (lane, single layer or everything), check, status, sources, housekeeping
 * (Architecture 4.12; concept: sources as plugins, 6.1).
 *
 * Exit codes: 0 = ran (also with partial failures), 1 = configuration error, 2 = at least one layer failed, 3 = lane lock busy,
 * 4 = internal error.
 */
final class FetcherCli
{
    public const OK = 0;
    public const CONFIG_ERROR = 1;
    public const LAYER_FAILED = 2;
    public const LANE_BUSY = 3;
    /** An error nobody expected: reported on stderr (cron.log) instead of ending as a PHP fatal error. */
    public const INTERNAL_ERROR = 4;
    /** Time budget of a run by hand. */
    private const MANUAL_BUDGET_SEC = 600;

    public function __construct(private readonly string $releaseDir) {}

    /** @param list<string> $argv */
    public function main(array $argv): int
    {
        $options = $this->options(array_slice($argv, 2));
        try {
            $config = (new ConfigLoader())->load($options['config'] ?? null, $this->releaseDir);
            $data = new GeneratedData($this->releaseDir . '/generated');

            return match ($argv[1] ?? '') {
                'run' => $this->run($config, $data, $options),
                'check' => $this->check($config, $data),
                'status' => $this->status($config, $data),
                'sources' => $this->sources($config, $data),
                'housekeeping' => $this->housekeeping($config, $data),
                default => $this->usage(),
            };
        } catch (ConfigError $e) {
            fwrite(STDERR, 'Configuration error: ' . $e->getMessage() . "\n");

            return self::CONFIG_ERROR;
        } catch (\Throwable $e) {
            fwrite(STDERR, sprintf("Internal error: %s: %s at %s:%d\n", $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

            return self::INTERNAL_ERROR;
        }
    }

    /** @param array<string, string> $options */
    private function run(Config $config, GeneratedData $data, array $options): int
    {
        if (isset($options['lane'])) {
            return $this->runLane($config, $data, $options['lane']);
        }
        $factory = new FetcherFactory($config, $data, 'manual', microtime(true) + self::MANUAL_BUDGET_SEC);
        $deadline = UtcInstant::fromTimestamp(time() + self::MANUAL_BUDGET_SEC);
        if (isset($options['all'])) {
            $targets = $factory->targets();
        } else {
            $layer = LayerId::tryFrom($options['layer'] ?? '');
            $scope = Scope::tryFrom($options['scope'] ?? 'global');
            if ($layer === null || $scope === null) {
                return $this->usage();
            }
            $targets = [$this->target($factory, $layer, $scope)];
        }
        // By hand every source of the layers runs at once, regardless of its interval and backoff.
        $plans = array_merge(...array_map(static fn(LayerTarget $t): array => $factory->plans()->of($t), $targets));
        $report = $factory->batch()->run($plans, static fn(SourcePlan $plan): UtcInstant => $deadline, 'manual');
        $outcomes = $report->outcomes;
        foreach ($targets as $target) {
            $outcomes[$target->name()] ??= $factory->batch()->publishLayer($target, 'manual');
        }

        return $this->report(new LaneReport(false, $outcomes, $report->skippedForBudget));
    }

    private function runLane(Config $config, GeneratedData $data, string $lane): int
    {
        $budget = $config->lanes->budget($lane);
        $factory = new FetcherFactory($config, $data, 'cron-' . $lane, microtime(true) + $budget);
        $report = $factory->laneRunner()->run($lane, UtcInstant::fromTimestamp(time() + $budget));
        if ($report->busy) {
            return self::LANE_BUSY;
        }
        if ($report->skippedForBudget > 0) {
            $factory->logger()->log('warning', 'lane.budgetExhausted', ['lane' => $lane, 'skipped' => $report->skippedForBudget]);
        }

        return $this->report($report);
    }

    private function report(LaneReport $report): int
    {
        foreach ($report->outcomes as $name => $outcome) {
            echo $name, ': ', $outcome->value, "\n";
        }
        if ($report->skippedForBudget > 0) {
            echo 'sources skipped (budget): ', $report->skippedForBudget, "\n";
        }

        return $report->hasFailures() ? self::LAYER_FAILED : self::OK;
    }

    private function target(FetcherFactory $factory, LayerId $layer, Scope $scope): LayerTarget
    {
        foreach ($factory->targets() as $target) {
            if ($target->layer === $layer && $target->scope === $scope) {
                return $target;
            }
        }

        throw new ConfigError(sprintf('Layer %s does not exist for %s', $layer->value, $scope->value));
    }

    private function check(Config $config, GeneratedData $data): int
    {
        $factory = new FetcherFactory($config, $data, 'check', microtime(true));
        $failed = 0;
        foreach ((new EnvironmentCheck())->run($config, $data, $factory) as [$ok, $description]) {
            echo $ok ? '  ✓ ' : '  ✗ ', $description, "\n";
            $failed += $ok ? 0 : 1;
        }

        return $failed === 0 ? self::OK : self::CONFIG_ERROR;
    }

    private function status(Config $config, GeneratedData $data): int
    {
        $factory = new FetcherFactory($config, $data, 'status', microtime(true));
        $reader = $factory->stateReader();
        printf("%-18s %-8s %-9s %-21s %6s %8s  %s\n", 'Layer', 'Status', 'Version', 'checked', 'Items', 'Interval', 'Error');
        foreach ($factory->targets() as $target) {
            $state = $reader->read($target->layer, $target->scope);
            printf(
                "%-18s %-8s %-9s %-21s %6d %8s  %s\n",
                $target->name(),
                $state?->status->value ?? 'pending',
                $state->version ?? '-',
                $state?->checkedAt?->toIso() ?? '-',
                $state->itemCount ?? 0,
                $state?->intervalSec === null ? '-' : $state->intervalSec . ' s',
                $state?->lastError !== null ? $state->lastError->message->key . ' (' . $state->consecutiveFailures . '×)' : '',
            );
        }

        return self::OK;
    }

    /** Health of every source in every scope: lane, interval, last success, failures, backoff, typical duration (D8). */
    private function sources(Config $config, GeneratedData $data): int
    {
        $factory = new FetcherFactory($config, $data, 'sources', microtime(true));
        $health = $factory->healthStore();
        printf("%-30s %-6s %6s %-21s %4s %-21s %8s %6s  %s\n", 'Source', 'Lane', 'Every', 'last success', 'Fail', 'backoff until', 'typical', 'Items', 'last failure');
        foreach ($factory->plans()->all() as $plan) {
            $state = $health->read($plan->id(), $plan->scope);
            printf(
                "%-30s %-6s %5ds %-21s %4d %-21s %8s %6d  %s\n",
                $plan->key(),
                $plan->lane,
                $plan->intervalSec,
                $state?->lastSuccessAt?->toIso() ?? '-',
                $state->consecutiveFailures ?? 0,
                $state?->backoffUntil?->toIso() ?? '-',
                $state?->typicalDurationMs === null ? '-' : $state->typicalDurationMs . ' ms',
                $state->itemCount ?? 0,
                $state->lastFailure ?? '',
            );
        }

        return self::OK;
    }

    private function housekeeping(Config $config, GeneratedData $data): int
    {
        $factory = new FetcherFactory($config, $data, 'housekeeping', microtime(true));
        $result = (new Housekeeping($config->paths, new SnapshotCleaner($config->paths->data, $factory->stateReader())))->run($factory->targets(), time());
        $factory->logger()->log('info', 'housekeeping', $result);

        return self::OK;
    }

    private function usage(): int
    {
        fwrite(STDERR, "Usage: fetcher.php run --lane <lane> | run --layer <layer> --scope <DE|AT|CH|global|border> | run --all | check | status | sources | housekeeping [--config=<path>]\n");

        return self::CONFIG_ERROR;
    }

    /**
     * @param list<string> $arguments
     * @return array<string, string>
     */
    private function options(array $arguments): array
    {
        $options = [];
        for ($i = 0; $i < count($arguments); $i++) {
            if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $arguments[$i], $m) !== 1) {
                continue;
            }
            // `--layer water` takes the next argument; a flag such as `--all` takes no option that follows it.
            $next = $arguments[$i + 1] ?? null;
            $takesNext = !isset($m[2]) && $next !== null && !str_starts_with($next, '--');
            $options[$m[1]] = $m[2] ?? ($takesNext ? $next : '');
            if ($takesNext) {
                $i++;
            }
        }

        return $options;
    }
}
