<?php

declare(strict_types=1);

namespace CommonSight\Domain\Snapshot;

use CommonSight\Model\Msg;
use CommonSight\Model\Snapshot;

/** Result of the assembly: a snapshot or the reason why the layer failed. */
final readonly class Assembly
{
    private function __construct(public ?Snapshot $snapshot, public ?Msg $failure) {}

    public static function assembled(Snapshot $snapshot): self
    {
        return new self($snapshot, null);
    }

    public static function failed(Msg $failure): self
    {
        return new self(null, $failure);
    }
}
