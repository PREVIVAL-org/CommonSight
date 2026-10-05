<?php

declare(strict_types=1);

namespace CommonSight\Entry;

use CommonSight\Application\FallbackTrigger;
use CommonSight\Application\StatusQuery;
use CommonSight\Config\Config;
use CommonSight\Domain\Schedule\StalenessPolicy;
use CommonSight\Domain\Status\ConditionalResponse;
use CommonSight\Domain\Status\ScopeParameter;
use CommonSight\Domain\Status\StatusView;
use CommonSight\Infrastructure\Contract\GeneratedData;
use CommonSight\Infrastructure\Http\ResponseCompletion;

/** Builds the objects of the status endpoint; the fallback only after the response has been sent (Architecture 6.3). */
final class StatusEndpointFactory
{
    public function __construct(private readonly Config $config, private readonly GeneratedData $data) {}

    public function endpoint(): StatusEndpoint
    {
        return new StatusEndpoint(new ScopeParameter(), $this->statusQuery(), new ResponseCompletion(), $this);
    }

    public function statusQuery(): StatusQuery
    {
        $fetcher = new FetcherFactory($this->config, $this->data, 'status', microtime(true));

        return new StatusQuery(
            $fetcher->stateReader(),
            new StatusView($this->stalenessPolicy(), 'data/v1/', Vicinity::km($this->config, $this->data)),
            new ConditionalResponse(),
            $this->data->layerMetas(),
        );
    }

    public function stalenessPolicy(): StalenessPolicy
    {
        return new StalenessPolicy($this->config->staleFactor);
    }

    /** Time limit of a request that runs the fallback: the longest fallback budget of the lanes. */
    public function fallbackBudgetSec(): int
    {
        return $this->config->lanes->maxFallbackSec();
    }

    public function fallbackTrigger(): FallbackTrigger
    {
        return (new FetcherFactory($this->config, $this->data, 'fallback', microtime(true) + $this->fallbackBudgetSec()))->fallbackTrigger();
    }
}
