# Layer: nature

| Section | Content |
|---|---|
| Purpose | Earthquakes of the last seven days in and around the countries, newest first (Q-NA-*). |
| Scopes | DE, AT, CH, border |
| Registry | color `#9476ce`, icon `activity`, on the map: yes, region filter: yes, list view: events, active by default: no |
| Sources | the source plugins that name the layer `nature` |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.nature.note…`) |
| Code | `NatureLayerFactory` (description), `NatureLayer` (steps and note per scope; no key figures) |
| History | moved from the core into a package on 2026-10-03 (concept: layers as plugins, L2) |
