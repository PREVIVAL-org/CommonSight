<?php

declare(strict_types=1);

namespace CommonSight\Sdk\News;

use CommonSight\Model\Item\CatalogTerm;
use CommonSight\Model\Item\Item;
use CommonSight\Model\Item\ItemCommon;
use CommonSight\Model\Item\NewsItem;
use CommonSight\Sdk\Source\ItemMapper;
use CommonSight\Sdk\Source\ParseContext;
use CommonSight\Sdk\Source\RecordType;
use CommonSight\Sdk\Text\SafeUrl;

/** Maps a feed entry to a news item with topic; without a valid link or without a topic it is dropped (Q-NE-03 to -05). */
final class NewsMapper implements ItemMapper
{
    public function __construct(
        private readonly NewsTopicClassifier $classifier,
        private readonly SafeUrl $url,
        private readonly NewsFeed $feed,
    ) {}

    public function map(object $record, ParseContext $context): ?Item
    {
        $record = RecordType::expect($record, FeedEntry::class);
        $url = $this->url->valid($record->link);
        $category = $this->classifier->classify($record->title . ' ' . $record->description);
        if ($url === null || $category === null || $record->title === '') {
            return null;
        }

        return new NewsItem(
            new ItemCommon(
                id: $this->feed->feed . ':' . ($record->id !== '' ? $record->id : $url),
                title: $record->title,
                url: $url,
                source: $this->feed->name,
                time: $record->published,
                lang: 'de',
            ),
            $category,
            new CatalogTerm($this->feed->feed),
        );
    }
}
