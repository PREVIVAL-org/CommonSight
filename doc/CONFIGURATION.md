# Configuration

Reference of `config.php`, the one configuration file of an installation. The template with all keys is `install/config.example.php` (in the package: `config.example.php`); installation and update steps are in `INSTALLATION.md`.

## Where and how

- **Location:** `<APP>/config.php`, next to `releases/` (two levels above the code of a release). The environment variable `CS_CONFIG` or `fetcher.php … --config=<path>` name another file.
- **Format:** a PHP file that returns an array. It can hold secrets (API keys, database access): readable for the account only (`chmod 600`).
- **Read by** the fetcher (cron lanes, `fetcher.php`), the status endpoint (`api/status.php`) and the start page (`gate.php`), at every run or request: a change applies at once, no restart.
- **Checked strictly:** a misspelled key, a value of the wrong type or out of range is refused with a message that names the key; settings of former releases are refused with a hint to their replacement. Run `fetcher.php check` after every change; it also lists the sources per lane, the settings of every layer with their defaults and the access to the start page.
- **Everything has a default** except `paths`; a key that is left out takes its default.

## `paths` (required)

Absolute paths; each directory must exist and be writable for the account.

| Key | Meaning | Example |
|---|---|---|
| `data` | snapshots, written by the fetcher, read by browsers | `<WEBROOT>/data/v1` |
| `state` | status of the layers, health of the sources, run markers | `<APP>/state` |
| `cache` | latest outcome per source, stores of the plugins | `<APP>/cache` |
| `locks` | lock files of lanes, layers and sources | `<APP>/locks` |
| `logs` | `fetcher.log` (JSON lines); cron writes `cron.log` and `tiles.log` there too | `<APP>/logs` |

## Freshness and reload

| Key | Type, default | Meaning |
|---|---|---|
| `staleFactor` | number ≥ 1, `2.5` | A layer counts as stale when one of its sources has not succeeded for this multiple of its interval (A-02); the browser shows it, and a visit then lets the status endpoint reload its due sources (fallback). |
| `triggerMinIntervalSec` | integer ≥ 1, `60` | Minimum gap in seconds between two reloads of the same layer by the status endpoint. |

## `vicinityKm` – the vicinity ("Umkreis")

| Key | Type, default | Meaning |
|---|---|---|
| `vicinityKm` | number > 0, `200` | Radius in km around the selected country or region within which items count as its vicinity (ADR 0038): the section "Grenzgebiet" of the lists and the points beyond the border on the map, with a region also the other regions of its country. At most the map area of the build (`mapKm`, 300 km, see below); the fetcher refuses more. A change applies to every layer at its next assembly, and the browser shows the new value with the next status. |

## `http` – requests to the providers

| Key | Type, default | Meaning |
|---|---|---|
| `connectTimeoutSec` | integer ≥ 1, `10` | time to connect |
| `requestTimeoutSec` | integer ≥ 1, `18` | time per request (a plugin may declare more for its source) |
| `maxBytes` | integer ≥ 1, `5000000` | size per response (a plugin may declare more) |
| `maxBytesBySource` | `[source id => bytes]`, `[]` | size for single sources, over what the plugin declares, e.g. `['pegelonline' => 10_000_000]` |
| `requestTimeoutBySource` | `[source id => seconds]`, `[]` | time for single sources, e.g. `['hubeau' => 60]` |
| `perHostConcurrency` | integer ≥ 1, `4` | parallel requests to one host |
| `totalConcurrency` | integer ≥ 1, `12` | parallel requests in all |
| `caFile` | path or `null` | CA bundle for TLS if the system one is missing or outdated, e.g. `/etc/ssl/certs/ca-certificates.crt` |
| `contact` | URL or e-mail, `null` | contact of the operator for the providers, sent as `User-Agent: CommonSight/2.0 (+<contact>)` (F-10). **Set it**: `null` sends the address of the CommonSight project. |

Source ids in `maxBytesBySource` and `requestTimeoutBySource` must exist (`fetcher.php sources` lists them).

### Example: `http`

Your contact for the providers, larger responses from PEGELONLINE and more time for Hub'Eau. Every key left out keeps its default:

```php
'http' => [
    'contact' => 'https://example.org/contact',
    'maxBytesBySource' => [
        'pegelonline' => 10_000_000,
    ],
    'requestTimeoutBySource' => [
        'hubeau' => 60,
    ],
],
```

## `lanes` – cron lanes

Each source runs in a lane (its plugin names it, `sources` can change it); each lane has its own cron line (`crontab.example`). The section changes the defaults or adds lanes:

