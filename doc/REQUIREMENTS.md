# Requirements: CommonSight

As of: 2026-10-03 (sources as plugins: F-01, F-02, F-04, F-05, F-17, F-19, A-02, Q-TR-CH-01, U-51 adjusted; layers as plugins: F-01; new layer pollen: 3.10)
This document describes the **functional scope** of CommonSight.

---

## 0. Reading notes

### 0.1 Scope

- This document describes **what** CommonSight must do functionally. The **how** (fetchers, snapshots, MapLibre/PMTiles, custom element, React as UI layer) is described in `ARCHITECTURE.md` and is only picked up here where it changes requirements.
- The domain logic that had to be checked explicitly (marked [approved]) was approved on 2026-09-28.
- General requirements on the architecture are in section 12.

### 0.2 Markings

| Marking | Meaning |
|---|---|
| **MUST** | Part of the functional scope; without it CommonSight cannot be accepted |
| **SHOULD** | clearly sensible functionally; deviation only with justification |
| **MAY** | optional; currently no requirement has this priority |
| **[approved]** | Domain logic that had to be checked functionally in particular; approved functionally on 2026-09-28 and to be implemented as described here |

Requirement IDs: `D-` data model, `Q-` sources/layers, `B-` assessment, `F-` fetcher, `A-` delivery, `U-` user interface, `K-` map, `T-` theming, `I-` i18n, `E-` embedding/host page, `R-` legal/attribution, `N-` non-functional.

### 0.3 Terms

| Term | Meaning |
|---|---|
| **Country** | DE, AT or CH. Liechtenstein is only included in the map extent, not as a separate country with data. |
| **Region** | State (DE, AT) or canton (CH); DE 16, AT 9, CH 26 |
| **Layer** | a functional data layer, each a plugin package (F-01): `warnings`, `weather`, `air`, `pollen`, `water`, `radiation`, `space`, `nature`, `traffic`, plus `news` (10 so far) |
| **Snapshot** | JSON file of a layer written by the fetcher (for one country, the border zone or country-independent) |
| **Item** | an element of a snapshot (warning, station, place, notice, index) |
| **Assessment** | classification of a measured value (`normal`, `elevated`, `high`, `unknown`) |

---

## 1. Functional overview

CommonSight is a **personal, supplementary situation picture** for Germany, Austria and Switzerland. It bundles official warnings, measured and model values, events and news from public sources, shows them on a map, in lists and in key figures, and makes **data status, source and gaps** visible at all times.

Guiding principles (**MUST**):

1. **Data gaps are no all-clear.** Missing, stale or unclassifiable data are marked as such, never as "everything is fine".
2. **Official sources take precedence.** Every item links to its original source; own colors and thresholds are marked as presentation, not as an official level.
3. **Transparency about the data status.** Fetch time and source date are visible per layer and per item.

### 1.1 Matrix layer × country

| Layer | Display name | DE | AT | CH |
|---|---|---|---|---|
| `warnings` | "Amtliche Warnungen" (official warnings) | DWD (WFS) + BBK/NINA (MoWaS, KATWARN, BIWAPP, police, flood portals) | GeoSphere Austria + AT-Alert | MeteoAlarm (MeteoSchweiz) + Alertswiss (BABS) |
| `weather` | "Wetter" (weather) | Open-Meteo | Open-Meteo | Open-Meteo |
| `air` | "Luftqualität" (air quality) | Open-Meteo/CAMS | Open-Meteo/CAMS | Open-Meteo/CAMS |
| `pollen` | "Pollenflug" (pollen) | Open-Meteo/CAMS | Open-Meteo/CAMS | Open-Meteo/CAMS |
| `water` | "Wasserpegel" (water gauges) | PEGELONLINE (WSV) | eHYD | BAFU via LINDAS (SPARQL) |
| `radiation` | "Strahlung" (radiation) | BfS ODL (IMIS) | Strahlenfrühwarnsystem (BMLUK/Umweltbundesamt, since 2026-10-02) | EURDEP via BfS/IMIS |
| `space` | "Weltraumwetter" (space weather) | NOAA SWPC (country-independent) | <- | <- |
| `nature` | "Naturgefahren (Erdbeben)" (natural hazards (earthquakes)) | USGS | USGS | USGS |
| `traffic` | "Verkehr" (traffic) | Autobahn GmbH | ÖAMTC (GeoRSS) | **no data**, status "Zugang benötigt" (access required) |
| `news` | "Nachrichten" (news) | tagesschau, ORF, SRF (country-independent, order per country) | <- | <- |

---

## 2. Data model (contract fetcher <-> frontend)

The contract is defined **as JSON Schema**. The TypeScript types of the frontend are generated from it, and the tests of the PHP fetcher check every generated snapshot against the schema.

### 2.1 Snapshot of a layer

| ID | Requirement | Prio |
|---|---|---|
| D-01 | Every snapshot contains: `schema` (version), `layer`, `scope` (country, `border` or `global` for country-independent layers), `status`, `source` (display name), `sourceUrl`, `generatedAt` (time of the assembly), `updatedAt` (most recent source date or `null`), `note` (notice on the layer), `coverage` (what the sources cover), `issues`, `items`, `stats` (layer-specific) | MUST |
| D-02 | `status` is one of: `ok`, `partial` (partial outage, available data are shown), `error` (no usable data), `setup` (data access not set up) | MUST |
| D-03 | With `partial` the snapshot names the cause(s) as **codes with parameters** (e.g. "3 notices without detail text"), not as finished text | MUST |
| D-04 | `note` and all fixed notice texts are delivered as **keys** and translated in the client. They appear **once per layer**, never repeated in every item | MUST |
| D-05 | In addition to the last successful state, the snapshot records when an update was last attempted and whether it failed, so that the frontend can show "last good state from ..., update failed" | SHOULD |
| D-06 | The data model is versioned (field or file name), so that frontend and snapshots do not drift apart unnoticed after an update | SHOULD |

### 2.2 Item

| ID | Requirement | Prio |
|---|---|---|
| D-10 | Common fields of every item: `kind` (kind, D-11), `id` (stable, unique per layer), `title`, `url` (original source, only `http`/`https`), `source` (short name, if different from the layer default), `time` (measurement or report time), location (`lat`/`lon` and/or `geometry` as GeoJSON), `regionIds` | MUST |
| D-11 | Every item has exactly one **kind** (`kind`) with its own defined fields. Mandatory fields of a kind are really mandatory; fields of other kinds do not occur. Kinds and fields see table below | MUST |
| D-16 | **Additional values** (e.g. wind, humidity, PM2.5, discharge, water temperature, depth) are delivered structured as a list `facts`: label (key), value, unit. They do not appear as a finished sentence in a description | MUST |
| D-17 | Peculiarities of individual sources (e.g. eHYD code, BAFU level, PEGELONLINE status) are expressed through the fields of the kind (`sourceValue` of the assessment, `facts`), not through source-specific fields | MUST |

**Kinds of items**

| Kind (`kind`) | Own fields | Layers |
|---|---|---|
| `warning` | warning type, level/`severity`, area (`area`), `onset`, `expires`, text in sections (description, weather situation, impacts, recommendations, update; where available), category | warnings |
| `measurement` | `value`, `unit`, reference of the value (e.g. local gauge zero, height above sea level, averaging period), measured quantity (e.g. water level, discharge, ODL), `assessment`, `facts` | water, radiation |
| `modelValue` | `value`, `unit`, short text (e.g. weather text, AQI level), `facts` | weather, air |
| `earthquake` | magnitude, depth, location description | nature |
| `trafficNotice` | route/road, type of notice, start, description | traffic |
| `index` | `value`, scale (minimum, maximum, label, e.g. Kp 0-9, G 0-5), history (optional) | space |
| `news` | topic (`category`), feed name | news |
| D-12 | All timestamps are **absolute points in time in UTC**, stored and transmitted as ISO 8601 with `Z` and seconds (e.g. `2026-09-28T10:15:00Z`). No local time, no value with offset (`12:15:00+02:00`), no point in time without zone reference. Storage is thus independent of server, source and user time zone | MUST |
| D-18 | Time values of the sources are converted to UTC using their **actual time zone**, including summer and winter time. If a source delivers local time without a zone (e.g. eHYD: Europe/Vienna), the source's zone is applied via the time zone database, not a fixed offset. In the hour that occurs twice when switching to winter time, the earlier option applies; the zone of each source is stated in its source profile (F-19) | MUST |
| D-13 | Coordinates in WGS84, order as in GeoJSON (lon, lat) in `geometry`; `lat`/`lon` as separate numbers | MUST |
| D-14 | Texts from the sources (warning texts, headlines) remain in the original language and are delivered as plain text: HTML removed, entities resolved, whitespace normalized | MUST |
| D-15 | The item marks the language of its source texts if it is known | SHOULD |

