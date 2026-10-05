<?php

declare(strict_types=1);

namespace CommonSight\Plugin\NewsLayer;

use CommonSight\Model\Decoded;
use CommonSight\Model\Msg;
use CommonSight\Model\Value\LayerId;
use CommonSight\Model\Value\Scope;
use CommonSight\Sdk\Layer\EmptyStatsBuilder;
use CommonSight\Sdk\Layer\ItemDeduplicator;
use CommonSight\Sdk\Layer\ItemLimit;
use CommonSight\Sdk\Layer\LayerDefinition;
use CommonSight\Sdk\Layer\LayerPlugin;
use CommonSight\Sdk\Layer\LayerSources;
use CommonSight\Sdk\Layer\NewestFirstSorter;
use CommonSight\Sdk\Layer\RecentItemFilter;
use CommonSight\Sdk\Layer\UrlDeduplicator;

/** Current headlines of public broadcasters, filtered by topic (Q-NE-*). Display name and link per scope from data/names.json. */
final class NewsLayer implements LayerPlugin
{
    /** Headlines of the last 72 hours, at most 45 (Q-NE-*). */
    private const WINDOW_SEC = 72 * 3600;
    private const LIMIT = 45;

    public function __construct(
        private readonly Decoded $names,
    ) {}

    public function definition(Scope $scope, LayerSources $sources): LayerDefinition
    {
        [$name, $url] = $sources->nameAndLink($scope, $this->names);
        $steps = [new RecentItemFilter(self::WINDOW_SEC), new UrlDeduplicator(), new ItemDeduplicator(), new NewestFirstSorter(), new ItemLimit(self::LIMIT)];

        return new LayerDefinition(LayerId::from('news'), $scope, $name, $url, new Msg('layer.news.note'), $steps, new EmptyStatsBuilder());
    }
}
