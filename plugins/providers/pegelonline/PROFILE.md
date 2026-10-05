# Profile: pegelonline

| Section | Content |
|---|---|
| Provider | Wasserstraßen- und Schifffahrtsverwaltung des Bundes (WSV), via ITZBund |
| Endpoint | `GET https://www.pegelonline.wsv.de/webservices/rest-api/v2/stations.json` with current measurement and characteristic water levels per gauge |
| Format | JSON, list of stations; coordinates `latitude`/`longitude`; times with offset (Europe/Berlin); water level in cm above gauge zero |
| Authentication | none |
| License | to be checked: PEGELONLINE states "Datenlizenz Deutschland – Zero – Version 2.0" for its open data |
| Attribution | "Pegel: WSV · PEGELONLINE" with link to https://www.pegelonline.wsv.de/ |
| Terms of use | to be checked (provider contacted, approval assumed); no fetch interval prescribed so far |
| Update rate | every 15 minutes |
| Volume | about 650 gauges, response a few 100 KB |
| Classification | by the gauges' own states `stateMnwMhw` and `stateNswHsw` (B-10, `GermanWaterAssessor`); `commented`/`out-dated` mean disturbed |
| Quirks | gauges without current measurement are skipped; HSW can lie below MHW, so both are evaluated independently |
| Code | `PegelonlineRequest`, `PegelonlineParser` (`Record\Station`), `PegelonlineMapper`, `GermanWaterAssessor` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