### 2.3 Assessment (`assessment`)

| ID | Requirement | Prio |
|---|---|---|
| D-20 | Fields: `level` (`normal`, `elevated`, `high`, `unknown`), `labelKey` + parameters, `basisKey` + parameters, `origin` (`source` = classification by the source, `display` = own display threshold), `validUntil` (UTC) | MUST |
| D-21 | The original level of the source is preserved (e.g. BAFU level 3, eHYD code 410), even if it is merged for the color | MUST |

---

## 3. Sources and layers

For all layers:

| ID | Requirement | Prio |
|---|---|---|
| Q-00 | Expired notices (`expires` ≤ now) and all-clears/cancellations (`Cancel`) are **not** shown | MUST |
| Q-01 | If one of several sub-sources of a layer fails, the layer is delivered with `partial` and the available data; only if all fail is the layer considered failed | MUST |
| Q-02 | If a source delivers no usable items although some are expected (measurement and model layers), the fetch is considered failed; the last good state remains (F-05) | MUST |
| Q-03 | Duplicate items (same `id`) are merged | MUST |

### 3.1 `warnings` - official warnings

**Germany**

| ID | Requirement | Prio |
|---|---|---|
| Q-W-DE-01 | **DWD weather warnings** via the WFS `dwd:Warnungen_Landkreise` (GeoJSON). Fetch **all** warnings completely (paging), otherwise `partial` | MUST |
| Q-W-DE-02 | Mapping DWD to a warning (kind `warning`): `title` = `HEADLINE`, area (`area`) = `AREADESC`, sections description = `DESCRIPTION` and recommendations = `INSTRUCTION`, `url` = `WEB` (fallback `https://www.dwd.de/warnungen`), `time` = `SENT`, `onset` = `ONSET`, `expires` = `EXPIRES`, `severity` = `SEVERITY`, `geometry`, category "Unwetter" (severe weather) | MUST |
| Q-W-DE-03 | **BBK/NINA** via `https://warnung.bund.de/api31/<feed>/mapData.json` for the feeds `mowas`, `katwarn`, `biwapp`, `police` and `lhp` (flood portals; the `dwd` feed of NINA is not read, Q-W-DE-01 covers it); police and flood notices get the categories "Polizei" and "Hochwasser"; warning area per notice via `.../api31/warnings/{id}.geojson` | MUST |
| Q-W-DE-04 | Mapping BBK: `title` = `i18nTitle.de` (fallback "Warnmeldung"), `url` = `https://warnung.bund.de/meldung/{id}`, `time` = `startDate`, `expires` = `expiresDate`, `severity`, category "Bevölkerungsschutz" (civil protection), source "BBK / NINA · MoWaS" | MUST |
| Q-W-DE-05 | Warning areas for all active MoWaS notices, reloading **only changed** notices (cache per notice ID) | SHOULD |
| Q-W-DE-06 | Notice: DWD and NINA notices are shown together; expired notices and all-clears are hidden | MUST |

**Austria** (GeoSphere Austria, AT-Alert)

| ID | Requirement | Prio |
|---|---|---|
| Q-W-AT-01 | Warning status via `https://warnungen.zamg.at/wsapp/api/getWarnstatus` (FeatureCollection) | MUST |
| Q-W-AT-02 | Skipped: expired warnings (`end` ≤ now) and warning level 0. **Counted as invalid** and skipped: records without valid `start`/`end`, with `end` ≤ `start`, unknown `wtype` (1 to 7) or `wlevel` (1 to 3) or empty `warnid`. Only invalid and no valid records -> fetch failed | MUST |
| Q-W-AT-03 | Warning types: 1 "Wind", 2 "Regen" (rain), 3 "Schnee" (snow), 4 "Glatteis" (black ice), 5 "Gewitter" (thunderstorm), 6 "Hitze" (heat), 7 "Kälte" (cold). Levels: 1 "Gelb" (yellow, `Moderate`), 2 "Orange" (`Severe`), 3 "Rot" (red, `Extreme`). Title: "{Typ}warnung · {Stufe}" | MUST |
| Q-W-AT-04 | Warning areas are in **MGI / Austria Lambert (EPSG:31287)** and are converted to WGS84, including a 7-parameter Helmert datum transformation (parameters matched to the GeoSphere map). Coordinates outside 9-18° E / 46-50° N are considered errors. Open or degenerate rings are discarded. **The conversion gets tests with reference points** | MUST [approved] |
| Q-W-AT-05 | If there is no usable area, the warning remains in the list without an area; the layer becomes `partial` with a count | MUST |
| Q-W-AT-06 | Region assignment via the municipality codes (`gemeinden`, five digits): first digit = state -> `regionIds` `AT-1` ... `AT-9`; area (`area`) = names of the affected states | MUST |
| Q-W-AT-07 | **German detail text** per warning via `getWarningsForCoords?lon=...&lat=...&lang=de` at a point **inside** the warning area (scanline method, holes taken into account, widest inner interval). Only the detail notice that matches **exactly** is taken over: same type, same level, same start and same end, and for `warnid` in the format `w{warnid}c{chgid}v{verlaufid}` also these three parts. This way no foreign warning of a neighboring municipality can slip in | MUST [approved] |
| Q-W-AT-08 | Detail text as **sections** of the warning (D-11): description = `text`, weather situation = `meteotext`, impacts = `auswirkungen`, recommendations = `empfehlungen`, update = `updategrund`, each only if present; `time` = `create` | MUST |
| Q-W-AT-09 | If the detail text is missing, the warning remains visible with type, level and area, with the notice to open the complete notice at GeoSphere; the layer becomes `partial` with a count | MUST |
| Q-W-AT-10 | The detail query needs one HTTP request **per warning**: cache detail texts per warning ID and only query for new or changed warnings; upper limit per run, the rest in the next run | MUST |
| Q-W-AT-11 | Sorting: highest level first, then earliest start | MUST |
| Q-W-AT-12 | Notice contains: official warnings from GeoSphere, German original texts, current and upcoming warnings, **valid for the permanent settlement area, not for high alpine locations**, the warnings of AT-Alert with test alarms marked as such, license CC BY 4.0, area basis Statistik Austria | MUST |
| Q-W-AT-14 | **AT-Alert** via the data call of warnungen.at-alert.at (`POST /api/rpc/alert/list`, no official interface), every minute, levels `AlertLevel1` to `AlertLevel4` and `Amber` (not the test levels `Test`, `Exercise`, `MonthlyTest`). Severity: levels 1 and 2 extreme, 3 severe, 4 and Amber moderate; hazard = name of the level; category civil protection; area = states and districts the warning was sent to, the area as given; the English version the warning centres add to the text is left out | MUST |
| Q-W-AT-15 | A test of the warning system sent at a real level (Zivilschutz-Probealarm) is recognised by its title or text and shown as "Probealarm" with minor severity | MUST |
| Q-W-AT-13 | `id` = `geosphere:{warnid}:{start}:{end}`; `onset` = start; `expires` = end | MUST |

**Switzerland** (MeteoAlarm, Alertswiss)

