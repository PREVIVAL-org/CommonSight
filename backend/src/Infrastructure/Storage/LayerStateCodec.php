<?php

declare(strict_types=1);

namespace CommonSight\Infrastructure\Storage;

use CommonSight\Model\Decoded;
use CommonSight\Model\FeedStatus;
use CommonSight\Model\Msg;
use CommonSight\Model\State\LastError;
use CommonSight\Model\State\LayerState;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Model\Value\UtcInstant;

/** Converts the content of a state file into a LayerState and back (Architecture 4.7). */
final class LayerStateCodec
{
    /** @throws \InvalidArgumentException|\ValueError for incomplete or invalid content */
    public function decode(Decoded $data): LayerState
    {
        $status = $data->get('status')->string();
        $error = $data->get('lastError');

        return new LayerState(
            LayerId::from($data->get('layer')->string() ?? ''),
            Scope::from($data->get('scope')->string() ?? ''),
            $this->nonEmpty($data->get('version')),
            $this->nonEmpty($data->get('file')),
            $status === null ? null : FeedStatus::from($status),
            $this->instant($data->get('generatedAt')),
            $this->instant($data->get('checkedAt')),
            $this->instant($data->get('sourceUpdatedAt')),
            $data->get('itemCount')->int() ?? 0,
            array_map($this->msg(...), $data->get('issues')->list()),
            $error->isArray() ? new LastError(UtcInstant::fromIso($error->get('at')->string() ?? ''), $this->msg($error->get('message'))) : null,
            $data->get('consecutiveFailures')->int() ?? 0,
            $this->instant($data->get('backoffUntil')),
            $data->get('intervalSec')->int(),
            $this->instant($data->get('staleAfter')),
        );
    }

    public function encode(LayerState $state): string
    {
        return json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function msg(Decoded $data): Msg
    {
        $params = [];
        foreach ($data->get('params')->entries() as $name => $value) {
            $raw = $value->raw();
            $params[$name] = is_int($raw) || is_float($raw) || is_string($raw) ? $raw : '';
        }

        return new Msg($data->get('key')->string() ?? throw new \InvalidArgumentException('Message without key'), $params);
    }

    private function instant(Decoded $value): ?UtcInstant
    {
        $iso = $value->string();

        return $iso === null ? null : UtcInstant::fromIso($iso);
    }

    private function nonEmpty(Decoded $value): ?string
    {
        $string = $value->string();

        return $string === null || $string === '' ? null : $string;
    }
}
