# Layer: news

| Section | Content |
|---|---|
| Purpose | Current headlines of public broadcasters, filtered by topic (Q-NE-*). |
| Scopes | global |
| Registry | color `#657488`, icon `newspaper`, on the map: no, region filter: no, list view: none, active by default: no |
| Sources | the source plugins that name the layer `news` |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.news.note…`) |
| Code | `NewsLayerFactory` (description), `NewsLayer` (steps and note per scope; no key figures) |
| History | moved from the core into a package on 2026-10-03 (concept: layers as plugins, L2) |
