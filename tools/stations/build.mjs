// Master data of measuring stations whose current values come without name, position or flood stages
// (ADR 0038): the fetcher joins them by station ID. Fetched from the official catalogues of the providers.
//
//   node tools/stations/build.mjs            (Node 18 or newer, no dependencies)
//
// Inputs:   map-assets/border-zone.geojson   DACH plus 300 km; stations outside are left out
//           live catalogues (see the functions below)
// Output:   plugins/providers/<id>/data/stations.json  stations of the source, sorted by ID
//
// The stations change rarely; run again when a source profile notes new or missing stations.

import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '../..');
const USER_AGENT = 'CommonSight/2.0 (+https://previval.org)';

const zone = JSON.parse(readFileSync(`${root}/map-assets/border-zone.geojson`, 'utf8'));
const zoneRings = zone.features.flatMap((f) => (f.geometry.type === 'Polygon' ? [f.geometry.coordinates] : f.geometry.coordinates));

/** Ray casting over all parts of the zone, holes included. */
function inZone(lon, lat) {
  let inside = false;
  for (const polygon of zoneRings) {
    for (const ring of polygon) {
      for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [xi, yi] = ring[i];
        const [xj, yj] = ring[j];
        if (yi > lat !== yj > lat && lon < ((xj - xi) * (lat - yi)) / (yj - yi) + xi) inside = !inside;
      }
    }
  }
  return inside;
}

const round = (value) => Math.round(value * 100000) / 100000;

async function get(url, init = {}) {
  const response = await fetch(url, { ...init, headers: { 'User-Agent': USER_AGENT, Accept: 'application/json', ...init.headers } });
  if (!response.ok) throw new Error(`${url}: HTTP ${response.status}`);
  return response;
}

const json = async (url, init) => (await get(url, init)).json();

function station(id, name, lat, lon, extra = {}) {
  return { id, name, lat: round(lat), lon: round(lon), ...extra };
}

/** France: Hub'Eau hydrometry, stations in service in the zone; foreign stations (commune code 99…) left out. */
async function hubeau() {
  const url = 'https://hubeau.eaufrance.fr/api/v2/hydrometrie/referentiel/stations?bbox=-1,42,10,52&en_service=true&size=10000&format=json'
    + '&fields=code_station,libelle_station,longitude_station,latitude_station,code_commune_station';
  const { data } = await json(url);
  return data
    .filter((s) => s.longitude_station !== null && s.latitude_station !== null && !String(s.code_commune_station ?? '').startsWith('99'))
    .filter((s) => inZone(s.longitude_station, s.latitude_station))
    .map((s) => station(s.code_station, s.libelle_station, s.latitude_station, s.longitude_station));
}

