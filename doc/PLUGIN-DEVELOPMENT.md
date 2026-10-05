# Plugin development

How to add a data source or a layer to CommonSight. Everything here is a plugin: a self-contained package in `plugins/<group>/<id>/`. Adding a source or a layer means adding its package and nothing else; no file outside `plugins/` changes. The core discovers the packages at build time, checks them, runs the sources, isolates their failures, assembles the layers and delivers the data.

The examples come from the two packages that were written to prove exactly that: the source `plugins/providers/open-meteo-pollen` and the layer `plugins/layers/pollen`. Read them alongside this guide.

Background: `internal/SOURCE-INTEGRATION.md` (sources as plugins), `internal/LAYER-PLUGINS.md` (layers as plugins), `doc/ARCHITECTURE.md`. Setting up the environment and running the checks: `doc/DEVELOPMENT.md`.

## 1. Two kinds of plugins

| | Source | Layer |
|---|---|---|
| Does | calls one provider's API and maps its data into the internal data model (items) | defines what happens with the items of its sources and how they look in the frontend |
| Belongs to | exactly one layer, named in its description | has any number of sources (none is allowed: it shows "setup") |
| Entry point | `SourcePluginFactory` | `LayerPluginFactory` |
| Code | backend only (PHP) | backend (PHP) and frontend (TypeScript) |
| Id | the provider, e.g. `pegelonline`, `open-meteo-pollen` | a domain word, e.g. `water`, `pollen` |

Ids are unique across both kinds and equal the folder name. The kind of a package follows from what its `plugin.php` returns, and its group folder must match it:

| Group | Holds |
|---|---|
| `plugins/layers/` | the layers |
| `plugins/news/` | the sources of news feeds (`news-orf`, `news-srf`, `news-tagesschau`) |
| `plugins/providers/` | all other sources: the data providers (`pegelonline`, `dwd-warnings`, `open-meteo-pollen`, …) |
| `plugins/auth/` | the authentication providers of the start page (`woltlab`); see section 8 |

The build refuses a package in the wrong group, in an unknown group or directly in `plugins/`.

A source never knows its layer's code and a layer never knows a source by id: the layer gets the descriptions of whatever sources name it. So a second pollen source can be added later without touching the layer.

## 2. Package layout

```text
plugins/
├─ providers/<source id>/          (news feeds: news/<source id>/)
│  ├─ plugin.php                  manifest: returns the factory
│  ├─ backend/                    PHP, namespace CommonSight\Plugin\<Name>\
│  │  └─ Record/                  the records of the API (plain readonly classes)
│  ├─ messages/de.json            texts in the namespace source.<id>. (if any)
│  ├─ catalog.json                catalog terms the source contributes (if any)
│  ├─ data/                       master data of the source, e.g. stations.json (if any)
│  ├─ tests/
│  │  ├─ responses/<scope>/       recordings of the real API (index.json + bodies)
│  │  ├─ cases/                   hand-made edge cases (if any)
│  │  └─ *Test.php                namespace CommonSight\Plugin\<Name>\Tests
│  └─ PROFILE.md                  provider, endpoint, license, attribution, terms, update rate, quirks
└─ layers/<layer id>/
   ├─ plugin.php
   ├─ backend/                    namespace CommonSight\Plugin\<Name>Layer\
   ├─ frontend/
   │  ├─ map.ts                   renderer and draw rank (needed when the layer is on the map)
   │  ├─ ui.ts                    legend, tile, panel, map notice (optional)
   │  └─ *.test.ts
   ├─ messages/de.json            texts in the namespace layer.<id>.
   ├─ data/names.json             display name and link per scope
   ├─ schema/stats.schema.json    the layer's key figures (if any)
   ├─ tests/*LayerTest.php        the layer on the recordings of its sources
   └─ LAYER.md                    what the layer shows, sources, registry entry, settings, frontend
```

The backend classes are found by Composer's classmap over `plugins/` (tests excluded); no autoload entry is needed. In a release the packages keep their place under `plugins/<group>/<id>/`, with only `backend/` and `data/`.

## 3. Writing a source

### 3.1 Manifest and factory

`plugin.php` returns a new factory. The factory has a constructor without parameters: the core creates it from the generated registry.

```php
<?php

declare(strict_types=1);

return new CommonSight\Plugin\OpenMeteoPollen\OpenMeteoPollenFactory();
```

