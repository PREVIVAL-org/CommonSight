<?php

declare(strict_types=1);

namespace CommonSight\Model\Item;

/** Headline from a news feed with topic and feed. */
final readonly class NewsItem implements Item
{
    public function __construct(
        public ItemCommon $common,
        public CatalogTerm $category,
        /** the feed from the catalog newsFeeds; with news plugins its id is the plugin's id */
        public CatalogTerm $feed,
    ) {}

    public function common(): ItemCommon
    {
        return $this->common;
    }

    public function withCommon(ItemCommon $common): static
    {
        return new self($common, $this->category, $this->feed);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['kind' => 'news'] + $this->common->toArray() + [
            'category' => $this->category->value,
            'feed' => $this->feed->value,
        ];
    }
}
