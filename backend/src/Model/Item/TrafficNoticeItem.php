<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

use CommonSight\Model\Value\UtcInstant;

/** Traffic notice with route, kind (the source's words and a category), start and description. */
final readonly class TrafficNoticeItem implements Item
{
    public function __construct(
        public ItemCommon $common,
        public ?string $road = null,
        public ?string $noticeType = null,
        public ?UtcInstant $start = null,
        public ?string $description = null,
        public ?TrafficCategory $category = null,
    ) {}

    public function common(): ItemCommon
    {
        return $this->common;
    }

    public function withCommon(ItemCommon $common): static
    {
        return new self($common, $this->road, $this->noticeType, $this->start, $this->description, $this->category);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $optional = [
            'road' => $this->road,
            'noticeType' => $this->noticeType,
            'category' => $this->category?->value,
            'start' => $this->start,
            'description' => $this->description,
        ];

        return ['kind' => 'trafficNotice'] + $this->common->toArray()
            + array_filter($optional, static fn(mixed $value): bool => $value !== null && $value !== '');
    }
}