| Lane | `budgetSec` | `fallbackSec` | `everySec` |
|---|---|---|---|
| `fast` (warnings) | 50 | 60 | 60 |
| `heavy` (default) | 240 | 120 | 300 |
| `slow` | 600 | `null` | 1800 |

- `budgetSec`: time of a cron run of the lane; sources that would not fit wait for the next run.
- `fallbackSec`: time of a reload by the status endpoint; `null` = this lane is never reloaded by a visit.
- `everySec`: how often the cron line of the lane runs; a layer is stale only when a source missed its lane.

A changed lane keeps the defaults of the keys left out. A new lane needs `budgetSec`; without `fallbackSec` it is never reloaded by a visit, and without `everySec` its cadence is unknown to the staleness check.

### Example: `lanes`

Less time for the heavy lane, and a new lane `night` that runs once a day for the water gauges beyond the border:

```php
'lanes' => [
    'heavy' => ['budgetSec' => 200],
    'night' => ['budgetSec' => 900, 'everySec' => 86400],
],
'sources' => [
    'chmi' => ['lane' => 'night', 'intervalSec' => 86400],
    'imgw' => ['lane' => 'night', 'intervalSec' => 86400],
],
```

The new lane needs its own cron line, like those in `crontab.example`:

```text
30 2 * * *    @PHP@ -d memory_limit=256M @APP@/current/bin/fetcher.php run --lane night >> @APP@/logs/cron.log 2>&1
```

Without the cron line its sources never run, and their layer turns stale.

## `layers` – settings of the layers

`'layers' => ['<layer id>' => ['settings' => [...]]]`; only settings the layer declares, as numbers in their range. `fetcher.php check` lists every setting with its default. Today:

| Layer | Setting | Default | Meaning |
|---|---|---|---|
| `radiation` | `warningUSvH` | `0.3` | dose rate in µSv/h from which a probe shows orange (B-13) |
| `radiation` | `highUSvH` | `1.0` | dose rate in µSv/h from which a probe shows red (B-14); above `warningUSvH` |

### Example: `layers`

Probes show orange from 0.25 µSv/h and red from 0.8 µSv/h. A setting that is left out keeps its default:

```php
'layers' => [
    'radiation' => [
        'settings' => [
            'warningUSvH' => 0.25,
            'highUSvH' => 0.8,
        ],
    ],
],
```

`fetcher.php check` then lists:

```text
Layer radiation: warningUSvH 0.25 (default 0.3), highUSvH 0.8 (default 1.0) (layers.radiation.settings)
```

The new thresholds apply when the layer is assembled the next time; no deploy is needed. A `highUSvH` that is not above `warningUSvH`, a setting the layer does not declare, or a text instead of a number fails the check. To go back to the defaults, delete the lines.

## `sources` – settings of single sources

A source is one data provider of a layer (e.g. `dwd-warnings` feeds the layer `warnings`). By default every source runs. You only add an entry for a source you want to change, under its **source id**:

```php
'sources' => [
    '<source id>' => [ /* keys below */ ],
],
```

| Key | Type, default | Meaning |
|---|---|---|
| `enabled` | `true`/`false`, `true` | `false` switches the source off (see below) |
| `reason` | text, none | why it is off; shown by `fetcher.php check`. Only a note for you. |
| `lane` | lane name, lane of the plugin | runs the source in another lane (see `lanes`), e.g. `'slow'` |
| `intervalSec` | integer ≥ 1, interval of the plugin | fetches the source less often. Never below the update rate of the source or the minimum interval of the provider's terms of use; the fetcher refuses a shorter one. |
| `order` | integer, rank of the plugin | rank within the layer when two sources report the same thing (smaller first) |
| `secrets` | `[name => text]`, none | secrets a source needs, e.g. `['apiKey' => '…']`; a source that needs one shows "not set up" without it. No source of this release needs one. |

An unknown source id or key is refused, so a typo cannot silently do nothing.

### Switching a source off

Use this when a provider is down for a long time, changed its format (F-17) or should not be shown in your installation.

1. Find the id in the list below or with `fetcher.php sources`.
2. Open `config.php`: production `<APP>/config.php`, local development `run/commonsight/config.php`.
3. Add the source to `sources`:

   ```php
   'sources' => [
       'meteoalarm-ch' => ['enabled' => false, 'reason' => 'format change, ticket 42'],
   ],
   ```

4. Run `fetcher.php check`. It must end without errors and lists the source as `Source meteoalarm-ch switched off: format change, ticket 42 (sources.meteoalarm-ch.enabled)`.

Several sources, e.g. all news feeds:

