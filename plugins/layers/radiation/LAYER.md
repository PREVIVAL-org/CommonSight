# Layer: radiation

| Section | Content |
|---|---|
| Purpose | Ambient dose rate of the gamma probes, classified by display thresholds of this layer (settings warningUSvH, highUSvH); outdated after 12 hours (Q-RA-*, B-13). |
| Scopes | DE, AT, CH, border |
| Registry | color `#b0a235`, icon `radiation`, on the map: yes, region filter: yes, list view: measurements, active by default: no |
| Sources | the source plugins that name the layer `radiation` |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.radiation.note…`) |
| Code | `RadiationLayerFactory` (description), `RadiationLayer` (steps, note, key figures per scope) |
| History | moved from the core into a package on 2026-10-03 (concept: layers as plugins, L2) |