`describe()` tells the core everything about the source without any service, so the build can check it. `create()` gets the environment and builds the plugin. Most sources have the common shape *requests → parser → mapper* and use `StandardSourcePlugin` with `RequestParseMap`:

```php
final class OpenMeteoPollenFactory implements SourcePluginFactory
{
    public function describe(): SourceDescription
    {
        return new SourceDescription(
            id: PollenRequest::SOURCE_ID,                // 'open-meteo-pollen', equal to the folder name
            name: 'CAMS · Open-Meteo',                   // display name
            attribution: new Attribution(
                'Pollenflug: Open-Meteo.com (CC BY 4.0) auf Basis von CAMS (Copernicus Atmosphere Monitoring Service)',
                'https://open-meteo.com/en/licence',
            ),
            layer: 'pollen',                             // the layer the items belong to
            scopes: [Scope::DE, Scope::AT, Scope::CH, Scope::Border],
            schedule: new SourceSchedule(3600, termsMinIntervalSec: 10800),
            expectations: new SourceExpectations(emptyIsFailure: false, rejectedMeansPartial: true),
            order: 10,                                   // rank within the layer when items are merged
        );
    }

    public function create(PluginEnvironment $environment): SourcePlugin
    {
        $places = new CityDirectory([...$environment->data->cities(), ...$environment->data->borderPlaces()]);

        return new StandardSourcePlugin(new RequestParseMap($environment->http, new SourceParts(
            new PollenRequest($places),
            new PollenParser(new JsonBody(), new UtcTimeParser(), $places),
            new PollenMapper(),
            coverage: static fn(Scope $scope): Msg => new Msg('source.open-meteo-pollen.coverage', ['count' => count($places->forCountry($scope))]),
        )));
    }
}
```

The fields of `SourceDescription`:

| Field | Meaning |
|---|---|
| `id` | stable id, `[a-z0-9-]`; also the namespace of the texts and the key in `config.php` |
| `name` | display name |
| `attribution` | text and link exactly as the provider's terms require, shown verbatim and never translated; optional `license` |
| `layer` | id of the layer; the build fails if it does not exist or lacks one of the scopes |
| `scopes` | `DE`, `AT`, `CH` (countries), `Border` (zone of about 300 km beyond DACH), `Global` (one for everyone) |
| `schedule` | update rate, terms minimum, lane, own interval (3.5) |
| `expectations` | when the result counts as failed or partial (3.4) |
| `http` | `new HttpBudget(timeoutSec: 40, maxBytes: 20_000_000)` when the defaults (18 s, 5 MB) do not fit |
| `secrets` | names of the secrets the source needs, e.g. `['apiKey']` (3.6) |
| `order` | rank within the layer: on equally critical duplicates the smaller one wins |

The environment (`PluginEnvironment`) lends the plugin its services, each scoped to this plugin:

| Service | Use |
|---|---|
| `http` | the only way to the network: HTTPS only, size and time limits, user agent, retry; stamps the source id |
| `store` | small key-value store of the plugin (`read`, `write` with TTL, `delete`), e.g. a detail cache |
| `secrets` | only the secrets the source declared (`get`, `has`) |
| `xml` | safe XML reader (no DOCTYPE, no entities, no network) |
| `data` | the plugin's own `data/` files (`readOwnJson`) and the master data of the core: `cities()`, `borderPlaces()`, `regionCodes()`, `countries()` |

Pass each part only what it needs through its constructor; parsers and mappers use no port at all.

### 3.2 Request

A `SourceRequest` names the HTTP requests for one scope. Several requests are fetched in parallel (per host limited by the configuration).

```php
final class PollenRequest implements SourceRequest
{
    public const SOURCE_ID = 'open-meteo-pollen';

    public function __construct(private readonly CityDirectory $cities) {}

    public function requestsFor(Scope $scope, UtcInstant $now): array
    {
        $cities = $this->cities->forCountry($scope);

        return [HttpRequest::withQuery('https://air-quality-api.open-meteo.com/v1/air-quality', [
            'latitude' => implode(',', array_map(static fn(City $c): string => (string) $c->position->lat, $cities)),
            'longitude' => implode(',', array_map(static fn(City $c): string => (string) $c->position->lon, $cities)),
            'current' => implode(',', array_map(static fn(PollenType $t): string => $t->apiName(), PollenType::cases())),
            'timezone' => 'UTC',
        ], 'application/json', self::SOURCE_ID)];
    }
}
```

