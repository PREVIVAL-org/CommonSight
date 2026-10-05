<?php

declare(strict_types=1);

// Configuration of CommonSight. Every key with type, default and meaning: doc/CONFIGURATION.md; installation:
// doc/INSTALLATION.md. Copy to <APP>/config.php (chmod 600) and replace the placeholders: @APP@ = application directory
// outside the webroot, @WEBROOT@ = webroot of the (sub)domain. Absolute paths. Only paths is required; every other key
// shows its default and may be left out. A misspelled key is refused; check with `fetcher.php check`.

return [
    'paths' => [
        'data' => '@WEBROOT@/data/v1',
        'state' => '@APP@/state',
        'cache' => '@APP@/cache',
        'locks' => '@APP@/locks',
        'logs' => '@APP@/logs',
    ],
    // A layer counts as stale when one of its sources has not succeeded for this multiple of its interval; the status
    // endpoint then reloads its due sources (A-02).
    'staleFactor' => 2.5,
    // Minimum gap between two reloads by the status endpoint, in seconds.
    'triggerMinIntervalSec' => 60,
    // Vicinity ("Umkreis") in km around the selected country or region: section "Grenzgebiet" and points beyond the
    // border (ADR 0038). At most 300, the map area of the build.
    'vicinityKm' => 200,
    // Requests of the fetcher to the data providers (not the web server): time and size limits, parallel requests, and
    // your contact for the providers. Usually only 'contact' is set; the rest keeps its defaults.
    'http' => [
        'connectTimeoutSec' => 10,
        'requestTimeoutSec' => 18,
        'maxBytes' => 5_000_000,
        // Per source, over the limits the plugin declares, e.g. ['pegelonline' => 10_000_000] and ['hubeau' => 60].
        'maxBytesBySource' => [],
        'requestTimeoutBySource' => [],
        'perHostConcurrency' => 4,
        'totalConcurrency' => 12,
        // CA bundle for TLS verification if the system one is missing or outdated, e.g. '/etc/ssl/certs/ca-certificates.crt'.
        'caFile' => null,
        // Contact of the operator for the providers, sent in the User-Agent: "CommonSight/2.0 (+<contact>)" (F-10), e.g.
        // 'https://example.org/contact'. Set it: null sends the address of the CommonSight project (previval.org).
        'contact' => null,
    ],
    // Cron lanes: time budget per run and per reload by the status endpoint (null = never by the status endpoint), and
    // how often the cron line of the lane runs (everySec; a layer is stale only when a source missed its lane). One cron
    // line per lane (crontab.example). Defaults: fast 50/60 s every 60 s, heavy 240/120 s every 300 s, slow 600 s
    // without reload every 1800 s.
    'lanes' => [],
    // Settings of the layers, declared by each layer; `fetcher.php check` lists them with their defaults. E.g. the display
    // thresholds of the dose rate in µSv/h (B-13, B-14).
    'layers' => ['radiation' => ['settings' => ['warningUSvH' => 0.3, 'highUSvH' => 1.0]]],
    // Per source (plugin id): switch off, e.g. ['meteoalarm-ch' => ['enabled' => false, 'reason' => 'format change']]
    // (F-17); another lane ('lane' => 'slow'), a longer interval ('intervalSec' => 1800, never below the update rate of
    // the source), another rank within its layer ('order' => 5), the secrets a source needs, e.g. an API key
    // ('secrets' => ['apiKey' => '…']); without them it shows "not set up". All source ids and the effects of switching
    // one off: doc/CONFIGURATION.md, sources; `fetcher.php sources` lists the sources of the installation.
    'sources' => [],
    // Share of broken records in a response above which a source reports a format change (F-18).
    'drift' => ['maxRejectedShare' => 0.10],
    // Start page only for the members of a community (doc/INSTALLATION.md, Access control; doc/CONFIGURATION.md, auth).
    // Without it the start page is open to everyone. Data, tiles and status stay public in any case. To close it to all
    // but the logged-in members of a WoltLab Suite forum, remove the comment signs and fill in:
    // 'auth' => [
    //     'provider' => 'woltlab',
    //     'settings' => [
    //         // Name of the forum's session cookie (browser, developer tools, cookies of the forum: ..._user_session).
    //         'cookie' => 'wsc_xxxxxx_user_session',
    //         // Name of the community on the card for visitors who are not admitted.
    //         'community' => 'Example Forum',
    //         // Address of the forum ending with /, for login (back to CommonSight) and registration.
    //         'forumUrl' => 'https://forum.example.org/',
    //         // Access to the forum database: a user of its own that may only read the two tables (recommended):
    //         //   GRANT SELECT ON forum.wcf1_user_session TO 'cs_read'@'localhost';
    //         //   GRANT SELECT ON forum.wcf1_user TO 'cs_read'@'localhost';
    //         // 'instance' is the N of the table prefix wcfN_ (1 if left out). PHP needs pdo_mysql.
    //         'database' => [
    //             'host' => 'localhost',
    //             'port' => 3306,
    //             'name' => 'forum',
    //             'user' => 'cs_read',
    //             'password' => '...',
    //             'instance' => 1,
    //         ],
    //         // Instead of database: the forum's own configuration file (no setup, but with the forum's full rights).
    //         // 'forumConfig' => '/path/of/the/forum/config.inc.php',
    //     ],
    // ],
];
