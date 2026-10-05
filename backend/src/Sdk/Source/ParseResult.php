<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

use CommonSight\Model\Value\UtcInstant;

/** Source records of a response with statistics and the source's information about the total size. */
final readonly class ParseResult
{
    /**
     * @param list<object> $records
     * @param int|null $totalAvailable total count according to the source, if it delivers in pages
     */
    public function __construct(
        public array $records,
        public ParseStatistics $statistics,
        public ?UtcInstant $sourceUpdatedAt = null,
        public ?int $totalAvailable = null,
    ) {}

    /** @param non-empty-list<self> $results */
    public static function merge(array $results): self
    {
        $records = [];
        foreach ($results as $result) {
            array_push($records, ...$result->records);
        }

        return new self(
            $records,
            ParseStatistics::sum(array_map(static fn(self $r): ParseStatistics => $r->statistics, $results)),
            UtcInstant::latest(array_map(static fn(self $r): ?UtcInstant => $r->sourceUpdatedAt, $results)),
            $results[0]->totalAvailable,
        );
    }
}
