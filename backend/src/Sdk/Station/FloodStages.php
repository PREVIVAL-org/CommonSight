<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Station;

/**
 * Flood stages of a gauge as its source defines them, and the value compared with them (ADR 0038); the plugin of the
 * source classifies them with its own FloodStageAssessor. Sources without stages deliver none().
 */
final readonly class FloodStages
{
    /**
     * @param list<float|null> $thresholds in rising order; a stage the source does not name is null
     * @param string $label name of the stages in the source value, e.g. "SPA"
     */
    public function __construct(
        public array $thresholds,
        public ?float $compared,
        public string $unit,
        public string $label,
        public string $source,
    ) {}

    public static function none(string $source): self
    {
        return new self([], null, '', '', $source);
    }

    /** The stages as delivered, e.g. "SPA 165 / 200 / 220 cm"; "-" without stages. */
    public function describe(): string
    {
        if ($this->thresholds === []) {
            return '-';
        }
        $values = implode(' / ', array_map(static fn(?float $v): string => $v === null ? '-' : (string) $v, $this->thresholds));

        return $this->label . ' ' . $values . ' ' . $this->unit;
    }
}
