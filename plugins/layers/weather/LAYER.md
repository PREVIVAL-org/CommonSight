# Layer: weather

| Section | Content |
|---|---|
| Purpose | Current model weather at selected places, in the countries and the border zone (Q-WE-*). |
| Scopes | DE, AT, CH, border |
| Registry | color `#388bbb`, icon `cloud-sun`, on the map: yes, region filter: yes, list view: measurements, active by default: yes |
| Sources | the source plugins that name the layer `weather` |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.weather.note…`) |
| Code | `WeatherLayerFactory` (description), `WeatherLayer` (steps and note per scope; no key figures) |
| History | moved from the core into a package on 2026-10-03 (concept: layers as plugins, L2) |
