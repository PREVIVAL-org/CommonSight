# Profile: imgw

| Section | Content |
|---|---|
| Provider | Instytut Meteorologii i Gospodarki Wodnej – Państwowy Instytut Badawczy (IMGW-PIB) |
| Endpoint | `GET https://danepubliczne.imgw.pl/api/data/hydro/` |
| Format | JSON, numbers as text; measuring times in UTC without zone suffix (checked 2026-10-02 and 2026-10-03: at 20:28 UTC the newest of 913 stations read 20:00) |
| Authentication | none |
| License | CC BY 4.0 (https://danepubliczne.imgw.pl/regulations) |
| Attribution | "Pegel: Źródłem pochodzenia danych jest Instytut Meteorologii i Gospodarki Wodnej – Państwowy Instytut Badawczy, dane przetworzone (CC BY 4.0)" (the Polish sentence verbatim as the terms require; prefix and license added) |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 10 minutes |
| Volume | about 900 stations in one response |
| Classification | by the warning and alarm levels of IMGW-PIB (`ImgwStageAssessor`, an SDK `FloodStageAssessor`) |
| Code | `ImgwRequest`, `ImgwParser`, mapped by `WaterReadingMapper` of the SDK |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