| ID | Requirement | Prio |
|---|---|---|
| Q-W-CH-01 | Atom feed `https://feeds.meteoalarm.org/feeds/meteoalarm-legacy-atom-switzerland` (CAP fields). Source "MeteoSchweiz · MeteoAlarm" | MUST |
| Q-W-CH-02 | Mapping: `id` = `id` (fallback hash), `title` = `headline`/`title`, section description = `description`/`summary`; the German block (`language` de…) of the full CAP message behind the `id` replaces headline, event, description and adds the instruction (the feed names them in English or in the language of the region), fetched once per warning and version; `url` = link `rel="alternate"` (fallback `https://www.naturgefahren.ch/`), `time` = `updated`/`published`/`sent`, `expires`, `severity`, area (`area`) = `areaDesc`, category "Unwetter" (severe weather); `updatedAt` = `updated` of the feed | MUST |
| Q-W-CH-03 | `polygon` contains "lat,lon" pairs, which are swapped into GeoJSON (lon, lat) and completed to a closed ring; fewer than 3 points -> no area, layer `partial` | MUST |
| Q-W-CH-04 | Notice: official weather warnings via MeteoAlarm and the civil protection notices of Alertswiss are shown together; test notices are hidden | MUST |
| Q-W-CH-05 | **Alertswiss (BABS)** via the JSON the website loads (`https://www.alert.swiss/content/alertswiss-internet/de/home/_jcr_content/polyalert.alertswiss_alerts.actual.json`, no official interface), every 2 minutes. Test alerts (`testAlert`, `technicalTestAlert`) and all-clears are skipped; a notice is valid as long as the feed lists it | MUST |
| Q-W-CH-06 | Mapping Alertswiss: `title` = title (fallback event), hazard = event, source "Alertswiss · <canton>", `time` = time of the CAP `reference`, sections description and instructions (`❘` as line break), `url` = first link (fallback `https://www.alert.swiss/`), category civil protection; areas from polygons ("lat","lon") and circles (radius in km, as a 24-corner polygon) | MUST |

**All countries**

| ID | Requirement | Prio |
|---|---|---|
| Q-W-01 | Sorting by severity: `Extreme`, `Severe`, `Moderate`, `Minor`, `Unknown` (AT additionally according to Q-W-AT-11) | MUST |

### 3.2 `weather` - weather (model values)

| ID | Requirement | Prio |
|---|---|---|
| Q-WE-01 | Open-Meteo Forecast, current values `temperature_2m`, `relative_humidity_2m`, `weather_code`, `wind_speed_10m`, `precipitation`, for a **fixed list of places**, at least one per state or canton (Appendix A); one combined request per country | MUST |
| Q-WE-02 | Item per place: `title` = place name, `lat`, `lon`, `value` = temperature (°C), short text = weather text from `weather_code`, `regionIds` = region of the place, `time`, additional values (`facts`, D-16) wind (km/h), humidity (%), precipitation (mm); kind `modelValue`, whose box carries the notice "Modellwert, keine Messung" (model value, not a measurement) | MUST |
| Q-WE-03 | Weather text from WMO code: 0 "Klar" (clear), 1-3 "Bewölkt" (cloudy), up to 48 "Nebel" (fog), up to 67 "Regen" (rain), up to 77 "Schneefall" (snowfall), up to 82 "Regenschauer" (rain showers), up to 86 "Schneeschauer" (snow showers), above that "Gewitter" (thunderstorm); missing "Wetterdaten" (weather data) | MUST |
| Q-WE-04 | Value missing for individual places -> `partial` | MUST |
| Q-WE-05 | Notice: model values for N selected places, at least one model point per state/canton, point values, no regional averages or station measurements; CC BY 4.0; free interface for non-commercial use | MUST |

### 3.3 `air` - air quality (model values)

| ID | Requirement | Prio |
|---|---|---|
| Q-AI-01 | Open-Meteo Air Quality API, current values `european_aqi`, `pm2_5`, `pm10`, for the same places as `weather` | MUST |
| Q-AI-02 | Kind `modelValue`: `value` = EU AQI, unit "EU-AQI", short text = classification (Q-AI-03); additional values PM2.5 and PM10 (µg/m³); source "CAMS · Open-Meteo" | MUST |
| Q-AI-03 | Classification: ≤ 20 "Gut" (good), ≤ 40 "Ausreichend" (fair), ≤ 60 "Mäßig" (moderate), ≤ 80 "Schlecht" (poor), ≤ 100 "Sehr schlecht" (very poor), above that "Extrem schlecht" (extremely poor) | MUST [approved] |
| Q-AI-04 | Notice and `partial` rule as for `weather` | MUST |

### 3.4 `water` - water gauges

**Germany** (WSV · PEGELONLINE)

| ID | Requirement | Prio |
|---|---|---|
| Q-WA-DE-01 | `stations.json?includeTimeseries=true&includeCurrentMeasurement=true`; per station the time series `W` (water level) with current measured value and coordinates | MUST |
| Q-WA-DE-02 | The response (measured at around 0.75 MB) contains all time series of all stations: **only the time series `W` and the required fields** are taken over | MUST |
| Q-WA-DE-03 | Item: `title` = station name · water body, `value` + `unit` (cm), `time`, `url` = `https://www.pegelonline.wsv.de/gast/stammdaten?pegelnr={Nummer}`, kind `measurement`, measured quantity water level, reference "über lokalem Pegelnullpunkt" (above local gauge zero), assessment according to B-10 | MUST |
| Q-WA-DE-04 | Notice per layer: water level above local gauge zero, unchecked raw data, no height value comparable across countries | MUST |

**Austria** (Hydrographie Österreich · eHYD)

| ID | Requirement | Prio |
|---|---|---|
| Q-WA-AT-01 | `https://ehyd.gv.at/services/PegelAktuell/json` (FeatureCollection) | MUST |
| Q-WA-AT-02 | Item: `id` = `hzbnr`, `title` = measuring point · water body, `value` from `wert` (decimal comma), `unit` = `einheit`, `time` = `zp` in **Europe/Vienna** converted to UTC, `url` = `internet` (fallback eHYD), kind `measurement`, measured quantity discharge for parameter `Q`, otherwise water level (reference "über lokalem Pegelnullpunkt"); assessment according to B-11 from `gesamtcode` | MUST |
| Q-WA-AT-03 | Notice: current, unchecked data; water level and discharge are different measured quantities; field `hd` (hydrographic service) per station | MUST |

**Switzerland** (BAFU · LINDAS)

| ID | Requirement | Prio |
|---|---|---|
| Q-WA-CH-01 | SPARQL query to `https://lindas.admin.ch/query`, graph `https://lindas.admin.ch/foen/hydro`: station (ID, name, water body, WKT point), measurement time, water level, discharge, water temperature, danger level. Complete, only the **most recent** observation per station | MUST |
| Q-WA-CH-02 | `value` = water level (m a.s.l.) or, if not present, discharge (m³/s); without either, no item. Kind `measurement`, measured quantity water level (reference "Meter über Meer", meters above sea level) or discharge. Additional values (`facts`): discharge, water temperature, where available; the BAFU danger level is the original level in the assessment | MUST |
| Q-WA-CH-03 | Notice: water level in meters above sea level, **do not compare with gauges above local zero**; unchecked BAFU measured values via LINDAS; `url` = `https://www.hydrodaten.admin.ch/de/aktuelle-lage` | MUST |
| Q-WA-CH-04 | Assessment according to B-12 from the danger level | MUST |

**All countries**

| ID | Requirement | Prio |
|---|---|---|
| Q-WA-01 | Notice: orange/red follow station-specific WSV/eHYD/BAFU classifications; gray = assessment missing or measured value older than 6 hours; the colors do not replace an official flood warning | MUST |

### 3.5 `radiation` - radiation (gamma ambient dose rate)

| ID | Requirement | Prio |
|---|---|---|
| Q-RA-01 | BfS/IMIS WFS `https://www.imis.bfs.de/ogc/opendata/ows`: DE `opendata:odlinfo_odl_1h_latest`, CH `opendata:eurdep_latestValue` filtered on station ID with country prefix and `analyzed_range_in_h=6`; AT from the Strahlenfrühwarnsystem (BMLUK/Umweltbundesamt, since 2026-10-02, see 1.1) | MUST |
| Q-RA-02 | Only stations with `site_status = 1`, a valid, non-negative value and coordinates | MUST |
| Q-RA-03 | Item: `id`, `title` = station name, `value` + `unit`, `time` = `end_measure`, kind `measurement`, measured quantity ODL, reference = averaging period (`duration`), `url` = country-specific info page (Appendix A), assessment according to B-13 | MUST |
| Q-RA-04 | Notice (once per layer): gamma ambient dose rate; natural radiation depends on location and weather; colors are **display thresholds, not official alarm levels**; rain and local peculiarities can cause elevated values; gray = no current assessment | MUST |
| Q-RA-05 | `stats` contains the color scale: threshold "elevated", threshold "high", unit µSv/h, maximum age 12 h, origin `display` | MUST |
| Q-RA-06 | Source: DE "BfS · ODL", AT "Umweltbundesamt · Strahlenfrühwarnsystem", CH "EURDEP · BfS/IMIS" | MUST |

