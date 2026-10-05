# Profile: umweltbundesamt

| Section | Content |
|---|---|
| Provider | Strahlenfrühwarnsystem of the BMLUK, operated with the Umweltbundesamt |
| Endpoint | `GET https://mb.strahlenschutz.gv.at/api/current` |
| Format | JSON; dose rate in nSv/h (converted to µSv/h); probes without positions |
| Authentication | none |
| License | to be checked |
| Attribution | "Strahlung: Strahlenfrühwarnsystem, BMLUK / Umweltbundesamt" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | hourly |
| Volume | about 110 probes |
| Classification | by the radiation layer (B-13, `DoseRateAssessmentApplier`) |
| Quirks | positions from `data/stations.json` (EURDEP number placed on the INSPIRE sites of the Umweltbundesamt, `tools/stations/build.mjs`, ADR 0038) |
| Code | `UmweltbundesamtRequest`, `UmweltbundesamtParser`, mapped by `DoseRateReadingMapper` of the SDK |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
