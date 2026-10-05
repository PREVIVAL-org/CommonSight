# Profile: noaa-scales

| Section | Content |
|---|---|
| Provider | NOAA Space Weather Prediction Center (SWPC) |
| Endpoint | `GET https://services.swpc.noaa.gov/products/noaa-scales.json` |
| Format | JSON object per day offset ("-1" to "3") with the scales R, S and G; times UTC |
| Authentication | none |
| License | public domain (U.S. government work); to be confirmed in the profile check |
| Attribution | "Weltraumwetter: NOAA SWPC" (wording to be checked) |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 5 minutes |
| Volume | one small object |
| Quirks | a missing scale is reported as a deficit (`MissingNoaaScales`) |
| Code | `SwpcRequest`, `ScalesParser` (`Record\NoaaScale`), `ScaleMapper`, `MissingNoaaScales` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
