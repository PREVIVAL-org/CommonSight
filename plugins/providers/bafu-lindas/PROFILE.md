# Profile: bafu-lindas

| Section | Content |
|---|---|
| Provider | Bundesamt für Umwelt BAFU, published as Linked Data via LINDAS |
| Endpoint | `GET https://lindas.admin.ch/query` (SPARQL, `application/sparql-results+json`) |
| Format | SPARQL result bindings; water level in "m ü. M." or discharge |
| Authentication | none |
| License | to be checked (opendata.swiss terms) |
| Attribution | "Pegel: Bundesamt für Umwelt BAFU · LINDAS" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 10 minutes |
| Volume | about 230 stations |
| Classification | by the danger levels of the BAFU (B-12, `SwissWaterAssessor`) |
| Code | `LindasRequest`, `LindasParser` (`Record\HydroObservation`), `LindasMapper`, `SwissWaterAssessor` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
