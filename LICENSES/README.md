# Licenses

The code of CommonSight is licensed under the GNU Affero General Public License v3.0 ([LICENSE](LICENSE) next to this file in an installation, `../LICENSE` in the repository). Third-party parts keep their own licenses; the paths name where they are in the repository:

| Part | Copyright | License |
|---|---|---|
| Open Sans 1.10 (`frontend/public/fonts/`), weights 400, 600, 700 | Digitized data © 2010-2011 Google Corporation; design Ascender Corporation. Open Sans is a trademark of Google. Modified: subset to the characters needed for DACH, kerning moved from the legacy kern table to GPOS. | [Apache-2.0](Apache-2.0.txt) |
| Noto Sans glyphs of the map (`map-assets/glyphs/`, downloaded from protomaps/basemaps-assets) | © 2022 The Noto Project Authors (https://github.com/notofonts) | [OFL-1.1](OFL-1.1.txt) |
| Basemap data (tile archive, Protomaps builds) | © OpenStreetMap contributors | [ODbL-1.0](ODbL-1.0.txt) |
| Region boundaries DE (`contract/regions/DE.geojson`) | © BKG (2021), via geoBoundaries | [DL-DE-BY-2.0](DL-DE-BY-2.0.txt) |
| Region boundaries AT (`contract/regions/AT.geojson`), derived | © BEV / geoBoundaries (2017); passed on under the same license | [CC-BY-SA-2.0](CC-BY-SA-2.0.txt) |
| Region boundaries CH (`contract/regions/CH.geojson`) | © swisstopo (2026) | terms of use of swisstopo for free geodata: https://www.swisstopo.admin.ch/de/nutzungsbedingungen-kostenlose-geodaten-und-geodienste |
| Libraries in the frontend bundle (React, Radix UI, MapLibre GL, pmtiles, @protomaps/basemaps, zustand, lucide-react) | see their license texts | collected at build time in `THIRD-PARTY-LICENSES.txt` next to these files; for libraries that ship no license file the upstream text comes from `frontend/licenses/`, and the build fails if a text is missing |

The data of the sources shown on the map (warnings, measurements, news) are subject to the terms of their providers; source and link are shown with every layer and item.

In an installation package all license texts are in `web/licenses/`.
