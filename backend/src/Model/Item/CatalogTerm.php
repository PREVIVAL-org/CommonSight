<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

/**
 * A term from a catalog (quantity, category, news feed), contributed by the core or by a plugin (V14). Checks only its
 * form; that the term is in its catalog is checked against the schema generated from the catalogs (V11).
 */
final readonly class CatalogTerm implements \JsonSerializable
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z][A-Za-z0-9-]{0,40}$/', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid catalog term: ' . $value);
        }
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