### 3.6 `space` - space weather

| ID | Requirement | Prio |
|---|---|---|
| Q-SP-01 | NOAA SWPC: planetary Kp index (`noaa-planetary-k-index.json`) and NOAA scales (`noaa-scales.json`) | MUST |
| Q-SP-02 | **Country-independent**: one snapshot for all countries | MUST |
| Q-SP-03 | Items of kind `index`: last Kp value (scale 0-9, time, history, description "estimated planetary geomagnetic activity index 0-9, 3-hour intervals, no local measurement") and per scale G (geomagnetic storm), R (shortwave radio blackouts), S (solar radiation storm) with level 0-5; for S the notice that particles in space are meant, not the gamma measuring stations on the ground | MUST |
| Q-SP-04 | `stats`: current Kp, **history of the last 16 Kp values**, G, R, S | MUST |
| Q-SP-05 | One of the two sub-sources or a scale missing -> `partial` | MUST |
| Q-SP-06 | Notice: global geomagnetic activity, possible shortwave impairments, **no guarantee** for local radio, mobile network or GNSS availability | MUST |

### 3.7 `nature` - natural hazards (earthquakes)

| ID | Requirement | Prio |
|---|---|---|
| Q-NA-01 | USGS FDSN Event API (GeoJSON), earthquakes of the **last 7 days** in the bounding rectangle of the country borders plus a 0.5° margin, newest first, at most 150 | MUST |
| Q-NA-02 | Item: `title` "Erdbeben · <place>", kind `earthquake`: magnitude, depth (km), location description (USGS `place` in German: directions, "of" and the countries around DACH translated, e.g. "0 km NNW von Baumkirchen, Österreich"), `lat`, `lon`, `time`, `url`; `updatedAt` = `metadata.generated` | MUST |
| Q-NA-03 | Notice: country surroundings including border areas, no complete coverage of small local quakes; for further natural hazards refer to weather warnings, gauges and the linked avalanche and hazard portals | MUST |
| Q-NA-04 | An empty result is **valid** (no quakes), not an error | MUST |

### 3.8 `traffic` - traffic

| ID | Requirement | Prio |
|---|---|---|
| Q-TR-DE-01 | Autobahn API `https://verkehr.autobahn.de/o/autobahn/{Autobahn}/services/warning` for the motorways A1-A10, A81, A93 | MUST [approved; selection] |
| Q-TR-DE-02 | Item: `id` = `identifier`, kind `trafficNotice`: `title` = title, route = subtitle, description from the description lines, start = `startTimestamp`, `lat`/`lon` from `point` ("lat,lon"), category "Verkehr" (traffic), source "Autobahn GmbH" | MUST |
| Q-TR-DE-03 | Individual motorways not reachable -> `partial`; all not reachable -> failure. Notice: only selected motorways, no complete traffic or infrastructure situation | MUST |
| Q-TR-AT-01 | ÖAMTC GeoRSS, German feed `https://www.oeamtc.at/verkehrsservice/output/rss/oeamtc_verkehrsservice_oesterreich.xml` | MUST |
| Q-TR-AT-02 | Item: `id` = `guid` (fallback hash), kind `trafficNotice`: `title` (fallback "Verkehrsmeldung · ÖAMTC"), description = `description`, `url` = `link`, `time` = `pubDate`/`date`, position from `georss:point` or `georss:line` (pairs "lat lon"); with more than one point, line as GeoJSON `LineString`; `updatedAt` = `lastBuildDate`/`pubDate` of the channel | MUST |
| Q-TR-AT-03 | Notice: current and planned notices (German feed of the ÖAMTC); only notices with coordinates appear on the map; no statement about power or mobile networks | MUST |
| Q-TR-CH-01 | Until a plugin with its access data exists (ASTRA · opentransportdata.swiss, API key and TMC location table), no data; snapshot with status `setup` and a notice that a data access must be set up for Swiss real-time traffic data; reference to TCS traffic info | MUST |

### 3.9 `news` - news

| ID | Requirement | Prio |
|---|---|---|
| Q-NE-01 | RSS/Atom: ORF.at (`https://rss.orf.at/news.xml`), tagesschau.de (`.../alle-meldungen-100~rss2.xml`), SRF News (`https://www.srf.ch/news/bnf/rss/1646`) | MUST |
| Q-NE-02 | **Fetch country-independently** (one fetch per feed). The order/weighting per country (AT: ORF first, CH: SRF, DE: tagesschau) is produced in the client or as a view per country | MUST |
| Q-NE-03 | Only notices of the **last 72 hours**, with a valid URL and one of the categories from Q-NE-04; at most 45 notices, newest first; duplicate = same URL | MUST |
| Q-NE-04 | Topic assignment by keyword in title and description (case-insensitive, order of checking = precedence): **"Infrastruktur"** (infrastructure: Sabotage, Cyberangriff, Hackerangriff, Stromausfall, Blackout, Netzausfall, Versorgungsausfall, Explosion), **"Unwetter"** (severe weather: Unwetter, Starkregen, Hochwasser, Überschwemm..., Überflut..., Sturm, Hurrikan, Orkan, Taifun, Tornado, Lawine, Erdbeben, Waldbrand, Dürre, Trockenperiode, Hitze), **"Konflikte"** (conflicts: Krieg, Ukrain..., Russland, russisch, Rakete, Drohnen, Angriff, Israel, Iran, Gaza, NATO, Terror, Konflikt, Waffen). Notices without a category are dropped | MUST [approved] |
| Q-NE-05 | Item: `id` (`guid`/`id`, fallback URL), `title`, `url`, `source` (feed name), `time`, `category` | MUST |
| Q-NE-06 | Notice: headlines automatically filtered by topic; international notices remain visible in every country view; **no guarantee of completeness or independent confirmation of breaking news**; if individual feeds fail, `partial` | MUST |

---

### 3.10 `pollen` - pollen (model values)

| ID | Requirement | Prio |
|---|---|---|
| Q-PO-01 | Open-Meteo Air Quality API, current values `alder_pollen`, `birch_pollen`, `grass_pollen`, `mugwort_pollen`, `olive_pollen`, `ragweed_pollen` (CAMS, grains/m³), for the same places as `weather`, in the countries and the border zone | MUST |
| Q-PO-02 | Kind `modelValue`: `value` = concentration of the strongest type, unit "Pollen/m³", short text = the strongest type ("vor allem Beifuß"), or "keine Pollen im Modell" when all are 0; every type as an additional value; source "CAMS · Open-Meteo" | MUST |
| Q-PO-03 | No health classification: the model values are shown as they are; the notice says so and that they are point values of a model, not measurements | MUST |
| Q-PO-04 | `partial` rule as for `weather`; inactive by default, list view measurements | MUST |

## 4. Assessment logic (classification of measured values)

Principle: these are **display categories, not an official cross-country warning scale**. The logic is a pure function without side effects and gets unit tests.

