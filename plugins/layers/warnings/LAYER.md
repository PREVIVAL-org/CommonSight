# Layer: warnings

| Section | Content |
|---|---|
| Purpose | Official warnings: weather warnings and civil protection messages per country, most severe first (Q-W-*). |
| Scopes | DE, AT, CH |
| Registry | color `#dc8c2b`, icon `triangle-alert`, on the map: yes, region filter: yes, list view: events, active by default: yes |
| Sources | the source plugins that name the layer `warnings` |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.warnings.note…`) |
| Code | `WarningsLayerFactory` (description), `WarningsLayer` (steps and note per scope; no key figures) |
| History | moved from the core into a package on 2026-10-03 (concept: layers as plugins, L2) |
