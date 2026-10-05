# Profile: eurdep

| Section | Content |
|---|---|
| Provider | EURDEP (European Radiological Data Exchange Platform), Swiss probes, published via BfS/IMIS |
| Endpoint | WFS `https://www.imis.bfs.de/ogc/opendata/ows`, layer `opendata:eurdep_latestValue`, filtered by the country prefix |
| Format | GeoJSON; dose rate in µSv/h with averaging period; times UTC |
| Authentication | none |
| License | to be checked |
| Attribution | "Strahlung: EURDEP (Schweiz) über BfS" (wording to be checked) |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 10 minutes |
| Volume | at least 30 probes expected |
| Classification | by the radiation layer (B-13, `DoseRateAssessmentApplier`) |
| Code | `ImisRequest`, `ImisParser` (`Record\DoseRateStation`), `ImisMapper` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
