# Profile: open-meteo-pollen

| Section | Content |
|---|---|
| Provider | Open-Meteo.com, pollen forecast of the Copernicus Atmosphere Monitoring Service (CAMS European air quality) |
| Endpoint | `GET https://air-quality-api.open-meteo.com/v1/air-quality`, one batch query with all places of the scope (core places: cities.json, border-places.json), `current` = the six pollen types |
| Format | JSON, one object per place in the order of the query; grains per m³ for alder, birch, grass, mugwort, olive and ragweed; times UTC |
| Authentication | none |
| License | CC BY 4.0; CAMS data under the Copernicus licence |
| Attribution | "Pollenflug: Open-Meteo.com (CC BY 4.0) auf Basis von CAMS (Copernicus Atmosphere Monitoring Service)" |
| Terms of use | Free API for non-commercial use: fewer than 10,000 calls a day, 5,000 an hour, 600 a minute; every place of a batch query counts as a call (open-meteo.com/en/terms). The three Open-Meteo sources share it: weather every 30 min (6,048 calls a day for 126 places), air quality every 2 h (1,512), pollen every 3 h (1,008), together about 8,600 (termsMinIntervalSec). Approval for the use assumed (provider contacted) |
| Update rate | hourly values; the model covers Europe and the pollen season, outside of it the values are 0 |
| Volume | one response per scope with some dozen places |
| Quirks | a place missing from the response is rejected and makes the layer partial; a place whose six values are all empty is skipped (no pollen in the model, e.g. outside the season), so the layer can be empty without an error. No health classification: the strongest type and all concentrations are shown as model values |
| Code | `PollenRequest`, `PollenParser` (`Record\PollenValues`), `PollenMapper`, `PollenType` |
| History | added on 2026-10-03 with the layer `pollen` as the proof of layers as plugins (L7) |
