# Profile: chmi

| Section | Content |
|---|---|
| Provider | Český hydrometeorologický ústav (ČHMÚ) |
| Endpoint | `GET https://opendata.chmi.cz/hydrology/now/data/<station>.json`, one request per gauge with flood stages |
| Format | JSON, static files regenerated every 10 to 30 minutes |
| Authentication | none |
| License | CC BY 4.0 |
| Attribution | "Pegel: ČHMÚ (CC BY 4.0)" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 10 minutes |
| Volume | about 400 small files |
| Classification | by the flood stages SPA 1-3 of ČHMÚ (`ChmiStageAssessor`, an SDK `FloodStageAssessor`) |
| Quirks | a gauge out of operation answers without values; station master data in `data/stations.json` (`tools/stations/build.mjs`, ADR 0038) |
| Code | `ChmiRequest`, `ChmiParser`, mapped by `WaterReadingMapper` of the SDK |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
