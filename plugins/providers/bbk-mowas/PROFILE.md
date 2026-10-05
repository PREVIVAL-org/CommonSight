# Profile: bbk-mowas

| Section | Content |
|---|---|
| Provider | Bundesamt für Bevölkerungsschutz und Katastrophenhilfe (BBK), the warnings of the NINA app: the modular warning system MoWaS, KATWARN, BIWAPP, police messages and the flood messages of the states (Länderübergreifendes Hochwasserportal). The weather warnings of the DWD, also in NINA, come from the source `dwd-warnings`. |
| Endpoint | `GET https://warnung.bund.de/api31/<feed>/mapData.json` for the feeds `mowas`, `katwarn`, `biwapp`, `police`, `lhp` (five requests per run, the same format); per message the area `GET https://warnung.bund.de/api31/warnings/<id>.geojson` |
| Format | JSON list of current messages; areas as GeoJSON |
| Authentication | none |
| License | to be checked (BBK terms of use) |
| Attribution | "Warnungen: BBK/NINA (MoWaS, KATWARN, BIWAPP, Polizei, Hochwasserportale)" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every minute |
| Volume | some dozen messages, most of them MoWaS (the other feeds were empty when they were added on 2026-10-04); one detail request per new message |
| Quirks | item ids carry the feed as prefix (`mowas:`, `katwarn:`, `biwapp:`, `police:`, `lhp:`); police messages and flood messages get categories of their own (`police`, `flood`, catalog.json), the rest is civil protection. A feed that fails makes the layer partial, the others still count. Areas are details: at most 30 requests per run, kept in the store of the plugin until the message expires; messages without area are reported (`WarningsWithoutGeometry`) |
| Code | `NinaFeed`, `MowasRequest`, `MowasParser` (`Record\MowasWarning`), `MowasMapper`, `MowasGeometryDetail` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5); KATWARN, BIWAPP, police and flood portals added on 2026-10-04 (their recordings are empty lists, the cases are in `tests/cases/`) |
