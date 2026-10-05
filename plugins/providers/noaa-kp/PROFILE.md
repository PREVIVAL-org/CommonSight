# Profile: noaa-kp

| Section | Content |
|---|---|
| Provider | NOAA Space Weather Prediction Center (SWPC) |
| Endpoint | `GET https://services.swpc.noaa.gov/products/noaa-planetary-k-index.json` |
| Format | JSON table (first row the header); times UTC |
| Authentication | none |
| License | public domain (U.S. government work); to be confirmed in the profile check |
| Attribution | "Weltraumwetter: NOAA SWPC" (wording to be checked) |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 5 minutes |
| Volume | one small table |
| Code | `SwpcRequest`, `KpParser` (`Record\KpSeries`), `KpMapper` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
