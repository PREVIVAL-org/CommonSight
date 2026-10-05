<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Source;

/** What is expected of a source: whether empty is an error, whether discarded records lead to partial, minimum size (F-18, Q-02). */
final readonly class SourceExpectations
{
    public function __construct(
        public bool $emptyIsFailure,
        public bool $rejectedMeansPartial,
        public ?int $minimumValid = null,
    ) {}

    /** Measurement and model sources: failed without items (Q-02). */
    public static function measurements(?int $minimumValid = null, bool $rejectedMeansPartial = false): self
    {
        return new self(true, $rejectedMeansPartial, $minimumValid);
    }

    /** Event sources: empty is valid (no messages). */
    public static function events(bool $rejectedMeansPartial = false): self
    {
        return new self(false, $rejectedMeansPartial);
    }
}
