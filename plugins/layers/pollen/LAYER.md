# Layer: pollen

| Section | Content |
|---|---|
| Purpose | Current model pollen concentrations (CAMS) at selected places, in the countries and the border zone: per place the strongest pollen type, all types as facts; no health assessment. |
| Scopes | DE, AT, CH, border |
| Registry | color `#c2507e` (apart from the ochre of radiation), icon `flower-2`, on the map: yes, region filter: yes, list view: measurements, active by default: no |
| Sources | the source plugins that name the layer `pollen` (today `open-meteo-pollen`) |
| Display name and link | per scope in `data/names.json` (curated); composed from the sources where a scope has none |
| Notes | `messages/de.json` (`layer.pollen.note`); the sources add how many places they cover |
| Frontend | `frontend/map.ts`: the places as circles in the layer color, the strongest concentration as label |
| Code | `PollenLayerFactory` (description), `PollenLayer` (steps, note per scope) |
| History | added on 2026-10-03 as the proof of layers as plugins (L7): only this package and `plugins/providers/open-meteo-pollen` |
