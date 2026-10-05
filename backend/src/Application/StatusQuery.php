<?php

declare(strict_types=1);

namespace CommonSight\Application;

use CommonSight\Domain\Status\ConditionalResponse;
use CommonSight\Domain\Status\StatusView;
use CommonSight\Model\Layer\LayerMeta;
use CommonSight\Model\Layer\LayerTarget;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;
use CommonSight\Port\StateReader;

/**
 * Has the state files of a country, of the global layers and of the border zone read and assembled into the response
 * of the status endpoint.
 */
final class StatusQuery
{
    /** @param list<LayerMeta> $layers */
    public function __construct(
        private readonly StateReader $states,
        private readonly StatusView $view,
        private readonly ConditionalResponse $conditional,
        private readonly array $layers,
    ) {}

    public function answer(Scope $country, UtcInstant $now, ?string $ifNoneMatch): StatusAnswer
    {
        $candidates = [];
        $border = [];
        foreach ($this->layers as $meta) {
            $target = new LayerTarget($meta->id, $meta->scopeFor($country));
            $candidates[] = [$target, $this->states->read($target->layer, $target->scope)];
            if ($meta->border) {
                $borderTarget = new LayerTarget($meta->id, Scope::Border);
                $border[] = [$borderTarget, $this->states->read($borderTarget->layer, $borderTarget->scope)];
            }
        }
        $layers = $this->view->layers($candidates, $now, $border);
        // The border snapshots also take part in the fallback (the most stale layer is reloaded).
        $candidates = [...$candidates, ...$border];
        $etag = $this->conditional->etag($layers);
        if ($this->conditional->isNotModified($ifNoneMatch, $etag)) {
            return new StatusAnswer(304, $etag, '', $candidates);
        }
        $body = json_encode($this->view->response($country, $layers, $now), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new StatusAnswer(200, $etag, $body, $candidates);
    }
}
