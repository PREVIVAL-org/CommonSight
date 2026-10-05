# CommonSight

CommonSight is a public situation picture for civil preparedness in Germany, Austria and Switzerland (DACH). It brings official warnings, measured values, model values, events and news from public sources together on one map, in lists and in key figures: weather warnings and civil protection alerts, weather, air quality and pollen, water levels, ambient gamma dose rate, space weather, earthquakes and traffic notices.

Every value shows its source, the time of measurement and a link to the official source, and gaps in the data stay visible. CommonSight supplements official information; it does not replace it. Data gaps are no all-clear, and official instructions always take precedence.

A border zone of 300 km beyond DACH (switch "Grenzgebiet") adds the measuring points of the neighbouring countries, so that residents of border regions see their whole surroundings: weather and air, water levels in France, the Netherlands, the Czech Republic and Poland, and the dose rate in Poland and South Tyrol.

CommonSight is an independent project. It is embedded as a web component (`<commonsight-map>`) into a host page and is currently hosted, for example, at <https://lagezentrum.previval.org/>. Data are fetched server-side by a PHP fetcher and delivered as static, versioned snapshots; visitors never cause requests to the data sources.

## Licence

The source code is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0), see `LICENSE`.

The displayed data remain subject to the terms of their providers (see the attributions in the application). Bundled third-party components keep their own licences; `LICENSES/` lists them with their texts.

## Pending permissions for data use

The following uses need a permission or a confirmation from the data provider before they go live, or before they stay live.

| Provider | Country | Data | Status |
| --- | --- | --- | --- |
| Bundesamt für Strahlenschutz (BfS) | DE | EURDEP layer `opendata:eurdep_latestValue`, currently used for the dose rate in CH | **in use, licence unclear**: EURDEP data need the written agreement of the original providers |
| Umweltbundesamt / BMLUK | AT | early warning system for radiation, `mb.strahlenschutz.gv.at/api/current` | **in use**, confirmation pending (non-commercial use with attribution appears to be allowed) |
| Nationale Alarmzentrale (NAZ) | CH, LI | NADAM dose rate | written consent required |
| MeteoAlarm (EUMETNET) | EU | geometries of the warning areas (geocodes GeoJSON) for warnings of the neighbouring countries | licence of the geometry files not stated |
| FANC / AFCN | BE | TELERAD dose rate (Doel, Tihange) | permission required (personal use only without it) |
| ASNR | FR | Téléray dose rate (Cattenom, Bugey) | no public interface, request for an official feed |
| SÚJB | CZ | MonRaS dose rate (Temelín, Dukovany) | no licence stated, no public interface |
| URSJV | SI | dose rate (Krško) | no licence stated |
| SHMÚ | SK | dose rate (Mochovce) | licence of the endpoint unclear |
| SSM | SE | gamma stations | no licence stated |
| BM OKF | HU | dose rate (Paks) | prior written permission required |
| SPW Wallonie | BE | water levels (Meuse, Ourthe) | terms contradict each other, written permission required |
| VMM Vlaanderen | BE | water levels via waterinfo KiWIS | free access token for automated retrieval |
| OVF | HU | water levels | data free, request for a proper interface |
| Arpae Emilia-Romagna | IT | water levels | undocumented interface, licence unclear |

Sources with an open licence (CC0, CC BY, Etalab, Flemish or Polish open data licences) need no request, but their attribution requirements apply.

## Origins of this Project

Parts of the source code and documentation for this project were written by Claude, an AI model developed by Anthropic. The concept, architecture, requirements, and specifications were defined by the authors; the authors directed the development, reviewed and selected the outputs, and approved every change prior to merging.