```php
'sources' => [
    'news-tagesschau' => ['enabled' => false],
    'news-orf' => ['enabled' => false],
    'news-srf' => ['enabled' => false],
],
```

**What happens:**

- No deploy or restart is needed. The change applies at the next run of the source's lane (at most 60 s for `fast`, 5 min for `heavy`).
- The source is no longer fetched. Its items leave the map and the lists when its layer is assembled the next time.
- The layer shows the notice "*{source}* ist vorübergehend abgeschaltet." and counts as **teilweise** (partial). The other sources of the layer run as before.
- If **all** sources of a layer are off, the layer shows **nicht eingerichtet** (not set up), e.g. the news panel when all three feeds are off.
- The source no longer counts for the staleness of its layer, and its name and credits disappear from the layer.

**Switching it back on:** remove its entry from `sources` (or set `'enabled' => true`) and run `fetcher.php check`. The source runs at the next run of its lane; its items come back with the next assembly of the layer.

A whole layer cannot be switched off in `config.php`; switch off all its sources instead.

### Source ids

Lane `fast` runs every 60 s, `heavy` every 5 min. "Border" means the areas beyond DACH within the map area.

| Source id | Provider | Layer | Area | Lane |
|---|---|---|---|---|
| `at-alert` | AT-Alert | `warnings` | AT | fast |
| `geosphere-warnings` | GeoSphere Austria | `warnings` | AT | fast |
| `dwd-warnings` | DWD | `warnings` | DE | fast |
| `bbk-mowas` | BBK/NINA | `warnings` | DE | fast |
| `alertswiss` | Alertswiss (BABS) | `warnings` | CH | fast |
| `meteoalarm-ch` | MeteoAlarm | `warnings` | CH | fast |
| `open-meteo-weather` | Open-Meteo | `weather` | DE, AT, CH, border | heavy |
| `open-meteo-air` | CAMS · Open-Meteo | `air` | DE, AT, CH, border | heavy |
| `open-meteo-pollen` | CAMS · Open-Meteo | `pollen` | DE, AT, CH, border | heavy |
| `bfs-odl` | BfS ODL | `radiation` | DE | heavy |
| `umweltbundesamt` | Strahlenfrühwarnsystem | `radiation` | AT | heavy |
| `eurdep` | EURDEP | `radiation` | CH | heavy |
| `paa` | PAA | `radiation` | border | heavy |
| `appa-bz` | Umweltagentur Südtirol | `radiation` | border | heavy |
| `pegelonline` | PEGELONLINE | `water` | DE | heavy |
| `ehyd` | eHYD | `water` | AT | heavy |
| `bafu-lindas` | BAFU/LINDAS | `water` | CH | heavy |
| `hubeau` | Hub'Eau | `water` | border | heavy |
| `rijkswaterstaat` | Rijkswaterstaat | `water` | border | heavy |
| `chmi` | ČHMÚ | `water` | border | heavy |
| `imgw` | IMGW-PIB | `water` | border | heavy |
| `usgs` | USGS | `nature` | DE, AT, CH, border | heavy |
| `autobahn` | Autobahn GmbH | `traffic` | DE | heavy |
| `oeamtc` | ÖAMTC | `traffic` | AT | heavy |
| `noaa-kp` | NOAA Kp-Index | `space` | global | heavy |
| `noaa-scales` | NOAA-Skalen | `space` | global | heavy |
| `news-tagesschau` | tagesschau.de | `news` | global | heavy |
| `news-orf` | ORF.at | `news` | global | heavy |
| `news-srf` | SRF News | `news` | global | heavy |

The list of a running installation, with lane, interval and health of each source: `fetcher.php sources`.

### Other settings of a source

- **Fetch less often**, e.g. to spare a slow provider: `'hubeau' => ['intervalSec' => 1800]`. A layer counts as stale after `staleFactor` × this interval.
- **Move to another lane**, e.g. so a slow provider does not hold up the others: `'imgw' => ['lane' => 'slow']`. The lane must exist (`lanes`) and have its cron line.
- **Change the rank** when two sources report the same thing (e.g. a station near the border): `'eurdep' => ['order' => 5]`; the smaller number wins.
- Keys can be combined: `'chmi' => ['lane' => 'slow', 'intervalSec' => 3600]`.
- Larger responses or more time for one source are set in `http` (`maxBytesBySource`, `requestTimeoutBySource`), not here.

### Example: `sources`

A `sources` section that switches off MeteoAlarm and all news feeds and fetches Hub'Eau less often, in the slow lane:

