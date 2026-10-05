<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

use CommonSight\Model\Msg;
use CommonSight\Model\Value\UtcInstant;

/** Official warning with warning kind, severity, area, validity and text in sections. */
final readonly class WarningItem implements Item
{
    /** @param list<WarningSection> $sections */
    public function __construct(
        public ItemCommon $common,
        public Msg $hazard,
        public Severity $severity,
        public string $area,
        public ?UtcInstant $onset,
        public ?UtcInstant $expires,
        public array $sections,
        public CatalogTerm $category,
        public ?Awareness $awareness = null,
    ) {}

    public function common(): ItemCommon
    {
        return $this->common;
    }

    public function withCommon(ItemCommon $common): static
    {
        return new self($common, $this->hazard, $this->severity, $this->area, $this->onset, $this->expires, $this->sections, $this->category, $this->awareness);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = ['kind' => 'warning'] + $this->common->toArray() + [
            'hazard' => $this->hazard,
            'severity' => $this->severity->value,
        ];
        if ($this->awareness !== null) {
            $data['awareness'] = $this->awareness->value;
        }
        $data['area'] = $this->area;
        if ($this->onset !== null) {
            $data['onset'] = $this->onset;
        }
        if ($this->expires !== null) {
            $data['expires'] = $this->expires;
        }
        $data['sections'] = $this->sections;
        $data['category'] = $this->category->value;

        return $data;
    }
}
