# Profile: appa-bz

| Section | Content |
|---|---|
| Provider | Agentur für Umwelt und Klimaschutz der Autonomen Provinz Bozen – Südtirol |
| Endpoint | `GET https://dati.retecivica.bz.it/services/airquality/sensors` (air and gamma probes; only the gamma probes are used) |
| Format | JSON |
| Authentication | none |
| License | CC0 |
| Attribution | "Strahlung: Umweltagentur Südtirol (CC0)" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | hourly |
| Volume | six gamma probes |
| Classification | by the radiation layer (B-13, `DoseRateAssessmentApplier`) |
| Quirks | the station list of the service has no usable positions, the town centres are used; station master data in `data/stations.json` (`tools/stations/build.mjs`, ADR 0038) |
| Code | `AppaBzRequest`, `AppaBzParser`, mapped by `DoseRateReadingMapper` of the SDK |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
