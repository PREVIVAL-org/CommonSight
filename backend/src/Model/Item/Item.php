<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

/** Item of a layer; each kind is its own class with fixed fields (D-11, V14). */
interface Item extends \JsonSerializable
{
    public function common(): ItemCommon;

    public function withCommon(ItemCommon $common): static;

    /** @return array<string, mixed> */
    public function jsonSerialize(): array;
}