| ID | Requirement | Prio |
|---|---|---|
| B-01 | **Freshness:** If the measurement time is missing or lies more than 15 min in the future -> `unknown` ("data status missing or implausible"). `validUntil` = measurement time + maximum age. If that is exceeded -> `unknown` with "measured value older than N hours. Last assessment: ..." | MUST [approved] |
| B-02 | Maximum age: **gauges 6 h, radiation 12 h** | MUST [approved] |
| B-03 | The **frontend checks `validUntil` again** against the current time (snapshots can age). Expired -> gray with "Datenstand veraltet" (data status stale) and the last assessment. If there is no valid assessment -> "Keine aktuelle Einordnung" (no current assessment) | MUST |
| B-10 | **Gauges DE** (PEGELONLINE `stateMnwMhw`, `stateNswHsw`): status `commented` or `out-dated` in either -> `unknown` (disturbed/stale). HSW `high` -> `high` ("HSW reached/exceeded", navigation limit, not a general flooding limit). Otherwise MHW `high` -> `elevated` ("MHW reached/exceeded", not an official flood warning). MHW `normal`/`low` -> `normal` ("below MHW" or "low water range"). Only HSW `normal` -> `normal` ("below HSW · MHW unknown"). Otherwise `unknown`. HSW and MHW are evaluated **independently**, because HSW can be below MHW | MUST [approved] |
| B-11 | **Gauges AT** (eHYD `gesamtcode`, three digits): hundreds = class, tens = trend, units ≠ 0 = limited currency (> 24 h) -> `unknown`. Classes 1-2 only with trend 3, classes 3-6 only with trend 0-2, otherwise `unknown` (name the code). 1 low water, 2 mean water, 3 elevated flow below HQ1 -> `normal`; 4 flood level 1 (HQ1 to below HQ10) -> `elevated`; 5 level 2 (HQ10 to below HQ30) and 6 level 3 (from HQ30) -> `high` | MUST [approved] |
| B-12 | **Gauges CH** (BAFU danger level 1-5): 1 -> `normal`, 2-3 -> `elevated`, 4-5 -> `high`; label "BAFU-Stufe N · {Bedeutung}" ("keine oder geringe", "mässige", "erhebliche", "grosse", "sehr grosse Gefahr": no or low, moderate, considerable, high, very high danger); level missing -> `unknown` | MUST [approved] |
| B-13 | **Radiation:** normalize the unit (nSv/h, µSv/h/uSv/h/μSv/h, mSv/h, Sv/h) to µSv/h; unknown unit or invalid value -> `unknown`. From **0.3 µSv/h** `elevated`, from **1.0 µSv/h** `high`, below that `normal`; `origin` = `display`. The basis text names the thresholds and that it is **not an official alarm level or health assessment** | MUST [approved] |
| B-14 | The radiation thresholds are **configurable** (finite, positive, "high" > "elevated", otherwise configuration error). | SHOULD |

---

## 5. Fetcher (data acquisition)


| ID | Requirement | Prio |
|---|---|---|
| F-01 | One **plugin package per source** with a common interface; a source names its layer and scopes itself, and layers are composed of the sources that name them. A new source **touches only its package**. Likewise one **plugin package per layer** with its processing, settings, map display, legend and contributions to the overview (tiles, side panel, map notice); a new layer **touches only its package and those of its sources** | MUST |
| F-02 | Update intervals **per source**: the default comes from the plugin (the update rate of the source, at least the minimum of its terms of use), configurable per source but never below that; a layer is updated whenever one of its sources has new data | MUST |
| F-03 | Snapshots are written **atomically** (temporary file + rename), optionally also precompressed (`.br`/`.gz`) | MUST |
| F-04 | At most **one** run per source and scope, and at most **one** assembly per layer at a time, whether from cron or the fallback: a source that is already running is skipped (non-blocking lock); an assembly waits briefly (up to 5 s) for one in progress, so that it includes the newest outcomes | MUST |
| F-05 | On error, the **last good snapshot** remains; the failure is recorded (D-05). After a failure of a source a **lockout period** (backoff) applies to that source; a `Retry-After` of the provider is honoured | MUST |
| F-06 | First installation without a snapshot: the delivery reports "no data yet" (A-04) | MUST |
| F-07 | **HTTPS only**, also for redirects (at most 3); TLS verification always active; optionally a custom CA file | MUST |
| F-08 | **Size limit** per response and source (e.g. 5 MB default, 20 MB per page for DWD; abort when exceeded); time limits per connection (e.g. 10 s) and request (e.g. 18 s) as well as a total budget per run; all configurable | MUST |
| F-09 | Process XML without DTD/entities (XXE protection), no network access while parsing | MUST |
| F-10 | Own user agent with contact address, accept compressed transfer, suitable `Accept` header | MUST |
| F-11 | Several requests of a layer **in parallel** with limited parallelism | SHOULD |
| F-12 | Links from source data are checked (`http`/`https`, valid URL), otherwise fallback to the source page | MUST |
| F-13 | Every layer also runs in the fallback within the web limits (time, memory). The memory peak per layer is logged, so that tight cases (especially DWD during severe weather) stand out | MUST |
| F-14 | Errors are logged with layer, country and cause | MUST |
| F-15 | Every adapter has tests with recorded source responses, also for special cases (empty, expired, Cancel, invalid coordinates, partial outage) | MUST |
| F-16 | Master data (places, regions, country bounds, source URLs, Appendix A) are kept in **one** place and shared by fetcher and frontend | MUST |
| F-17 | Every **source can be switched off individually** via the configuration, without a deploy. A switched-off source, or one whose required access data (e.g. an API key) is not configured, appears in its layer as a cause (`issues`); the layer becomes `partial`, or `setup` if no other source is active | MUST |
| F-18 | **Detect format drift:** every parser reports per run the number of valid and discarded records. If the share of discarded records rises above a configurable threshold (default 10 %) or the number of valid ones falls below an empirical value, the layer reports this as a separate cause and logs it separately | SHOULD |
| F-19 | For every source there is a **source profile** (endpoint, format, license and attribution, terms of use, update rate of the source, known peculiarities). The profile lies in the package of the source. The fetch interval of a source is not shorter than its update rate or the minimum interval of its terms of use | MUST |

---

## 6. Delivery

