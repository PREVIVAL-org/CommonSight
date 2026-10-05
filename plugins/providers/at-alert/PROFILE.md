# Profile: at-alert

| Section | Content |
|---|---|
| Provider | AT-Alert, the public warning system of Austria (cell broadcast, EU-Alert): sent by the interior ministry (BMI) and the warning centres of the states; RTR publishes every warning on warnungen.at-alert.at (§ 125 (4) TKG 2021) |
| Endpoint | `POST https://warnungen.at-alert.at/api/rpc/alert/list` with `{"json": {"regions": [], "alertLevels": ["AlertLevel1", "AlertLevel2", "AlertLevel3", "AlertLevel4", "Amber"], "search": "", "limit": 100, "offset": 0}}`: the data call of the website (oRPC); no official interface. Without `from`/`to` the list holds the current warnings; with them (YYYY-MM-DD) the archive. The CSV download of the website is `alert/downloadCsv` with the same filters |
| Format | JSON `{"json": {"totalCount", "alerts": [...]}}`; per warning `consolidation_identifier`, `alert_level`, `title`, `info_description` (plain text, German and English), `description` (HTML), `info_area_description`, `sender` (code of the warning centre, e.g. `LszB`), `sent`, `info_expires`, `begin_date`, `end_date` (ISO with offset), `geometries` (GeoJSON multipolygons, lon/lat), `matched_polygons` (states and districts with name) |
| Authentication | none |
| License | to be checked (RTR, BMI) |
| Attribution | "Warnungen: AT-Alert (BMI, Landeswarnzentralen), veröffentlicht von der RTR" |
| Terms of use | to be checked: the data call is not offered as an interface. Fetched every minute (the website itself every 30 seconds), one request per run |
| Update rate | as warnings are sent; most last minutes to hours |
| Volume | usually none; at the yearly Zivilschutz-Probealarm about 20 (one per warning centre) |
| Quirks | the levels `Test`, `Exercise` and `MonthlyTest` are not requested (the website hides them too). A test of the warning system sent at a real level is kept and shown as "Probealarm" with minor severity; it is recognised by its title or text (Probealarm, Probewarnung, Testalarm, TestAlert, Übungsalarm, Systemtest, Funktionstest, Testnachricht, "Amtlicher Test", or a title starting with "TEST"): all 18 messages around the Zivilschutz-Probealarm on 2026-10-02 and 2026-10-03 (the sirens test, system tests of the warning centres and the interior ministry) came at real levels, not at the test levels; the sirens test at `AlertLevel1`. The text repeats the title at its start, which is cut off, and the warning centres add an English version (paragraph, line by line or between asterisks), which is left out (`GermanText`). Severity: level 1 and 2 extreme, level 3 severe, level 4 and Amber (missing person) moderate. A list longer than the limit is reported as incomplete. A change of the website can change the call without notice: an unreadable response fails the source, the last good outcome stays |
| Code | `AtAlertRequest`, `AtAlertParser` (`Record\AtAlertWarning`), `AtAlertMapper` |
| History | added on 2026-10-04: civil protection warnings for Austria next to the weather warnings of GeoSphere. The former JSON call `api/filteredAlerts` (used until 2025, e.g. by github.com/fitforfire/at-alert-api) no longer exists |
