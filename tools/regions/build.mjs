// Preparation of the region boundaries (Architecture 8.3), reproducible with mapshaper.
//
//   docker compose run --rm node --prefix /src/tools/regions install
//   docker compose run --rm node --prefix /src/tools/regions run build
//
// Inputs:   contract/regions/{DE,AT,CH}.geojson (Appendix C of the requirements, 5 decimal places)
//           tools/regions/input/liechtenstein.geojson (Natural Earth 1:10m Admin 0, public domain; only clipping and mask)
// Outputs:  map-assets/regions/<region ID>.geojson   more strongly simplified, one file per region (U-17)
//           map-assets/dach.geojson                  union of DE, AT, CH, LI; basis of the border zone
//           map-assets/border-zone.geojson           DACH plus the border zone (BORDER_ZONE_KM): clipping of the tile archive
//                                                    and area of the neighbouring countries' data
//           map-assets/dach-mask.geojson             world minus the border zone, drawn opaque (K-01)
//           map-assets/border-shade.geojson          border zone minus DACH, drawn slightly dimmed
//
// The areas for the assignment in the fetcher stay unchanged in contract/regions/.

import mapshaper from 'mapshaper';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '../..');
const contract = `${root}/contract/regions`;
const out = `${root}/map-assets`;
mkdirSync(`${out}/regions`, { recursive: true });

async function run(command) {
  await mapshaper.runCommands(command);
}

// One file per region for drawing: 20 % of the points, shapes are preserved.
for (const country of ['DE', 'AT', 'CH']) {
  await run(`-i ${contract}/${country}.geojson -simplify 20% keep-shapes -filter-fields id,name -split id `
    + `-o ${out}/regions/ format=geojson extension=.geojson precision=0.00001 force`);
}

// DACH outline: all countries merged, slightly simplified; Liechtenstein closes the gap between CH and AT.
// The country borders come from different sources and do not match exactly. Without dissolve2 (also merges
// overlapping areas) and closing the gaps, DE, AT and CH would remain separate, mutually overlapping
// areas; in the mask these would be overlapping holes that MapLibre fills incorrectly depending on zoom (AT vanished).
await run(`-i ${contract}/DE.geojson ${contract}/AT.geojson ${contract}/CH.geojson ${here}/input/liechtenstein.geojson combine-files `
  + `-merge-layers force -dissolve2 -clean gap-fill-area=50km2 `
  + `-simplify 10% keep-shapes -clean gap-fill-area=50km2 -each 'name="DACH"' `
  + `-o ${out}/dach.geojson format=geojson rfc7946 precision=0.0001 force`);

// Border zone: DACH plus BORDER_ZONE_KM, so that residents of border regions see the situation beyond the border.
// The width of the map area (mapKm) is set in contract/data/border-zone.json; the vicinity of a selection
// (vicinityKm in config.php) may be smaller, at most mapKm, and needs no new map.
const BORDER_ZONE_KM = JSON.parse(readFileSync(`${root}/contract/data/border-zone.json`, 'utf8')).mapKm;
await run(`-i ${out}/dach.geojson -buffer radius=${BORDER_ZONE_KM}km -dissolve2 -simplify interval=500 keep-shapes `
  + `-each 'name="DACH + ${BORDER_ZONE_KM} km"' -o ${out}/border-zone.geojson format=geojson rfc7946 precision=0.0001 force`);

/** Outer rings of all parts of an area (Polygon or MultiPolygon). */
function outerRingsOf(file) {
  const collection = JSON.parse(readFileSync(file, 'utf8'));
  const rings = [];
  for (const feature of collection.features ?? [collection]) {
    const geometry = feature.geometry ?? feature;
    const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
    for (const polygon of polygons) rings.push(polygon[0]);
  }
  return rings;
}

// RFC 7946: outer ring counterclockwise, holes clockwise; the rings of mapshaper are counterclockwise.
const hole = (ring) => [...ring].reverse();
function writeArea(file, name, outer, holes) {
  writeFileSync(`${out}/${file}`, JSON.stringify({
    type: 'FeatureCollection',
    features: [{ type: 'Feature', properties: { name }, geometry: { type: 'Polygon', coordinates: [outer, ...holes.map(hole)] } }],
  }));
}

// Mask: rectangle around the world with the border zone as a hole (opaque); border shade: the border zone with
// DACH as holes (slightly dimmed), so that the border of DACH stays recognizable.
const dachRings = outerRingsOf(`${out}/dach.geojson`);
const zoneRings = outerRingsOf(`${out}/border-zone.geojson`);
const world = [[-180, -85], [180, -85], [180, 85], [-180, 85], [-180, -85]];
writeArea('dach-mask.geojson', 'World without border zone', world, zoneRings);
writeArea('border-shade.geojson', 'Border zone without DACH', zoneRings[0], dachRings);
console.log(`Regions, DACH outline, border zone (${BORDER_ZONE_KM} km) and masks written to ${out} `
  + `(${dachRings.length} DACH parts, ${zoneRings.length} border zone parts).`);
