# Profile: geosphere-warnings

| Section | Content |
|---|---|
| Provider | GeoSphere Austria (formerly ZAMG) |
| Endpoint | `GET https://warnungen.zamg.at/wsapp/api/getWarnstatus`; per warning the text `GET https://warnungen.zamg.at/wsapp/api/getWarningsForCoords` for a point inside its area |
| Format | GeoJSON in the Austrian Lambert projection (EPSG:31287), municipality codes (GKZ); times as Unix timestamps |
| Authentication | none |
| License | CC BY 4.0 |
| Attribution | "Warnungen: GeoSphere Austria, CC BY 4.0; Gebietsgrundlage: Statistik Austria" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every minute |
| Volume | some dozen warnings; one detail request per new warning |
| Quirks | areas are projected to WGS84 (`AustriaLambertGeometry` of the SDK); state names from the region codes of the core; detail texts at most 30 per run, kept in the store of the plugin; invalid records make the layer partial (Q-W-AT-02) |
| Code | `GeoSphereStatusRequest`, `GeoSphereStatusParser` (`Record\WarnFeature`), `GeoSphereWarningMapper`, `GeoSphereWarnType`, `GeoSphereDetailSource` with parser, matcher and composer |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