| ID | Requirement | Prio |
|---|---|---|
| A-01 | Snapshots via a lean PHP script **without WoltLab**: age check, output of the file unchanged (`readfile()`), `ETag`/`Last-Modified`, `304` for an unchanged state, suitable `Cache-Control` | MUST |
| A-02 | If a layer is clearly older than expected (a source of it has not succeeded for e.g. 2 to 3 times its interval), the due sources of **exactly this layer** are updated in the background after the response has been sent; the user never waits | MUST |
| A-03 | Only allowed values for country and layer (allowlist); only `GET`/`HEAD` | MUST |
| A-04 | Without a snapshot: defined response "no data yet", which the frontend shows as "Daten werden abgerufen ..." (fetching data ...) | MUST |
| A-05 | PMTiles static with range requests, without compression | MUST |
| A-06 | No access check in the component | MUST |
| A-07 | Security headers: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` | SHOULD |

---

## 7. User interface

### 7.1 Structure

| ID | Requirement | Prio |
|---|---|---|
| U-01 | **Header in the element** (ACCESS-AND-BRANDING B-D1): logo of the installation with link (attributes `logo`, `logo-dark`, `logo-link`, `logo-alt`; without them the CommonSight icon), the name of the installation (attribute `site-name`, from `custom/branding.json`; without it "CommonSight"; 2026-10-04), **clock with date** (seconds, from 860 px width), light/dark switch automatic/light/dark (only if the host page does not set the theme, T-05); off with `header="none"` for a host page with its own (T-10). Country selection and views are in the page head (U-03) | MUST |
| U-02 | **Views** as tabs: "Lagekarte" (map view: map, layers, news, key figures), "Messwerte" (measured values: layers `weather`, `air`, `pollen`, `water`, `radiation`, `space`), "Warnungen & Ereignisse" (warnings & events: layers `warnings`, `nature`, `traffic`) | MUST |
| U-03 | **Page header:** one line with the country/region selection and next to it the views (U-02); no heading, no fetch time, no "Aktualisieren" (refresh) (the UI updates itself, U-50 to U-52); "Datenquellen" (data sources) sits below the source status (U-22) | MUST |
| U-04 | ~~Footer~~ dropped: the notice "Ergänzendes Lagebild. Datenlücken sind keine Entwarnung. Amtliche Anweisungen haben Vorrang." (supplementary situation picture; data gaps are no all-clear; official instructions take precedence), a link to the operator and the notice "Einstellungen bleiben in diesem Browser" (settings stay in this browser) are shown by the embedding host page. *Added (2026-10-01):* below the content only the version, right-aligned and small ("Version 0.1.0", from the file `VERSION`, maintained by hand) | MUST |
| U-05 | Short status messages (toasts) for events such as location, fullscreen, map errors | SHOULD |
| U-06 | Offline detection: banner "Keine Internetverbindung. Angezeigte Daten können veraltet sein." (no internet connection; displayed data may be stale) | MUST |
| U-07 | The layout works on mobile devices (map, lists, detail sheet) | MUST |

### 7.2 Country and region

| ID | Requirement | Prio |
|---|---|---|
| U-10 | Start country from the configuration of the host page, overridden by the saved selection (U-60) | MUST |
| U-11 | A country switch resets region, detail sheet and map focus and zooms the map to the country | MUST |
| U-12 | **Region selection** "Bundesland" or "Kanton" with "Ganz {Land}" (all of {country}) and alphabetically sorted regions; button "Ganzes Land" (whole country) when a region is selected. Implemented as a combined selection "Land / Region" with "Alle Länder" (all countries) as the first entry: DE, AT and CH together, without region, reference place of the key figures Germany | MUST |
| U-13 | The selection **zooms the map** to the region, **draws the region border** (dashed) and **filters** all layers except `news` and `space` (these remain supra-regional, with a notice) | MUST |
| U-14 | **Regional assignment** of an item, in this order: (1) geometry intersects the region area (point in polygon with holes, line intersection, area overlap); (2) `lat`/`lon` lies in the region area; (3) `regionIds` contains the region; (4) the area specification of a warning (`area`) matches a region name or alias (e.g. Genève, Ticino, Vaud); otherwise **"ohne sichere Ortszuordnung"** (without reliable location assignment) | MUST |
| U-15 | Items without reliable location assignment are **not** counted as matches, but are reachable via "Ohne Ortszuordnung (N)" (unassigned (N)), with the explanation that it cannot be determined whether they concern the region | MUST |
| U-16 | Display: "{Region} · N regional zugeordnete Einträge" (N regionally assigned items); the layer notice is extended by "Region X: N zugeordnete Einträge, M ohne sichere Regionalzuordnung" (N assigned items, M without reliable regional assignment) | MUST |
| U-17 | The region filter works immediately, because the fetcher assigns the items to the regions (Architecture V2). Only the **outline** of the selected region is loaded on demand (one file per region); on error the filter remains effective, and the map shows a message with "Erneut versuchen" (try again) instead of the outline | MUST |
| U-18 | ~~The regional assignment of large amounts of data runs in a web worker~~ *Dropped:* the assignment is done by the fetcher (Architecture V2); the browser only compares `regionIds`. A web worker is only added if measurements show jank | - |

### 7.3 View "Lagekarte"

| ID | Requirement | Prio |
|---|---|---|
| U-20 | **Layer list** with icon, name, color and toggle per layer (without `news`), counter "aktiv / gesamt" (active / total); active by default: `warnings`, `weather`, `water` | MUST |
| U-21 | Switching on `space` (cannot be shown on the map) or a layer with status `setup` opens its detail sheet | SHOULD |
| U-22 | Below the layer list: notice "Orte und Messpunkte anklicken ..." (click places and measuring points ...), button "Warnmeldungen ansehen" (view warnings; the layer with the map notice names it), "Quellenstatus" (source status) with "N von M Quellen verbunden" (N of M sources connected; M = all layers) and, if applicable, "M derzeit nicht erreichbar" (M currently unreachable), below that the button "Datenquellen" (opens U-40) | MUST |
| U-23 | **Map notice** above the map: number of warnings (in the region or in the connected sources), "unvollständig" (incomplete) for `partial`, "Warnquelle derzeit nicht erreichbar" (warning source currently unreachable) on error, notice about items without location assignment; click opens the warnings; can be closed and shown again, the state is saved | MUST |
| U-24 | **Map legend** at the bottom: number of active layers and the update countdown; no permanent color keys, the layers are recognized by their color in the layer list | MUST |
| U-25 | **Legend of measuring point colors on demand:** link "Legende" below the map, only while a layer with a legend is active (today gauges and radiation): orange/red/gray with the explanation each layer gives (gauges per country, Appendix B; ODL thresholds from `stats`; the age after which its points turn gray, gauges > 6 h, ODL > 12 h), gray = assessment missing, disturbed or stale, **"Ein grauer Punkt ist keine Entwarnung"** (a gray dot is no all-clear), "Maßgeblich bleiben die amtlichen Warnungen" (the official warnings remain authoritative) | MUST |
| U-26 | **News** as a sidebar: topic filter "Alle"/"Unwetter"/"Konflikte"/"Infrastruktur" (all/severe weather/conflicts/infrastructure) as a select field in the sidebar header, notices as a compact news box (U-80); empty: "Keine passenden Meldungen ... der letzten 72 Stunden" (no matching notices ... of the last 72 hours); error: link to the news source of the country; loading state as a placeholder; footer with update rhythm and, if applicable, "einzelne Feeds fehlen" (individual feeds missing) | MUST |
| U-27 | **Key figures "Messwerte im Überblick"** (measured values at a glance), one tile per layer that contributes one, ordered by its rank; each tile clicks into the detail sheet. Today: **weather** (first place or first place of the region: °C, weather text, time); **air** (EU AQI with classification, highlighted from > 40); **space weather** (Kp, G/R/S, line of the last Kp values); **radiation** (station nearest to the region point or to the first place of the country, µSv/h, "älterer Wert" (older value) at > 6 h, click opens this station). The 6 h of the tile are a hint about the age; independently of that, the assessment only turns gray after 12 h (B-02) | MUST |

### 7.4 Map

Technology: MapLibre GL with PMTiles, map style from the theming variables.

| ID | Requirement | Prio |
|---|---|---|
| K-01 | Basemap DACH from the own PMTiles archive, mask outside DACH, `maxBounds`; start on the bounds of the country or region | MUST |
| K-02 | Controls: zoom, scale (metric), **reset view** (country or region), **my location** (geolocation only centers the map, zoom approx. 10; message on error or missing support), **fullscreen** (with fallback message) | MUST |
| K-03 | **Warning areas** as polygons in warning color by severity (fixed functionally, not changeable via theming, T-07): Extreme `#f66f6f`, Severe `#fa9966`, Moderate `#e7b567`, Minor `#e8c880`; for Unknown the Minor color | MUST [approved; color values] |
| K-04 | **Weather** as a label at the place: place name and rounded temperature | MUST |
| K-05 | **Measuring points** (gauges, radiation, earthquakes, traffic, air) as circles in layer color or assessment color: `elevated` `#f97316`, `high` `#ef4444`, `unknown` `#94a3b8`. `high` and `elevated` larger, with a light border and **always in the foreground**; `unknown` semi-transparent and dashed (MapLibre circles cannot be dashed: drawn hollow with a gray outline instead, ARCHITECTURE 9.4, to be confirmed in the acceptance of T-08) | MUST |
| K-06 | Lines and areas of other layers (e.g. ÖAMTC routes, MoWaS areas) are drawn | MUST |
| K-07 | **Tooltip** on hover: the core details of the item's box (U-83), i.e. title, value with unit, for warnings area and validity, source and data status, assessment with justification. All content from sources is escaped | MUST |
| K-08 | **Click** on an object opens the detail sheet of the item | MUST |
| K-09 | "Auf Karte anzeigen" (show on map) from lists and detail sheet: activates the layer, switches to the map view, zooms to the geometry (at most zoom 11) or flies to the point (at least zoom 9) | MUST |
| K-10 | The map is only drawn for active layers; not for `space` and layers with status `error` | MUST |
| K-11 | Large point sets (up to approx. 1,700 radiation stations, thousands of gauges) are drawn performantly as GeoJSON sources/WebGL layers, not as individual DOM markers | MUST |
| K-12 | Failure of the basemap leads to a message, lists remain usable ("Die Karte konnte nicht geladen werden. Die Datenlisten bleiben verfügbar." - the map could not be loaded; the data lists remain available) | MUST |
| K-13 | Map attribution behind the "i", closed at every start: only OpenStreetMap/Protomaps. The credits of the data sources (e.g. GeoSphere for AT warnings) and of the region borders (Appendix C) are in the data use of the side bar, not on the map | MUST |
| K-14 | Accessible labeling of the map (e.g. "Interaktive Karte für {Land/Region}", interactive map for ...) and of the tools | MUST |

### 7.5 Views "Messwerte" and "Warnungen & Ereignisse"

