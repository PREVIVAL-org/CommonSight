# Layer: water

| Section | Content |
|---|---|
| Purpose | Water levels and discharges of the gauges, classified by the stages of their services; outdated after 6 hours (Q-WA-*, B-10 to B-12). |
| Scopes | DE, AT, CH, border |
| Registry | color `#398ed4`, icon `waves-horizontal`, on the map: yes, region filter: yes, list view: measurements, active by default: yes |
| Sources | the source plugins that name the layer `water` |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.water.note…`) |
| Code | `WaterLayerFactory` (description), `WaterLayer` (steps and note per scope; no key figures) |
| History | moved from the core into a package on 2026-10-03 (concept: layers as plugins, L2) |