Other helpers of `HttpRequest`: `postJson()`, `withHeader()`, and for secrets `withSecretQuery()` / `withSecretHeader()` (3.6). Follow-up pages: pass a `Paging` to `SourceParts`; details per record (a second request per item): a `DetailSource`, see `plugins/providers/bbk-mowas` and `plugins/providers/geosphere-warnings`.

### 3.3 Parser and records

A `SourceParser` turns one response into records: plain readonly classes in `backend/Record/` that hold the data as the API delivers it. It counts every entry exactly once:

| Call | Meaning | Effect |
|---|---|---|
| `$counter->valid()` | a usable record | becomes an item |
| `$counter->skipped()` | correctly left out (cancelled, out of service, outside the season) | nothing |
| `$counter->rejected('missingField')` | broken: a field missing, a wrong type, a format change | counted by reason; can make the layer partial (3.4) and shows in `fetcher.php sources` |

A response that cannot be read at all (broken JSON, wrong root element) throws `UnreadableResponse`; the source then fails as a whole and its last good outcome stays.

```php
public function parse(HttpResponse $response, ParseContext $context): ParseResult
{
    $data = $this->json->decode($response);                 // throws UnreadableResponse for broken JSON
    $locations = $data->isList() ? $data->list() : [$data];
    $counter = new ParseCounter();
    $records = [];
    foreach ($this->cities->forCountry($context->scope) as $index => $city) {
        $current = ($locations[$index] ?? Decoded::of(null))->get('current');
        if ($current->isNull()) {
            $counter->rejected('missingPlace');
            continue;
        }
        $concentrations = $this->concentrations($current);
        if ($concentrations === []) {
            $counter->skipped();                            // no pollen in the model right now: no error
            continue;
        }
        $counter->valid();
        $records[] = new PollenValues($city, $this->time->parseUtc($current->get('time')->raw()), $concentrations);
    }

    return new ParseResult($records, $counter->statistics());
}
```

`Decoded` reads untrusted JSON safely: `get('a', 'b')` never fails, `string()`, `int()`, `float()` (finite only), `bool()` return `null` for a wrong type. The SDK has readers for the usual shapes: `JsonBody` (JSON, GeoJSON features), `XmlEntryReader` (via the environment), `UtcTimeParser` (ISO, epoch, local times with a zone), `NumberParser` (decimal comma), `TextCleaner`, `SafeUrl` (http/https only, else a fallback), `GeoJsonGeometry`, `NewestPerStation`, `StationDirectory`, `CityDirectory`.

### 3.4 Mapper and items

An `ItemMapper` maps one record to one item of the internal model, or `null` to drop it. A record that cannot become a valid item throws `\InvalidArgumentException`; `RequestParseMap` catches it per record, so one bad record never costs the source its other items.

```php
final class PollenMapper implements ItemMapper
{
    private const UNIT = 'Pollen/m³';

    public function map(object $record, ParseContext $context): Item
    {
        $record = RecordType::expect($record, PollenValues::class);
        [$type, $value] = $record->strongest();
        $facts = [];
        foreach ($record->concentrations as $name => $concentration) {
            $facts[] = new Fact(new Msg('source.open-meteo-pollen.type.' . $name), $concentration, self::UNIT);
        }

        return new ModelValueItem(
            new ItemCommon(
                id: 'pollen:' . $record->city->id,          // stable across runs, with a prefix of its own
                title: $record->city->name,
                url: 'https://open-meteo.com/en/docs/air-quality-api',
                time: $record->time,
                position: $record->city->position,
                regionIds: $record->city->regionId === null ? [] : [$record->city->regionId],
                country: $record->city->foreignCountry,     // only in the border zone
            ),
            new CatalogTerm('pollenConcentration'),         // a term of the catalog (3.7)
            $value,
            self::UNIT,
            $value > 0 ? new Msg('source.open-meteo-pollen.summary.strongest.' . $type) : new Msg('source.open-meteo-pollen.summary.none'),
            $facts,
        );
    }
}
```

The item kinds (`backend/src/Model/Item/`): `WarningItem`, `MeasurementItem`, `ModelValueItem`, `IndexItem`, `EarthquakeItem`, `TrafficNoticeItem`, `NewsItem`. Pick the kind by what the value *is*, not how it should look. Every item has an `ItemCommon`:

