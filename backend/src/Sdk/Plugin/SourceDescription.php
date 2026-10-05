<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Source\SourceExpectations;

/** Everything the core needs to know about a source, supplied by the source itself; checks itself on creation. */
final readonly class SourceDescription
{
    /**
     * @param list<Scope> $scopes scopes the source delivers data for
     * @param list<string> $secrets names of the secrets the source needs (API keys, tokens), supplied by the configuration
     */
    public function __construct(
        /** stable id, e.g. 'pegelonline'; also the namespace of its texts and the key in the configuration */
        public string $id,
        /** display name, e.g. 'WSV · PEGELONLINE' */
        public string $name,
        public Attribution $attribution,
        /** id of the layer the source belongs to; the core checks that it exists */
        public string $layer,
        public array $scopes,
        public SourceSchedule $schedule,
        /** emptyIsFailure, minimumValid, … (Q-02, F-18); judged by the core */
        public SourceExpectations $expectations,
        public HttpBudget $http = new HttpBudget(),
        public array $secrets = [],
        /** rank within the layer on equal criticality when merging items, smaller first */
        public int $order = 100,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Invalid source id: ' . $id);
        }
        if (trim($name) === '' || preg_match('/^[a-z][a-z0-9-]{1,30}$/', $layer) !== 1) {
            throw new \InvalidArgumentException('Source ' . $id . ': name or layer missing or invalid');
        }
        if ($scopes === [] || count(array_unique(array_map(static fn(Scope $s): string => $s->value, $scopes))) !== count($scopes)) {
            throw new \InvalidArgumentException('Source ' . $id . ': scopes empty or duplicated');
        }
        foreach ($secrets as $secret) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9]{0,40}$/', $secret) !== 1) {
                throw new \InvalidArgumentException('Source ' . $id . ': invalid secret name ' . $secret);
            }
        }
    }
}
