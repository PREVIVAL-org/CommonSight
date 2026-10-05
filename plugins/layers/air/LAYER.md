# Layer: air

| Section | Content |
|---|---|
| Purpose | Current model air quality (CAMS) at selected places, in the countries and the border zone (Q-AI-*). |
| Scopes | DE, AT, CH, border |
| Registry | color `#348f7b`, icon `wind`, on the map: yes, region filter: yes, list view: measurements, active by default: no |
| Sources | the source plugins that name the layer `air` |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.air.note…`) |
| Code | `AirLayerFactory` (description), `AirLayer` (steps and note per scope; no key figures) |
| History | moved from the core into a package on 2026-10-03 (concept: layers as plugins, L2) |