```php
'sources' => [
    // Warnings for CH: only Alertswiss; the layer shows "MeteoAlarm ist vorübergehend abgeschaltet." and counts as partial.
    'meteoalarm-ch' => ['enabled' => false, 'reason' => 'format change'],
    // No news: the news panel shows "nicht eingerichtet".
    'news-tagesschau' => ['enabled' => false],
    'news-orf' => ['enabled' => false],
    'news-srf' => ['enabled' => false],
    // French gauges every 30 minutes.
    'hubeau' => ['lane' => 'slow', 'intervalSec' => 1800],
],
```

`fetcher.php check` then lists:

```text
Source meteoalarm-ch switched off: format change (sources.meteoalarm-ch.enabled)
Source news-tagesschau switched off (sources.news-tagesschau.enabled)
Source news-orf switched off (sources.news-orf.enabled)
Source news-srf switched off (sources.news-srf.enabled)
```

To switch them on again, delete their lines.

## `drift` – format changes of the providers

| Key | Type, default | Meaning |
|---|---|---|
| `maxRejectedShare` | number > 0, `0.10` | share of broken records in a response above which the source reports a format change (F-18); the layer shows it, the log names the source |

## `auth` – access to the start page

Without `auth` the start page is open to everyone. With it, only visitors the provider admits see the map; the others see the members card (`INSTALLATION.md`, Access control). Only the start page is protected; data, tiles and status stay public.

| Key | Meaning |
|---|---|
| `provider` | id of a package in `plugins/auth/`, today `woltlab` |
| `settings` | settings of the provider, checked by it |

Settings of `woltlab`:

| Key | Meaning |
|---|---|
| `cookie` | name of the forum's session cookie, e.g. `wsc_1d3758_user_session` |
| `community` | name shown on the members card, e.g. `PREVIVAL.org` |
| `forumUrl` | address of the forum ending with `/`, for login (back to CommonSight) and registration |
| `database` | own access data: `host`, `port`, `name`, `user`, `password`, optionally `instance` (the N of the table prefix `wcfN_`); best a user that may only read `wcfN_user_session` and `wcfN_user` |
| `forumConfig` | instead of `database`: path of the forum's `config.inc.php` |

### Example: `auth`

The start page only for the logged-in members of a WoltLab forum, with a database user of its own (recommended):

```php
'auth' => [
    'provider' => 'woltlab',
    'settings' => [
        'cookie' => 'wsc_1d3758_user_session',
        'community' => 'Example Forum',
        'forumUrl' => 'https://forum.example.org/',
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'forum',
            'user' => 'cs_read',
            'password' => '…',
            'instance' => 1,
        ],
    ],
],
```

The database user may only read the two tables (in the forum's database, as its administrator):

```sql
CREATE USER 'cs_read'@'localhost' IDENTIFIED BY '…';
GRANT SELECT ON forum.wcf1_user_session TO 'cs_read'@'localhost';
GRANT SELECT ON forum.wcf1_user TO 'cs_read'@'localhost';
```

Without a user of its own, read the forum's configuration instead of `database` (no setup, but with the forum's full rights):

```php
'auth' => [
    'provider' => 'woltlab',
    'settings' => [
        'cookie' => 'wsc_1d3758_user_session',
        'community' => 'Example Forum',
        'forumUrl' => 'https://forum.example.org/',
        'forumConfig' => '/var/www/forum/config.inc.php',
    ],
],
```

`fetcher.php check` reports the access to the start page. To open the start page to everyone again, delete `auth`.

## Refused keys of former releases

| Key | Replaced by |
|---|---|
| `memberGate` | `auth` with the provider `woltlab` |
| `budgets` | `lanes` |
| `assessment` | `layers.radiation.settings` |
| `layers.<id>.intervalSec` | `sources.<id>.intervalSec` |

## Not in `config.php`

- **Colours and logo** of the start page: `<WEBROOT>/custom/` (`theme.css`, `logo.svg`, `favicon.svg`, `branding.json`; `INSTALLATION.md`, Colours and logo).
- **Map area of the border zone** (ADR 0038): `mapKm` (300) in `contract/data/border-zone.json`, set at build time: how far the map reaches beyond DACH. The outline of the border zone and the mask (`tools/regions/build.mjs`), the clipping of the tile archive, the station lists (`tools/stations/build.mjs`) and the border places follow from it. A change means: rebuild the map assets, stations and border places, build a new tile archive, then a release. `vicinityKm` cannot reach further.
- **Tiles:** the tile job takes its directories from the environment (`CS_APP`, `CS_WEB`, `PHP`, optionally `PMTILES`, `MAXZOOM`), set in the cron line.
