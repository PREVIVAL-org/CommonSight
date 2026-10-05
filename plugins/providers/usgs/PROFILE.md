# Profile: usgs

| Section | Content |
|---|---|
| Provider | U.S. Geological Survey, Earthquake Hazards Program |
| Endpoint | `GET https://earthquake.usgs.gov/fdsnws/event/1/query` by the bounds of each country and the border zone |
| Format | GeoJSON (FDSN) |
| Authentication | none |
| License | public domain (U.S. government work); to be confirmed in the profile check |
| Attribution | "Erdbeben: U.S. Geological Survey (USGS)" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every minute |
| Volume | few events per day in DACH |
| Quirks | USGS writes English only: the place is translated (`GermanPlace`), the title is "Erdbeben · <place>". |
| Code | `UsgsRequest`, `UsgsParser` (`Record\Quake`), `UsgsMapper`, `GermanPlace` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
