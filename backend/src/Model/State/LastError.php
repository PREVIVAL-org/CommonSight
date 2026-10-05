<?php

declare(strict_types=1);

namespace CommonSight\Model\State;

use CommonSight\Model\Msg;
use CommonSight\Model\Value\UtcInstant;

/** Time and cause of the last failed update attempt (D-05). */
final readonly class LastError implements \JsonSerializable
{
    public function __construct(public UtcInstant $at, public Msg $message) {}

    /** @return array{at: UtcInstant, message: Msg} */
    public function jsonSerialize(): array
    {
        return ['at' => $this->at, 'message' => $this->message];
    }
}