| Field | Rule |
|---|---|
| `id` | stable between runs and unique, with a prefix of the source, e.g. `pegelonline:<station uuid>`; never a position in a list or an id the API generates anew per request. Two sources that describe the same thing may share an id on purpose: the layer then merges them |
| `title`, `url` | the url leads to the original (checked by `SafeUrl`) |
| `time` | time of the value or message, UTC |
| `position` / `geometry` | point, line or area; items without a place are allowed (lists only) |
| `regionIds` | region of the item if the source knows it; otherwise the layer assigns regions from the position |
| `lang` | language of the text if it is not German |
| `country` | only in the border zone: the foreign country |

Texts for the user are never written into an item as literal German: use a `Msg` with a key of the plugin (`source.<id>.…`) and parameters. Data texts of the provider (titles, descriptions) stay as they are.

`SourceExpectations` judge the result:

- `SourceExpectations::measurements()`: no items is a failure (a measuring network never has nothing to report).
- `SourceExpectations::events()`: empty is valid (no warnings right now).
- `new SourceExpectations(emptyIsFailure: false, rejectedMeansPartial: true)`: custom, e.g. pollen outside the season.
- `rejectedMeansPartial: true`: rejected records mark the layer "unvollständig"; `minimumValid`: fewer valid records than this is a failure.

### 3.5 Schedule and terms of use

```php
schedule: new SourceSchedule(
    900,                          // updateRateSec: how often the source itself has new data
    termsMinIntervalSec: 1800,    // shortest interval the provider's terms allow; null = no limit
    lane: 'fast',                 // cron lane: fast (every minute), heavy (default, every 5 min), slow (every 30 min)
    intervalSec: null,            // own interval; null = the update rate
),
```

The core never fetches a source more often than its update rate or its terms minimum, also not after a failure (backoff 1 min doubling to 30 min, `Retry-After` honoured up to a day) and not after a new release. Warnings belong in `fast`, everything else in `heavy`.

Respect the provider's terms in the code, not only in the profile:

- **Attribution** exactly as required, including a required licence reference.
- **Quotas**: a limit per day counts every request, and for batch APIs often every location (Open-Meteo: 126 places × 96 runs a day is already over 10,000). Compute the volume and set `termsMinIntervalSec` so that all sources of the provider together stay below it; write the calculation into `PROFILE.md`.
- **Detail requests**: cap them per run (see `MowasFactory`, 30 per run) and cache them in the store.

### 3.6 Secrets

A source that needs an API key declares it and reads it from its environment. The secret never reaches a log or a recording: requests are identified by their redacted URL.

```php
// describe()
secrets: ['apiKey'],

// create()
new ExampleRequest($environment->secrets->get('apiKey')),

// in the request
return [HttpRequest::withQuery('https://api.example.org/v1/items', ['bbox' => $bbox], 'application/json', self::SOURCE_ID)
    ->withSecretQuery('key', $this->apiKey)];          // or ->withSecretHeader('Authorization', 'Bearer ' . $this->apiKey)
```

The operator puts it into `config.php`; a source with a missing secret is not run and `fetcher.php check` names it:

```php
'sources' => ['example' => ['secrets' => ['apiKey' => '…']]],
```

### 3.7 Texts and catalog terms

`messages/de.json` holds every text key the source uses, in its own namespace:

```json
{
  "source.open-meteo-pollen.type.birch": "Birke",
  "source.open-meteo-pollen.summary.none": "keine Pollen im Modell",
  "source.open-meteo-pollen.coverage": "Modellwerte (CAMS) für {count} ausgewählte Orte."
}
```

A quantity, warning category, news category or news feed the core does not know is contributed through `catalog.json`; the lists are `measuredQuantities`, `modelQuantities`, `warningCategories`, `newsCategories`, `newsFeeds`. A term belongs to exactly one contributor; the build fails on a duplicate.

```json
{
  "modelQuantities": [
    {
      "id": "pollenConcentration",
      "label": "Pollenkonzentration",
      "definition": "Pollen grains per cubic metre of air from a pollen model (CAMS), not a measurement.",
      "decimals": 1,
      "shortSuffix": ""
    }
  ]
}
```

### 3.8 Recordings and tests

Every scope of a source needs a recording of the real API in `tests/responses/<scope>/`. Record it with:

```sh
docker compose run --rm composer php ../tools/record/record-plugin.php open-meteo-pollen --scope=AT --limit=25
```