/** Netherlands: Rijkswaterstaat locations with a water level above NAP measured in the last three days. */
async function rijkswaterstaat() {
  const base = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl';
  const post = (path, body) => json(`${base}${path}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  const catalogue = await post('/METADATASERVICES/OphalenCatalogus', { CatalogusFilter: { Grootheden: true, Hoedanigheden: true, Compartimenten: true } });
  const metadata = new Set(catalogue.AquoMetadataLijst
    .filter((m) => m.Grootheid.Code === 'WATHTE' && m.Hoedanigheid.Code === 'NAP' && m.Compartiment.Code === 'OW')
    .map((m) => m.AquoMetadata_MessageID));
  const locationIds = new Set(catalogue.AquoMetadataLocatieLijst.filter((l) => metadata.has(l.AquoMetaData_MessageID)).map((l) => l.Locatie_MessageID));
  const locations = catalogue.LocatieLijst.filter((l) => locationIds.has(l.Locatie_MessageID) && inZone(l.Lon, l.Lat));
  const latest = await post('/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen', {
    LocatieLijst: locations.map((l) => ({ Code: l.Code })),
    AquoPlusWaarnemingMetadataLijst: [{ AquoMetadata: { Compartiment: { Code: 'OW' }, Grootheid: { Code: 'WATHTE' }, Hoedanigheid: { Code: 'NAP' } } }],
  });
  const since = Date.now() - 3 * 86400_000;
  const active = new Set(latest.WaarnemingenLijst
    .filter((w) => w.AquoMetadata.ProcesType === 'meting' && Date.parse(w.MetingenLijst?.[0]?.Tijdstip ?? '') >= since)
    .map((w) => w.Locatie.Code));
  return locations.filter((l) => active.has(l.Code)).map((l) => station(l.Code, l.Naam, l.Lat, l.Lon));
}

/**
 * Czech Republic: ČHMÚ stations with flood stages (SPA 1-3); `stages` refer to the water level in cm (`H`) or the
 * discharge in m³/s (`Q`), as the station's SPA type says.
 */
async function chmi() {
  const meta = await json('https://opendata.chmi.cz/hydrology/now/metadata/meta1.json');
  const header = meta.data.data.header.split(',');
  const at = (row, name) => row[header.indexOf(name)];
  const result = [];
  for (const row of meta.data.data.values) {
    const kind = at(row, 'SPA_TYP') === 'Q' ? 'Q' : 'H';
    const stages = [1, 2, 3].map((n) => at(row, `SPA${n}${kind}`));
    const lat = at(row, 'GEOGR1');
    const lon = at(row, 'GEOGR2');
    if (stages.some((s) => s === null) || !inZone(lon, lat)) continue;
    result.push(station(at(row, 'objID'), at(row, 'STATION_NAME'), lat, lon, { river: at(row, 'STREAM_NAME'), stageOf: kind, stages }));
  }
  return result;
}

/**
 * Austria: probes of the radiation early warning system. The API delivers only screen positions; the station is
 * identified by its number in EURDEP and placed on the nearest site of the INSPIRE service of the Umweltbundesamt
 * (positions rounded to minutes). Without a site within 3 km the EURDEP position stays (rounded to 0.01°).
 */
async function umweltbundesamt() {
  const current = await json('https://mb.strahlenschutz.gv.at/api/current');
  const eurdep = await json('https://www.imis.bfs.de/ogc/opendata/ows?service=WFS&version=1.0.0&request=GetFeature&outputFormat=application/json'
    + "&maxFeatures=3000&typeName=opendata:eurdep_latestValue&cql_filter=id%20LIKE%20'AT%25'%20AND%20analyzed_range_in_h=6");
  const kml = await (await get('https://inspire.lfrz.gv.at/000808/ows?service=WMS&version=1.1.1&request=GetMap&layers=Standorte_Strahlenfruehwarnsystem'
    + '&styles=&srs=EPSG:4326&bbox=9.4,46.3,17.2,49.1&width=2000&height=1000&format=application/vnd.google-earth.kml%2Bxml'
    + '&format_options=KMSCORE:100;KMATTR:true;mode:download', { headers: { Accept: '*/*' } })).text();
  const sites = [...kml.matchAll(/Breite.*?atr-value"&gt;([^&]*)&lt;.*?nge.*?atr-value"&gt;([^&]*)&lt;/gs)].map((m) => [Number(m[1]), Number(m[2])]);
  const positions = new Map(eurdep.features.map((f) => [f.properties.id, [f.geometry.coordinates[1], f.geometry.coordinates[0]]]));
  const km = ([lat1, lon1], [lat2, lon2]) => Math.hypot((lat1 - lat2) * 111.2, (lon1 - lon2) * 111.2 * Math.cos((lat1 * Math.PI) / 180));
  const result = [];
  for (const probe of current.data) {
    const known = positions.get(probe.nummer);
    if (known === undefined) {
      console.warn(`umweltbundesamt: no position for ${probe.nummer} ${probe.name}`);
      continue;
    }
    const nearest = sites.reduce((best, site) => (km(known, site) < km(known, best) ? site : best), sites[0]);
    const [lat, lon] = km(known, nearest) <= 3 ? nearest : known;
    result.push(station(probe.nummer, probe.name, lat, lon));
  }
  return result;
}

/**
 * South Tyrol: gamma probes of the Agency for Environment (APPA Bozen). The station list of the service has no or
 * implausible positions for these probes, so the town centres are used (about 1-3 km accurate).
 */
function appaBz() {
  return [
    station('AU', 'Auer', 46.3475, 11.2989),
    station('BR', 'Bruneck', 46.7966, 11.9363),
    station('BX', 'Brixen', 46.715, 11.656),
    station('BZ', 'Bozen', 46.4983, 11.3548),
    station('LA', 'Latsch', 46.6178, 10.8653),
    station('RE', 'Ritten', 46.5397, 11.46),
  ];
}

const builders = { 'appa-bz': appaBz, chmi, hubeau, rijkswaterstaat, umweltbundesamt };
const output = {};
for (const [id, build] of Object.entries(builders)) {
  output[id] = (await build()).sort((a, b) => a.id.localeCompare(b.id));
  console.log(`${id}: ${output[id].length} stations`);
}
// Each source keeps its stations in its own plugin package (concept: sources as plugins).
for (const [id, list] of Object.entries(output)) {
  mkdirSync(`${root}/plugins/providers/${id}/data`, { recursive: true });
  writeFileSync(`${root}/plugins/providers/${id}/data/stations.json`, `[\n${list.map((s) => `  ${JSON.stringify(s)}`).join(',\n')}\n]\n`);
}
