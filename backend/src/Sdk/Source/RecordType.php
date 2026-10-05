<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

/** Checks that a source record has the expected type of its source before a mapper or a detail source reads it. */
final class RecordType
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function expect(object $record, string $class): object
    {
        if (!$record instanceof $class) {
            throw new \InvalidArgumentException(sprintf('Datensatz %s statt %s', $record::class, $class));
        }

        return $record;
    }
}
