# Profile: bfs-odl

| Section | Content |
|---|---|
| Provider | Bundesamt für Strahlenschutz (BfS), ODL measuring network, published via IMIS |
| Endpoint | WFS `https://www.imis.bfs.de/ogc/opendata/ows`, layer `opendata:odlinfo_odl_1h_latest` |
| Format | GeoJSON; dose rate in µSv/h with averaging period; times UTC |
| Authentication | none |
| License | to be checked (dl-de/by-2-0 per the BfS open data terms) |
| Attribution | "Strahlung: Bundesamt für Strahlenschutz (BfS), ODL (Datenlizenz Deutschland – Namensnennung – 2.0)": names the source and the licence, as dl-de/by-2-0 requires (wording to be checked) |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 10 minutes (hourly values) |
| Volume | at least 1,000 probes expected |
| Classification | by the radiation layer (B-13, `DoseRateAssessmentApplier`) |
| Code | `ImisRequest`, `ImisParser` (`Record\DoseRateStation`), `ImisMapper` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