The tool runs the plugin against the real API, trims large responses to the first N items and writes `index.json` (request signature, status, content type, body file) plus the bodies. Secrets never reach the recording. Re-record when the provider changes its format.

The generic check (`PluginContractTest`) runs every source on its recordings without any entry per plugin. It fails when `PROFILE.md` is missing, a scope has no recording, an item violates the item schema (e.g. a quantity no catalog knows) or a text key has no text.

Edge cases the recording does not show go into unit tests of the parser and mapper, with small inline responses:

```php
final class PollenParserTest extends TestCase
{
    /** Outside the season the model has no values: skipped, not an error; a place missing from the response is one. */
    public function testEmptyPlacesAreSkippedMissingOnesRejected(): void
    {
        $response = Fixtures::json([
            ['current' => ['time' => '2026-01-10T12:00', 'alder_pollen' => null, 'birch_pollen' => null, 'grass_pollen' => null]],
        ]);
        $places = new CityDirectory([/* two City objects: Wien, Graz */]);

        $result = (new PollenParser(new JsonBody(), new UtcTimeParser(), $places))
            ->parse($response, new ParseContext(Scope::AT, UtcInstant::fromIso('2026-01-10T12:00:00Z')));

        self::assertSame([], $result->records);
        self::assertSame(1, $result->statistics->skipped);
        self::assertSame(['missingPlace' => 1], $result->statistics->rejectedByReason);
    }
}
```

Larger edge cases (a cancelled warning, a broken geometry) can live as files in `tests/cases/` and be served to the layer test in place of the API: `(new LayerHarness())->respond('https://maps.dwd.de/', 'dwd-warnings', 'de-cases.json')`, see `plugins/layers/warnings/tests/WarningsLayerTest.php`.

### 3.9 PROFILE.md

```markdown
# Profile: <id>

| Section | Content |
|---|---|
| Provider | who publishes the data |
| Endpoint | URL, method, parameters, how many requests per scope |
| Format | JSON/XML/GeoJSON, units, time zone of the times |
| Authentication | none, or the secret and where to get it |
| License | the licence of the data |
| Attribution | the text exactly as in the code |
| Terms of use | limits (per minute, hour, day), commercial use, the volume calculation and the chosen intervals |
| Update rate | how often the provider has new data |
| Volume | items per scope |
| Quirks | what is skipped or rejected and why, odd fields, known gaps |
| Code | the classes |
| History | when added and changed |
```

## 4. Writing a layer

### 4.1 Description

```php
final class PollenLayerFactory implements LayerPluginFactory
{
    public function describe(): LayerDescription
    {
        return new LayerDescription(
            id: 'pollen',
            global: false,            // true: one layer for everyone (space weather, news)
            border: true,             // also in the border zone beyond DACH
            color: '#c2507e',         // default color; the host page can override it with --cs-layer-pollen
            icon: 'flower-2',         // a lucide icon name; the build fails for an unknown one
            onMap: true,              // needs frontend/map.ts
            regionFilter: true,       // items can be filtered by region
            view: 'measurements',     // list view: 'measurements', 'events' or null
            defaultActive: false,
            order: 35,                // position in the layer list        );
    }

    public function create(LayerEnvironment $environment): LayerPlugin
    {
        return new PollenLayer($environment->mechanics, $environment->data->readOwnJson('names.json'));
    }
}
```

### 4.2 Definition per scope

The layer returns, for each of its scopes, its processing steps, its note, its key figures and its display name and link:

```php
final class PollenLayer implements LayerPlugin
{
    public function __construct(
        private readonly LayerMechanics $mechanics,
        private readonly Decoded $names,
    ) {}

    public function definition(Scope $scope, LayerSources $sources): LayerDefinition
    {
        [$name, $url] = $sources->nameAndLink($scope, $this->names);   // curated in names.json, else from the sources
        $steps = $scope === Scope::Border
            ? [new ItemDeduplicator(), ...$this->mechanics->borderZone()]
            : [new ItemDeduplicator(), $this->mechanics->regions($scope)];

        return new LayerDefinition(LayerId::from('pollen'), $scope, $name, $url, new Msg('layer.pollen.note'), $steps, new EmptyStatsBuilder());
    }
}
```

Steps run in order on the merged items of all sources of the layer. The SDK brings:

| Step | Use |
|---|---|
| `ItemDeduplicator` | merges items with the same id; the more critical wins, then the source rank |
| `UrlDeduplicator` | keeps the first item of each link (news) |
| `ExpiredItemFilter` | removes expired warnings |
| `RecentItemFilter(maxAgeSec)` | keeps items within a time window |
| `ItemLimit(maximum)` | caps the number of items |
| `SeveritySorter`, `NewestFirstSorter` | order |
| `FreshnessApplier(check, maxAgeHours)` | marks assessments of old measurements as not current |
| `$mechanics->regions($scope)` | assigns the items to the regions of the country |
| `$mechanics->borderZone()` | the steps of the border zone: only items outside DACH within the zone, with their nearby countries |

A step of your own implements `PipelineStep::apply(array $items, UtcInstant $now): array`. In a country scope the core adds the nearby countries and regions itself.

Key figures: `EmptyStatsBuilder` for none; otherwise a `StatsBuilder` of your own plus `schema/stats.schema.json` (see `plugins/layers/radiation`, `plugins/layers/space`).

### 4.3 Settings

A layer can read numbers from `config.php` (`layers.<id>.settings`). It declares them with default and range; the core validates them and `fetcher.php check` lists them:

```php
// describe()
settings: [
    new LayerSetting('warningUSvH', 0.3, 0.01, null, 'orange from this dose rate (µSv/h)'),
    new LayerSetting('highUSvH', 1.0, 0.01, null, 'red from this dose rate (µSv/h)'),
],

// create()
$thresholds = new RadiationThresholds($environment->settings->float('warningUSvH'), $environment->settings->float('highUSvH'));
```

Checks across settings (high above warning) are the layer's: throw `\InvalidArgumentException`, the core reports it as a configuration error.

### 4.4 Texts and names

`messages/de.json` needs at least the name and the note:

```json
{
  "layer.pollen.name": "Pollenflug",
  "layer.pollen.note": "Modellwerte (CAMS) der Pollenkonzentration für ausgewählte Orte, …"
}
```

`data/names.json` gives display name and link per scope; a scope without an entry gets the names of its sources joined with " · ":

```json
{
  "DE": { "name": "CAMS · Open-Meteo", "url": "https://open-meteo.com/en/docs/air-quality-api" }
}
```

### 4.5 Map (frontend/map.ts)

A layer on the map exports `map` with a renderer and a draw rank. The renderer turns items into GeoJSON features and names the MapLibre style layers that draw them. Import only from `@sdk/map` (and `@contract/…`, your own modules); ESLint and dependency-cruiser refuse anything else.

```ts
import type { ItemFeature, LayerMapPart, LayerRenderer } from '@sdk/map';
import { circleLayer, pointGeometry, pointStyle } from '@sdk/map';

/** Label of a place: name and rounded pollen concentration, e.g. "Wien 6". */
export function pollenLabel(title: string, value: number): string {
  return `${title} ${Math.round(value)}`;
}

export const pollenLayer: LayerRenderer = {
  toFeatures: (items, context) =>
    items.flatMap((item): ItemFeature[] => {
      const geometry = pointGeometry(item);
      if (item.kind !== 'modelValue' || geometry === null) return [];
      return [
        {
          type: 'Feature',
          geometry,
          properties: {
            itemId: item.id,
            layer: context.layer,
            label: pollenLabel(item.title, item.value),
            ...pointStyle(null, context.layerColor),
          },
        },
      ];
    }),
  styleLayers: (sourceId) => [circleLayer(sourceId)],
};

export const map: LayerMapPart = { renderer: pollenLayer, drawRank: 55 };
```

`@sdk/map` offers `pointGeometry`, `drawGeometry` (points, lines, areas), `pointStyle` (the uniform point size with a fill color), `circleLayer`, `lineLayer`, `measurementFeatures` (measurements colored by assessment), `effectiveLevel`, `warningColor` and the fixed palettes (`LEVEL_COLORS`, `SEVERITY_COLORS`, `AWARENESS_COLORS`). Use the uniform point style so that dots of all layers have the same size. `drawRank` orders the layers from bottom to top; it is unique (the region outline of the core lies at 20).

### 4.6 User interface (frontend/ui.ts)

Everything here is optional and pure data plus functions; the core renders it with its own components and translates the messages. A tile in "Messwerte im Überblick", from `plugins/layers/air`:

