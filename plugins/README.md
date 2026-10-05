# Plugins

Every source of data and every layer is a plugin: one self-contained package in this folder (concepts in `internal/SOURCE-INTEGRATION.md` and `internal/LAYER-PLUGINS.md`). How to write one, with code samples: [doc/PLUGIN-DEVELOPMENT.md](../doc/PLUGIN-DEVELOPMENT.md). Adding a source or a layer means adding its package and nothing else; no file outside the packages changes.

- A **source** describes itself, does its own API calls and maps the raw data of its API into the internal data model (the item kinds). It names the layer it belongs to.
- A **layer** defines what happens with the items of its sources (steps, note, key figures, settings) and how it appears in the frontend (map, legend, tiles, side panel, map notice).

The core discovers the packages at build time, runs the sources, isolates their failures, assembles the layers and delivers the data. The kind of a package follows from its factory; ids are unique across both kinds (layers use domain words such as `water`, sources name the provider such as `pegelonline`). The packages lie in three groups, and the build refuses one in the wrong group:

- `layers/`: the layers
- `news/`: the sources of news feeds
- `providers/`: all other sources, the data providers
- `auth/`: the authentication providers, which decide who may see the start page (`internal/ACCESS-AND-BRANDING.md`); first: `woltlab`

## Package layout

```text
plugins/
├─ providers/<source id>/         e.g. providers/pegelonline (news feeds: news/<source id>/)
│  ├─ plugin.php                  manifest: returns the SourcePluginFactory
│  ├─ backend/                    PHP, namespace CommonSight\Plugin\<Name>\: factory, request, parser, mapper, records
│  ├─ messages/de.json            texts in the namespace source.<id>., if any
│  ├─ catalog.json                catalog terms the source contributes (quantities, categories), if any
│  ├─ data/                       master data owned by the source (e.g. stations.json), if any
│  ├─ tests/                      responses/<scope>/ (recordings), cases/ (edge cases), *Test.php
│  └─ PROFILE.md                  provider, endpoints, license, attribution, terms of use, update rate, quirks
└─ layers/<layer id>/             e.g. layers/water
   ├─ plugin.php                  manifest: returns the LayerPluginFactory
   ├─ backend/                    PHP, namespace CommonSight\Plugin\<Name>Layer\: factory, layer, own assessments
   ├─ frontend/                   map.ts (renderer, draw rank), ui.ts (legend, tile, panel, notice), *.test.ts
   ├─ messages/de.json            texts in the namespace layer.<id>. (name, notes, legend, slots)
   ├─ data/names.json             display name and link per scope
   ├─ schema/stats.schema.json    the layer's key figures, if any
   ├─ tests/                      *LayerTest.php: its cases on the recordings of its sources
   └─ LAYER.md                    what the layer shows, sources, registry entry, settings, frontend
```

## Contract

- **Sources:** `CommonSight\Sdk\Plugin\SourcePluginFactory` describes the source (`describe()`: `SourceDescription`) and creates the `SourcePlugin` (`create(PluginEnvironment)`), whose `fetch()` returns a `SourceOutcome`.
- **Layers:** `CommonSight\Sdk\Layer\LayerPluginFactory` describes the layer (`describe()`: `LayerDescription`) and creates the `LayerPlugin` (`create(LayerEnvironment)`), which returns a `LayerDefinition` per scope.
- **Frontend of a layer:** `frontend/*.ts` imports only `@sdk/…` (the plugin API of the core), `@contract/…` and its own modules; tests also vitest. ESLint and dependency-cruiser check it.
- **Isolation:** a package uses only `CommonSight\Model`, `CommonSight\Sdk` and `CommonSight\Port`, never the core and never another package (`deptrac`, `PluginIsolationTest`). Parsers, mappers and record types use no port themselves (`deptrac`); they may use other classes of their own plugin, which must not bring network or store in through the back door.
- A source calls the network only through the HTTP client of its environment; HTTPS, size and time limits of the core always apply.
- The terms of use of the provider are respected in the code: attribution verbatim, no fetch more often than the terms allow.
- Every package passes a generic check without any entry elsewhere (`PluginContractTest`, `LayerContractTest`, `tests/layers/layer-packages.test.ts`).