| ID | Requirement | Prio |
|---|---|---|
| U-30 | Selection of the layer (data source) and **text filter** "Ort oder Stichwort ..." (place or keyword ...) over title and description | MUST |
| U-31 | Status line: status ("Quelle verbunden", "Teilweise verfügbar", "Zugang benötigt", "Nicht verfügbar", "Wird geladen": source connected, partially available, access required, not available, loading), source, number of items, source date, link "Abdeckung & Quelle" (coverage & source; detail sheet of the layer) | MUST |
| U-32 | Items are shown in lists as the **box of their kind** (U-80), in the compact variant | MUST |
| U-33 | In "Messwerte" additionally a switchable **table view**: place/station, value with unit and assessment, data status, source as link, "Auf Karte anzeigen" | SHOULD |
| U-34 | Incremental loading: first 60 items, "Weitere Einträge anzeigen (N)" (show more items (N)) | MUST |
| U-35 | Empty states with cause: "Datenzugang noch nicht eingerichtet", "Quelle derzeit nicht verfügbar", "Keine passenden Einträge", "Keine Einträge im abgefragten Umfang", "Daten werden abgerufen ..." (data access not set up yet, source currently not available, no matching items, no items in the queried scope, fetching data ...); below that the layer notice and "Originalquelle öffnen" (open original source) | MUST |
| U-36 | The layer notice is below the list | MUST |

### 7.6 Boxes per kind of item

| ID | Requirement | Prio |
|---|---|---|
| U-80 | **One box of its own per kind of item (D-11)**, not per source. Items of the same kind from different sources look the same. Content per kind: | MUST |
| | - **Warning:** level with fixed warning color (T-07) and as text, warning type, area, "Gültig ab ... bis ..." (valid from ... until ...), text in sections; compact only the first section, shortened | |
| | - **Measured value:** value with unit and reference, measured quantity, assessment with color and label (compact) or with justification (detailed), measurement time, original level of the source, additional values | |
| | - **Model value:** value with unit, short text, additional values, notice "Modellwert, keine Messung" | |
| | - **Earthquake:** magnitude, depth, place, time | |
| | - **Traffic notice:** route, type, start, description | |
| | - **Index:** value on its scale (e.g. Kp 5 of 9), history as a line | |
| | - **News:** topic, time, headline, feed name | |
| U-81 | Every box has the common elements in the same place: header with category or layer and time, title, link to the original source, "Auf Karte anzeigen" (if a location exists) | MUST |
| U-82 | Every box exists in two variants: **compact** (lists, news) and **detailed** (detail sheet, U-42). It is the same box with less or more content, not a second design | MUST |
| U-83 | **Tooltip** on the map (K-07), box and detail sheet show the same details in the same wording for the same item | MUST |
| U-84 | If a new kind is added, it cannot be delivered without its own box (checked by the build) | MUST |

### 7.7 Detail sheet (side panel)

| ID | Requirement | Prio |
|---|---|---|
| U-40 | **Data sources & coverage:** explanation of the update rhythms; per layer incl. news: status, notice, source, count, fetch time, source date, link to the original source, "Daten ansehen" (view data); section **data usage** with license notices (Open-Meteo CC BY 4.0, warning sources, map basis, RSS) | MUST |
| U-41 | **Layer:** notice, status, fetch time, "Offizielle Quelle öffnen" (open official source); for `warnings`, `nature`, `water` additionally **"Weitere amtliche Informationen"** (further official information) with country-specific links (Appendix D); items as boxes of their kind in the detailed variant (U-82; at most 150, then a reference to the map); empty: "Keine Einträge im abgefragten Umfang ... Dies ist keine allgemeine Entwarnung" (no items in the queried scope ... this is no general all-clear) (for earthquakes with "letzte 7 Tage im Landesumfeld", last 7 days in the country surroundings) | MUST |
| U-42 | **Item:** the box of the item in the detailed variant (U-82); for warnings without text the notice "Die vollständige Warnmeldung mit Verhaltenshinweisen findest du in der offiziellen Quelle" (the complete warning with guidance is in the official source); "Vollständige Originalquelle öffnen" (open complete original source), layer notice and "Alle Einträge dieser Ebene" (all items of this layer) | MUST |
| U-43 | **Without location assignment:** explanation (U-15) and list of these items with layer, source, time, title, area, description, "Originalquelle prüfen" (check original source) | MUST |
| U-44 | Subtitle "{Land} · Quellenstatus und Aktualität" or "{Land} · Quelle, Datenstand und Einordnung" ({country} · source status and currency / source, data status and assessment) | SHOULD |

### 7.8 Updating in the client

| ID | Requirement | Prio |
|---|---|---|
| U-50 | **Only the layers currently needed** are loaded: active map layers, the layer selected in a list, `news` when the news panel is visible, the layers of the key figures, all of them for the source status | MUST |
| U-51 | Reloading at the interval of the layer (the shortest interval of its sources, reported by the status, F-02), **paused in background tabs** (`document.hidden`), immediate reload on return if the state is older than the interval | MUST |
| U-52 | Conditional requests (`ETag`/`If-None-Match`), so that unchanged states only cost a `304` | MUST |
| U-53 | Age of every data status visible; the relative time is re-evaluated regularly (at least every minute) (B-03) | MUST |
| U-54 | Error loading a layer -> layer with status "Nicht verfügbar" and notice "Die Quelle ist derzeit nicht erreichbar. Das ist keine Entwarnung." (the source is currently unreachable; this is no all-clear); last loaded data remain visible, if present | MUST |
| U-55 | On a country switch, running requests of the old country are aborted | MUST |

### 7.9 Settings in the browser

| ID | Requirement | Prio |
|---|---|---|
| U-60 | Saved (only locally, `localStorage`): country, active layers, region, map notice hidden, switch "Grenzgebiet", map section (centre and zoom, restored only for the same country and region); separately the selected light/dark. Invalid saved values are discarded. Missing or blocked storage must not impair anything | MUST |
| U-61 | The storage key can be set via the configuration of the host page (e.g. per user, so that users of one browser do not mix) | SHOULD |

---

## 8. Theming, presentation, accessibility

| ID | Requirement | Prio |
|---|---|---|
| T-01 | Custom element with Shadow DOM, no iframe | MUST |
| T-02 | Documented set of CSS variables (colors, font, shape, controls) with own default values; font `inherit` by default | MUST |
| T-03 | `::part()` for header, layer list, legend, detail sheet | SHOULD |
| T-04 | The map style is generated from the same variables and set anew on theme change | MUST |
| T-05 | Light/dark via attribute `theme="light|dark"`; without a preset `prefers-color-scheme`; an own toggle only without a preset from the host page | MUST |
| T-06 | **Layer colors** (initial values, each declared by its layer package and overridable as `--cs-layer-<id>`): warnings `#dc8c2b`, weather `#388bbb`, air `#348f7b`, pollen `#c2507e`, water `#398ed4`, nature `#9476ce`, space `#a37dc2`, radiation `#b0a235`, traffic `#768d9f`, news `#657488`; icons per layer | SHOULD |
| T-07 | **Warning level and assessment colors are fixed** and cannot be changed via theming; variants only for light/dark and accessibility | MUST |
| T-08 | Color is never the only carrier of information: assessments always have a label; `unknown` additionally dashed | MUST |
| T-09 | Keyboard operability, visible focus, ARIA labels, status messages as a live region; `prefers-reduced-motion` is respected | MUST |
| T-10 | Embedding mode: header can be hidden (`header="none"`), full width | SHOULD |
| T-11 | Colours of the header as variables (`--cs-header-bg`, `--cs-header-text`, `--cs-header-line`) like all others; the element marks the theme in effect as `data-theme` on itself, so a host page can give its variables a value per theme; the operator keeps colours and logo in `<WEBROOT>/custom/`, which updates never overwrite; logo and favicon in any format, a dark logo, link and alt text via `custom/branding.json` (B-D2, B-D3) | MUST |

---

## 9. Language, time, numbers