```ts
import type { LayerUiPart, ModelValueItem } from '@sdk/ui';

export const AIR_HIGHLIGHT_ABOVE = 40;

export const ui: LayerUiPart = {
  tile: {
    rank: 20,
    title: { key: 'layer.air.tile.title' },
    value: ({ matched, format }) => {
      const item = matched.find((entry): entry is ModelValueItem => entry.kind === 'modelValue');
      if (item === undefined) return null; // the core shows "Wird geladen" or "Keine Daten"
      return {
        value: `${format.number(item.value, 0)} ${item.unit}`,
        detail: `${item.title} · ${format.text(item.summary)}`,
        time: format.time(item.time),
        highlight: item.value > AIR_HIGHLIGHT_ABOVE,
        itemId: item.id,
      };
    },
  },
};
```

The other parts of `LayerUiPart`:

| Part | Shows |
|---|---|
| `legend` | title and lines explaining the point colors, per selected countries |
| `panel` | the side panel next to the map (news) |
| `notice` | the notice above the map with a summary and "Anzeigen" (warnings); gets the availability, so "loading" and "error" are told apart from "none" |
| `newEntriesToast` | message key with `{count}` when an update brings new entries |
| `emptyText`, `officialLinks` | text of the detail sheet without entries; links to the official information of the country |

### 4.7 Tests of a layer

The layer test runs the layer on the recordings of its sources. `record()` also stores the assembled snapshot as `contract/fixtures/<layer>-<scope>.json`, the example the frontend tests use:

```php
final class PollenLayerTest extends TestCase
{
    private const RECORDED_NOW = '2026-10-03T19:45:00Z';   // shortly after the recordings

    #[DataProvider('scopes')]
    public function testRecordingsGiveValidSnapshots(Scope $scope): void
    {
        $snapshot = (new LayerHarness())->record(LayerId::from('pollen'), $scope, self::RECORDED_NOW);

        self::assertNotSame([], $snapshot->items);
        self::assertContainsOnlyInstancesOf(ModelValueItem::class, $snapshot->items);
    }

    /** Outside the pollen season the model has no values: the layer is empty, not failed. */
    public function testOutsideTheSeasonTheLayerIsEmptyNotFailed(): void
    {
        $harness = new LayerHarness();
        $empty = ['current' => ['time' => '2026-01-10T12:00', 'alder_pollen' => null, /* … */]];
        $harness->http->respond('https://air-quality-api.open-meteo.com/', (string) json_encode(array_fill(0, 9, $empty)));

        $snapshot = $harness->snapshot(LayerId::from('pollen'), Scope::AT, self::RECORDED_NOW);

        self::assertSame(FeedStatus::Ok, $snapshot->status);
        self::assertSame([], $snapshot->items);
    }
}
```

Frontend tests sit next to the code and use those fixtures through `@sdk/testing`:

```ts
import pollenAt from '@contract/fixtures/pollen-AT.json';
import { asSnapshot, testRenderContext } from '@sdk/testing';
import { describe, expect, it } from 'vitest';
import { pollenLayer } from './map';

describe('pollen on the map', () => {
  it('draws every place of the snapshot', () => {
    const items = asSnapshot(pollenAt).items;
    const features = pollenLayer.toFeatures(items, testRenderContext('pollen'));
    expect(features.map((feature) => feature.properties.itemId)).toEqual(items.map((item) => item.id));
  });
});
```

Every layer package also passes the generic checks without an entry: `LayerContractTest` (name text, valid snapshots, known texts) and `frontend/tests/layers/layer-packages.test.ts` (icon, color variable, texts of its parts, a renderer exactly when on the map, unique ranks, renderer and tile work with the fixtures).

## 5. Rules

- **Isolation:** a package uses only `CommonSight\Model`, `CommonSight\Sdk` and `CommonSight\Port`, never the core and never another package (`deptrac`, `PluginIsolationTest`). Parsers, mappers and records use no port. The frontend of a layer imports only `@sdk/…`, `@contract/…` and its own modules.
- **Network:** only through `$environment->http`. HTTPS, size and time limits of the core always apply.
- **Failures:** expected failures (HTTP errors, unreadable responses) are handled by `StandardSourcePlugin`; never catch and hide them. A failing source never cancels the rest of a run, and its last good outcome stays until a newer one.
- **Stable ids:** items keep their id across runs, otherwise the "new entries" notice and the map focus break.
- **Times in UTC**, local times parsed with the provider's zone (`UtcTimeParser::parse($text, $zone)`).
- **Texts:** German in `messages/de.json`, never literal in PHP or TypeScript; attribution texts stay verbatim.
- **Code style:** the checks of the repository apply to plugins too: PHP-CS-Fixer, PHPStan (max level, cognitive complexity), ESLint, Prettier.

