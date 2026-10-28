# Layer: warnings

| Section | Content |
|---|---|
| Purpose | Official warnings: weather warnings and civil protection messages per country, most severe first (Q-W-*). |
| Scopes | DE, AT, CH |
| Registry | color `#dc8c2b`, icon `triangle-alert`, on the map: yes, region filter: yes, list view: events, active by default: yes |
| Sources | the source plugins that name the layer `warnings` |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.warnings.note…`) |
| Key figures | `stats.situation`: situation level of the country from 1 to 5 with warnings and regions per grade (`internal/SITUATION-LEVEL.md`, schema `schema/stats.schema.json`) |
| Code | `WarningsLayerFactory` (description), `WarningsLayer` (steps and note per scope), `WarningGrade` (common scale of the sources), `SituationStatsBuilder`, `SituationStats` |
| History | moved from the core into a package on 2026-10-03 (concept: layers as plugins, L2) |
