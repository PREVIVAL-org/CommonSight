# Layer: traffic

| Section | Content |
|---|---|
| Purpose | Traffic notices of the road operators and automobile clubs; Switzerland waits for a data access (Q-TR-*). |
| Scopes | DE, AT, CH |
| Registry | color `#768d9f`, icon `route`, on the map: yes, region filter: yes, list view: events, active by default: no |
| Sources | the source plugins that name the layer `traffic` |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.traffic.note…`) |
| Code | `TrafficLayerFactory` (description), `TrafficLayer` (steps and note per scope; no key figures) |
| History | moved from the core into a package on 2026-10-03 (concept: layers as plugins, L2) |
