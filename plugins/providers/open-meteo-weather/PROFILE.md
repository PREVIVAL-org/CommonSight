# Profile: open-meteo-weather

| Section | Content |
|---|---|
| Provider | Open-Meteo.com (weather models of national weather services) |
| Endpoint | `GET https://api.open-meteo.com/v1/forecast`, one batch query with all places of the scope (core places: cities.json, border-places.json) |
| Format | JSON, one object per place in the order of the query; times UTC |
| Authentication | none |
| License | CC BY 4.0 |
| Attribution | "Wetter: Open-Meteo.com (CC BY 4.0)" with link to the licence page |
| Terms of use | Free API for non-commercial use: fewer than 10,000 calls a day, 5,000 an hour, 600 a minute; every place of a batch query counts as a call (open-meteo.com/en/terms). The three Open-Meteo sources share it: weather every 30 min (6,048 calls a day for 126 places), air quality every 2 h (1,512), pollen every 3 h (1,008), together about 8,600 (termsMinIntervalSec). Approval for the use assumed (provider contacted) |
| Update rate | every 15 minutes |
| Volume | one response per scope with some dozen places |
| Quirks | a place without temperature gets no item; rejected places make the layer partial |
| Code | `OpenMeteoRequest`, `OpenMeteoParser` (`Record\CurrentValues`), `WeatherMapper`, `WeatherCodeSummary` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
