# Profile: dwd-warnings

| Section | Content |
|---|---|
| Provider | Deutscher Wetterdienst (DWD) |
| Endpoint | WFS `https://maps.dwd.de/geoserver/dwd/ows`, layer `Warnungen_Landkreise`, in pages (`DwdPaging`) |
| Format | GeoJSON; times UTC |
| Authentication | none |
| License | to be checked (DWD terms of use, GeoNutzV) |
| Attribution | "Warnungen: Deutscher Wetterdienst (DWD)" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every minute |
| Volume | up to 20 MB per page during severe weather (F-08) |
| Code | `DwdWarningRequest`, `DwdWarningParser` (`Record\DwdWarningFeature`), `DwdWarningMapper`, `DwdPaging` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
