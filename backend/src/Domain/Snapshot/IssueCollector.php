<?php

declare(strict_types=1);

namespace CommonSight\Domain\Snapshot;

use CommonSight\Domain\Source\SourceResult;
use CommonSight\Domain\Source\SourceState;
use CommonSight\Model\Msg;
use CommonSight\Sdk\Source\Deficit;
use CommonSight\Sdk\Source\DeficitKind;

/** Translates failed, disabled and incomplete sources of a layer into issues as Msg (D-03). */
final class IssueCollector
{
    /**
     * @param list<SourceResult> $results
     * @return list<Msg>
     */
    public function collect(array $results): array
    {
        $issues = [];
        foreach ($results as $result) {
            array_push($issues, ...$this->issuesOf($result));
        }

        return $issues;
    }

    /** @return list<Msg> */
    private function issuesOf(SourceResult $result): array
    {
        $params = ['source' => $result->sourceName];

        return match ($result->outcome) {
            SourceState::Disabled => [new Msg('issue.sourceDisabled', $params)],
            SourceState::Unconfigured => [new Msg('issue.sourceUnconfigured', $params)],
            SourceState::Failed => [new Msg('issue.sourceFailed', $params)],
            SourceState::Pending => [new Msg('issue.sourcePending', $params)],
            SourceState::Succeeded => $this->deficitIssues($result),
        };
    }

    /** @return list<Msg> */
    private function deficitIssues(SourceResult $result): array
    {
        if ($result->expectations->emptyIsFailure && $result->items === []) {
            return [new Msg('issue.sourceFailed', ['source' => $result->sourceName])];
        }
        $issues = [];
        foreach ($result->deficits as $deficit) {
            if ($this->counts($result, $deficit)) {
                $issues[] = new Msg($deficit->messageKey(), ['source' => $result->sourceName] + $deficit->params);
            }
        }

        return $issues;
    }

    /** Discarded records only count for sources where every expected record counts (e.g. per place, Q-WE-04). */
    private function counts(SourceResult $result, Deficit $deficit): bool
    {
        return $deficit->kind !== DeficitKind::InvalidRecords || $result->expectations->rejectedMeansPartial;
    }
}