## 6. Build, check, run

```sh
./dev.sh test                                   # everything: backend checks, frontend checks, end-to-end

# backend only (generates the contract first: catalog, layer registry, plugin registry, texts)
docker compose run --rm -T composer composer check-all
docker compose run --rm -T composer vendor/bin/phpunit --filter PollenLayerTest

# frontend only (generates types and the layer registries first)
docker compose run --rm -T node npm run check

# deploy to the local environment and run the new source
./dev.sh deploy
docker compose exec -T -u commonsight scheduler php /home/commonsight/commonsight/current/bin/fetcher.php run --layer pollen --scope AT
docker compose exec -T -u commonsight scheduler php /home/commonsight/commonsight/current/bin/fetcher.php sources
```

`bin/build-contract.php` (part of `check-all`) discovers the packages and fails for an id that differs from its folder, a source naming an unknown layer or a scope its layer does not have, a source package with a `frontend/` folder, a layer package without one, and a duplicate catalog term. The frontend generator fails for an unknown icon and for a layer on the map without `map.ts`.

## 7. Checklist

**Source**

- [ ] in `plugins/providers/` (or `plugins/news/` for a news feed); `plugin.php`, factory with a constructor without parameters, id equal to the folder name
- [ ] attribution verbatim, licence reference if required
- [ ] update rate and terms minimum set; quota calculation in `PROFILE.md`
- [ ] parser counts valid, skipped and rejected; unreadable responses throw `UnreadableResponse`
- [ ] stable item ids with the source as prefix; times in UTC
- [ ] every text key in `messages/de.json`, new terms in `catalog.json`
- [ ] a recording per scope; unit tests for the edge cases
- [ ] `PROFILE.md` complete

**Layer**

- [ ] in `plugins/layers/`; description with color, lucide icon, scopes, view, order
- [ ] steps per scope (dedup, regions or border zone, filters, sort)
- [ ] `layer.<id>.name` and `layer.<id>.note`, `data/names.json`
- [ ] `frontend/map.ts` when on the map (unique draw rank), `frontend/ui.ts` if needed
- [ ] layer test on the recordings of its sources (writes the fixtures), frontend tests
- [ ] `LAYER.md` complete

**Both**

- [ ] `./dev.sh test` passes
- [ ] `fetcher.php check` and a real run in the local environment look right

## 8. Authentication providers

A third, small kind of plugin decides who may see the start page (`internal/ACCESS-AND-BRANDING.md`, `doc/ARCHITECTURE.md` 6.5). Its package lies in `plugins/auth/<id>/`: `plugin.php` returns an `AuthProviderFactory`, `backend/` holds its code, `tests/` its tests, `AUTH.md` what it admits and which settings it reads (see `plugins/auth/woltlab`).

```php
final class ExampleAuthFactory implements AuthProviderFactory
{
    public function describe(): AuthDescription
    {
        return new AuthDescription('example', 'Example community');
    }

    /** Settings from config.php (`auth.settings`); invalid ones throw \InvalidArgumentException, a configuration error. */
    public function create(AuthEnvironment $environment): AuthProvider
    {
        $url = $environment->settings->get('url')->string() ?? throw new \InvalidArgumentException('url is needed');

        return new ExampleAuth($url);
    }
}

final class ExampleAuth implements AuthProvider
{
    public function __construct(private readonly string $url) {}

    /** Yes or no from the cookies; a failure is thrown as \RuntimeException: the core closes the page and logs it. */
    public function admits(AuthRequest $request): bool
    {
        return ($request->cookies['example_session'] ?? '') !== '' && $this->askTheCommunity($request->cookies['example_session']);
    }

    /** Name and links on the members card; the login may return to the start page. */
    public function community(AuthRequest $request): Community
    {
        return new Community('Example', $this->url . 'login?return=' . rawurlencode($request->pageUrl), $this->url . 'register');
    }
}
```

Rules: decide only yes or no, keep and log nothing about the visitor, ask an outside service only when the request carries something to check (a page without a session cookie costs nothing), and throw on a failure instead of guessing. The operator activates a provider with `'auth' => ['provider' => '<id>', 'settings' => [...]]`; `fetcher.php check` creates it and reports invalid settings.

