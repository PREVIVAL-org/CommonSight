# Profile: rijkswaterstaat

| Section | Content |
|---|---|
| Provider | Rijkswaterstaat (Dutch Ministry of Infrastructure and Water Management) |
| Endpoint | `POST https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen` (DD-API 2.0) |
| Format | JSON; water levels in cm above NAP |
| Authentication | none |
| License | CC0 |
| Attribution | "Pegel: Rijkswaterstaat (CC0)" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 10 minutes |
| Volume | about 300 locations |
| Classification | without flood stages; reference NAP |
| Quirks | station master data in `data/stations.json` (`tools/stations/build.mjs`, ADR 0038) |
| Code | `RijkswaterstaatRequest`, `RijkswaterstaatParser`, mapped by `WaterReadingMapper` of the SDK |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
