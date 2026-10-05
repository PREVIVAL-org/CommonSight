# Profile: oeamtc

| Section | Content |
|---|---|
| Provider | ÖAMTC |
| Endpoint | German GeoRSS feed of the ÖAMTC traffic service, `https://www.oeamtc.at/verkehrsservice/output/rss/oeamtc_verkehrsservice_oesterreich.xml` (`OeamtcRequest`; until 2026-10-04 its English twin `oeamtc_traffic_informations_austria.xml`, same notices and ids); overview of the feeds: https://www.oeamtc.at/feeds/ |
| Format | XML GeoRSS; `georss:point`/`georss:line` as "lat lon" |
| Authentication | none |
| License | to be checked |
| Attribution | "Verkehr: ÖAMTC" |
| Terms of use | to be checked (provider contacted, approval assumed) |
| Update rate | every 2 minutes |
| Volume | several hundred notices |
| Code | `OeamtcRequest`, `OeamtcParser` (`Record\TrafficEntry`), `OeamtcMapper` |
| History | moved into a plugin on 2026-10-03 (concept: sources as plugins, P5) |
