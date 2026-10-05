# Profile: meteoalarm-ch

| Section | Content |
|---|---|
| Provider | MeteoSchweiz, distributed by MeteoAlarm (EUMETNET) |
| Endpoint | Atom/CAP feed of MeteoAlarm for Switzerland (`MeteoAlarmRequest`, feed "legacy"); per warning the full CAP message behind its `id` (`https://feeds.meteoalarm.org/api/v1/warnings/feeds-switzerland/...`) for the German texts |
| Format | XML Atom with CAP fields; polygons "lat,lon" pairs |
| Authentication | none |
| License | to be checked (MeteoAlarm terms of use) |
| Attribution | "Warnungen: MeteoSchweiz via MeteoAlarm" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every minute |
| Volume | a few dozen entries |
| Quirks | expired and cancelled entries are dropped; polygons are rotated and closed (Q-W-CH-03). The feed names event and title in English and summarises in the language of the region (Italian for Ticino); the German block of the CAP message (`de`, `de-CH`, …) replaces them (`GermanCapDetail`, at most 30 per run, kept until the warning expires); a warning without one keeps the feed's texts |
| Code | `MeteoAlarmRequest`, `MeteoAlarmParser` (`Record\CapEntry`), `MeteoAlarmMapper` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
