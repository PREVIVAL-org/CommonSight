# Layer: space

| Section | Content |
|---|---|
| Purpose | Global space weather: planetary Kp index and NOAA scales G, R, S (Q-SP-*). |
| Scopes | global |
| Registry | color `#a37dc2`, icon `satellite`, on the map: no, region filter: no, list view: measurements, active by default: no |
| Sources | the source plugins that name the layer `space` |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.space.note…`) |
| Code | `SpaceLayerFactory` (description), `SpaceLayer` (steps, note, key figures per scope) |
| History | moved from the core into a package on 2026-10-03 (concept: layers as plugins, L2) |
