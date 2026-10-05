<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NewsTagesschau;

use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\News\NewsFeed;
use CommonSight\Sdk\News\NewsFeedParser;
use CommonSight\Sdk\News\NewsFeedRequest;
use CommonSight\Sdk\News\NewsMapper;
use CommonSight\Sdk\News\NewsTopicClassifier;
use CommonSight\Sdk\Plugin\Attribution;
use CommonSight\Sdk\Plugin\PluginEnvironment;
use CommonSight\Sdk\Plugin\SourceDescription;
use CommonSight\Sdk\Plugin\SourcePlugin;
use CommonSight\Sdk\Plugin\SourcePluginFactory;
use CommonSight\Sdk\Plugin\SourceSchedule;
use CommonSight\Sdk\Source\RequestParseMap;
use CommonSight\Sdk\Source\SourceExpectations;
use CommonSight\Sdk\Source\SourceParts;
use CommonSight\Sdk\Source\StandardSourcePlugin;
use CommonSight\Sdk\Text\SafeUrl;
use CommonSight\Sdk\Text\TextCleaner;
use CommonSight\Sdk\Text\UtcTimeParser;

/** Headlines from the RSS feed with all news of tagesschau.de (ARD), kept by topic (Q-NE-*). */
final class NewsTagesschauFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: self::feed()->sourceId,
            name: self::feed()->name,
            attribution: new Attribution('Nachrichten: tagesschau.de', 'https://www.tagesschau.de/infoservices/rssfeeds'),
            layer: 'news',
            scopes: [Scope::Global],
            schedule: new SourceSchedule(60),
            expectations: SourceExpectations::events(),
            order: 10,
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new NewsFeedRequest(self::feed()),
            new NewsFeedParser($environment->xml, new UtcTimeParser(), new TextCleaner()),
            new NewsMapper(new NewsTopicClassifier(), new SafeUrl(), self::feed()),
        )));
    }

    private static function feed(): NewsFeed
    {
        return new NewsFeed('news-tagesschau', 'tagesschau', 'tagesschau.de', 'https://www.tagesschau.de/infoservices/alle-meldungen-100~rss2.xml');
    }
}