| ID | Requirement | Prio |
|---|---|---|
| I-01 | All UI texts via i18n, initially German only | MUST |
| I-02 | **Display in the language and time zone of the browser.** Date, time and number formats follow the language of the browser (`navigator.languages`), the time zone follows the browser setting. The conversion from UTC (D-12) to the time zone of the browser happens exclusively at display time | MUST |
| I-03 | Data status briefly with day, month, hour and minute in the format of the browser language (`Intl.DateTimeFormat`), e.g. "28.09., 12:15" for German; missing: "kein Datenstand" (no data status). No hard-wired format | MUST |
| I-04 | Measured values with up to 3 decimal places (radiation, gauges), temperature with 1, AQI with none | SHOULD |

---

## 10. Embedding and host page

| ID | Requirement | Prio |
|---|---|---|
| E-01 | Configuration via attributes of the element or a configuration object: base URL of the snapshots, URL of the tiles and glyphs, default country, language, theme, storage key, embedding mode | MUST |
| E-02 | **WoltLab host page:** own page with template (no iframe), login required, **group permission** instead of `allowed_group_ids`, banned users excluded, page not indexable and not on the start page, ACP options for default country and the like, mapping of the forum style variables to the theming variables | MUST |
| E-04 | For geolocation the page needs the permission in the context of the host page | MUST |
| E-05 | **Start page for members only** (ACCESS-AND-BRANDING A-D1 to A-D5): an auth provider (plugin in `plugins/auth/`, `auth` in `config.php`) decides per request whether the start page shows the map; a visitor who is not admitted sees the header and a members card with the name of the community and its login (back to CommonSight) and registration links. Only the start page is protected; data, tiles and status stay public. Closed on every failure, which is logged; nothing about visitors is kept or logged. Without a provider the page is open | MUST |
| E-06 | **WoltLab provider:** members are users of the forum with a session active within 60 days who are not banned (session cookie of WoltLab Suite 6, read from the forum database with own read-only access data or the forum's `config.inc.php`); logging out takes effect at once | MUST |

---

## 11. Legal and attribution

| ID | Requirement | Prio |
|---|---|---|
| R-01 | The source of every layer and every item is visible and linked | MUST |
| R-02 | License notices: Open-Meteo/CAMS CC BY 4.0; GeoSphere Austria CC BY 4.0 with area basis Statistik Austria; OSM (ODbL) and Protomaps; region borders according to Appendix C | MUST |
| R-03 | The Austrian region borders (derived, CC BY-SA 2.0) are passed on under the same license; the application code is licensed under the AGPL-3.0 | MUST |
| R-04 | The license texts of the application and of all third-party parts (libraries, fonts, glyphs, map and region data) are shipped together in one place, with an overview of what is licensed how | MUST |
| R-05 | Privacy notice: thanks to the fetcher and own tiles, no third parties receive users' connection data | MUST |
| R-06 | Logo and favicon belong to the application, the font is delivered locally with its license; the map font needs its own glyphs with a suitable license | MUST |

---

## 12. General requirements on the architecture

Apply to backend and frontend. The concrete rules and how they are checked are in `ARCHITECTURE.md`, section 1.3.

| ID | Requirement | Prio |
|---|---|---|
| N-01 | **Strict separation of responsibilities.** Every class, every module and every function has exactly one responsibility and implements only that. | MUST |
| N-02 | **Separation of task types.** Domain logic, input/output (network, files, locks, clock, browser, map), flow control and presentation live in separate building blocks. Domain logic contains no input/output. | MUST |
| N-03 | **Directed dependencies.** Dependencies point in one direction only, without cycles. Every building block receives exactly the dependencies it needs, passed explicitly; no aggregate objects, no global state. | MUST |
| N-04 | **No catch-all classes.** No classes or modules that bundle several sources, layers or tasks. | MUST |
| N-05 | **Isolated testability.** Every building block of the domain logic can be tested without network, file system and clock, and is tested that way. | MUST |
| N-06 | **Checkable and checked.** Compliance with N-01 to N-05 is checked automatically in the build where possible and otherwise in code review; a violation prevents acceptance. | MUST |

---

## Appendix A: Master data per country

| | DE | AT | CH |
|---|---|---|---|
| Name | Deutschland | Österreich | Schweiz |
| Bounds (S/W - N/E) | 47.25/5.6 - 55.08/15.45 | 46.36/9.45 - 49.1/17.18 | 45.75/5.93 - 47.83/10.5 |
| Warning source (display) | BBK / NINA · Deutscher Wetterdienst | GeoSphere Austria · AT-Alert | MeteoSchweiz · MeteoAlarm · Alertswiss |
| Warnings (link) | https://warnung.bund.de/ | https://warnungen.zamg.at/ | https://www.naturgefahren.ch/ |
| Gauges (link) | https://www.pegelonline.wsv.de/ | https://ehyd.gv.at/ | https://www.hydrodaten.admin.ch/ |
| Radiation (link) | https://odlinfo.bfs.de/ | https://mb.strahlenschutz.gv.at/ | https://www.naz.ch/de/aktuell/tagesmittelwerte |
| Traffic (link) | https://www.autobahn.de/unterwegs | https://www.oeamtc.at/verkehrsservice/ | https://www.tcs.ch/de/tools/verkehrsinfo-verkehrslage/ |
| News on failure | https://www.tagesschau.de/ | https://orf.at/ | https://www.srf.ch/news |

**Places for weather and air** (each with region ID):

- **DE (16):** Berlin, Hamburg, München, Köln, Frankfurt, Stuttgart, Dresden plus model points Brandenburg, Bremen, Mecklenburg-Vorpommern, Niedersachsen, Rheinland-Pfalz, Saarland, Sachsen-Anhalt, Schleswig-Holstein, Thüringen
- **AT (9):** Wien, Linz, Graz, Salzburg, Innsbruck, Klagenfurt, Bregenz plus model points Burgenland, Niederösterreich
- **CH (26):** Bern, Zürich, Basel, Genf, Luzern, Lugano, Chur plus model points for the remaining 19 cantons

**Regions:** IDs according to ISO 3166-2 (DE-BW ..., CH-AG ...) or `AT-1` ... `AT-9` (state code). Per region: name, aliases (CH: Fribourg, Genève, Neuchâtel, Ticino, Vaud, Valais), bounding box, reference point, border area (`contract/regions/{country}.geojson`, properties `id`, `name`).

## Appendix B: Gauge legend texts per country

- **DE:** orange from MHW (mean high water level), red from HSW (highest navigable water level), according to PEGELONLINE. HSW is a navigation limit and not a general flooding limit. Missing thresholds are not estimated.
- **AT:** orange at eHYD flood level 1 (HQ1 to below HQ10), red at levels 2 and 3 (from HQ10). Low/mean water and elevated flow below HQ1 keep the layer color.
- **CH:** orange at BAFU danger levels 2 and 3, red at levels 4 and 5. The exact official level is shown at the measuring point; the colors of the map merge levels.
- For all: without a threshold being exceeded, in layer color (blue).

## Appendix C: Region borders (origin and attribution)

| Country | Source | As of | Attribution | License |
|---|---|---|---|---|
| DE | BKG via geoBoundaries gbOpen ADM1 (`DEU-ADM1-10402087`) | 2021 | © BKG (2021), geoBoundaries | dl-de/by-2-0 |
| AT | BEV via geoBoundaries gbOpen ADM1 (`AUT-ADM1-97560089`) | 2017 | © BEV / geoBoundaries (2017) | CC BY-SA 2.0 |
| CH | swisstopo swissBOUNDARIES3D, cantons | 2026-01-01 (incl. the move of Moutier to Jura) | © swisstopo (2026) | swisstopo terms of use |

Preparation: geometry-preserving simplification (tolerance 0.0001°), rounding to 5 decimal places; the preparation is done by `tools/regions/`.

## Appendix D: Further official information (links per country)

| DE | AT | CH |
|---|---|---|
| Warnung Bund - https://warnung.bund.de/ | AT-Alert - https://warnungen.at-alert.at/ | Alertswiss - https://www.alert.swiss/de/home.html |
| Hochwasserzentralen - https://www.hochwasserzentralen.de/ | HORA · Naturgefahren - https://hora.gv.at/ | Naturgefahrenportal - https://www.naturgefahren.ch/ |
| Lawinenwarndienst Bayern - https://lawinenwarndienst.bayern.de/ | Lawinenwarndienste - https://lawinen.at/ | SLF · Lawinenbulletin - https://www.slf.ch/de/lawinenbulletin-und-schneesituation/ |
