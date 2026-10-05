# Profile: hubeau

| Section | Content |
|---|---|
| Provider | Hub'Eau, Office français de la biodiversité (OFB) and BRGM |
| Endpoint | `GET https://hubeau.eaufrance.fr/api/v2/hydrometrie/observations_tr`, observations of the last two hours in five latitude bands |
| Format | JSON; water levels in mm; times UTC |
| Authentication | none |
| License | Licence Ouverte Etalab |
| Attribution | "Pegel: Hub'Eau (OFB/BRGM, Licence Ouverte Etalab)" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 5 minutes |
| Volume | up to about 7,200 rows per band (2026-10-02); limit 20,000 |
| Classification | without flood stages; only the water level is shown (`WithoutStages`) |
| Quirks | some stations deliver late; the newest value per station is kept; station master data in `data/stations.json` (`tools/stations/build.mjs`, ADR 0038) |
| Code | `HubEauRequest`, `HubEauParser`, mapped by `WaterReadingMapper` of the SDK |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
