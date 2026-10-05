# Profile: paa

| Section | Content |
|---|---|
| Provider | Państwowa Agencja Atomistyki (PAA) |
| Endpoint | WFS `https://monitoring.paa.gov.pl/geoserver/ows` |
| Format | GeoJSON; local time Europe/Warsaw |
| Authentication | none |
| License | CC BY 3.0 PL |
| Attribution | "Strahlung: Państwowa Agencja Atomistyki (CC BY 3.0 PL)" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | hourly |
| Volume | about 40 stations in the border zone expected |
| Classification | by the radiation layer (B-13, `DoseRateAssessmentApplier`) |
| Code | `PaaRequest`, `PaaParser`, mapped by `DoseRateReadingMapper` of the SDK |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
