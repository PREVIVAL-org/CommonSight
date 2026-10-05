# Profile: alertswiss

| Section | Content |
|---|---|
| Provider | Bundesamt für Bevölkerungsschutz BABS, Alertswiss: the alerts of the federal government and the cantons on alert.swiss (also in the Alertswiss app) |
| Endpoint | `GET https://www.alert.swiss/content/alertswiss-internet/de/home/_jcr_content/polyalert.alertswiss_alerts.actual.json`: the JSON the website itself loads (also `fr`, `it`, `en`); no official interface |
| Format | JSON `{heartbeatAgeInMillis, renderTime, alerts: [...]}`; per alert identifier, title, event, severity (CAP, lower case), description, instructions, areas (polygons as `["lat", "lon"]` strings, circles with radius in km, canton codes), publisherName, links, flags `testAlert`, `technicalTestAlert`, `allClear`, `nationWide`; the field `sent` is a localized text, the time comes from `reference` (`sender,identifier,ISO time`). Texts of other languages are machine-translated (`gtransOrigin`), line breaks are `❘` |
| Authentication | none |
| License | to be checked (BABS); content of the federal administration |
| Attribution | "Warnungen: Alertswiss, Bundesamt für Bevölkerungsschutz BABS" |
| Terms of use | to be checked: the JSON is not offered as an interface. Fetched every 2 minutes like the website, one request per run |
| Update rate | as alerts are issued; alerts mostly stay for days (fire bans, forest fire danger, drought, landslides, events) |
| Volume | 10 to 20 current alerts, one response of about 100 KB |
| Quirks | test alerts and all-clear messages are skipped; there is no expiry: an alert is valid as long as it is listed. Circles become polygons with 24 corners; an alert for the whole country has no area. Alerts of the Principality of Liechtenstein (FL) are listed too and lie outside the Swiss regions. A change of the website can change the JSON without notice: an unreadable response fails the source, the last good outcome stays |
| Code | `AlertswissRequest`, `AlertswissParser` (`Record\AlertswissAlert`, `AlertAreas`), `AlertswissMapper` |
| History | added on 2026-10-04: civil protection alerts for Switzerland next to the weather warnings of MeteoSchweiz |
