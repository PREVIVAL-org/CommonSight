# Profile: autobahn

| Section | Content |
|---|---|
| Provider | Die Autobahn GmbH des Bundes |
| Endpoint | `GET https://verkehr.autobahn.de/o/autobahn/<road>/services/<service>` for `warning`, `closure` and `roadworks`, three requests per motorway (`AutobahnRequest::ROADS`, `SERVICES`); documentation: <https://autobahn.api.bund.dev/> |
| Selection | current items only (`future: false`); of the roadworks only `SHORT_TERM_ROADWORKS` (2026-10-04: 150 of 872 current ones; the long-term sites would cover the map) |
| Format | JSON, one list under the name of the service; coordinates "lat,lon" as text; kind as English codes (`display_type`, `abnormalTrafficType`), mapped to German words |
| Authentication | none |
| License | to be checked |
| Attribution | "Verkehr: Autobahn GmbH des Bundes" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every minute |
| Volume | 36 requests per run; warnings and closures small, roadworks up to 0.8 MB (A7; with all long-term sites, filtered by the parser) |
| Code | `AutobahnRequest`, `AutobahnParser` (`Record\RoadWarning`), `AutobahnMapper` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5); closures and short-term roadworks added on 2026-10-04 |
