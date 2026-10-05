<?php

declare(strict_types=1);

namespace CommonSight\Sdk\Plugin;

use CommonSight\Port\HttpClient;
use CommonSight\Port\PluginData;
use CommonSight\Port\PluginStore;
use CommonSight\Port\XmlEntryReader;

/**
 * The services the core lends to one plugin, each scoped to that plugin; handed over once when the plugin is created.
 * Inside the plugin its parts receive only what they need through their constructors.
 */
final readonly class PluginEnvironment
{
    public function __construct(
        /** HTTPS only, limits of this source, run budget, user agent, retry; stamps the source id itself */
        public HttpClient $http,
        public PluginStore $store,
        /** only the secrets the source declared */
        public Secrets $secrets,
        public XmlEntryReader $xml,
        public PluginData $data,
    ) {}
}
