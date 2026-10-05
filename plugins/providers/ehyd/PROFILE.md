# Profile: ehyd

| Section | Content |
|---|---|
| Provider | Hydrographischer Dienst Österreich, Bundesministerium für Land- und Forstwirtschaft, Regionen und Wasserwirtschaft (BML) |
| Endpoint | `GET https://ehyd.gv.at/services/PegelAktuell/json` |
| Format | JSON (GeoJSON features); water levels in cm or "m ü.A" (above the Adriatic, i.e. sea level); local time Europe/Vienna |
| Authentication | none |
| License | to be checked |
| Attribution | "Pegel: Hydrographie Österreich (BML) · eHYD" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 15 minutes |
| Volume | about 300 gauges |
| Classification | by the flood code `gesamtcode` of eHYD (B-11, `AustrianWaterAssessor`) |
| Code | `EhydRequest`, `EhydParser` (`Record\EhydGauge`), `EhydMapper`, `AustrianWaterAssessor` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
