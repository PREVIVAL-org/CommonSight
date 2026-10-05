// Reference points for AustriaLambertProjection (Architecture 4.10, Q-W-AT-04).
//
//   docker compose run --rm node --prefix /src/tools/projection install
//   docker compose run --rm node --prefix /src/tools/projection run reference
//
// proj4js computes with the same definition as PROJ (EPSG:31287 with 7-parameter Helmert to WGS84).
// The result is stored as a test case file in the backend; the tests allow 0.5 m deviation.

import proj4 from 'proj4';
import { writeFileSync } from 'node:fs';

const EPSG_31287 = '+proj=lcc +lat_0=47.5 +lon_0=13.3333333333333 +lat_1=49 +lat_2=46 +x_0=400000 +y_0=400000 '
  + '+ellps=bessel +towgs84=577.326,90.129,463.919,5.137,1.474,5.297,2.4232 +units=m +no_defs';

const places = [
  ['Wien Stephansdom', 16.37338, 48.20849],
  ['Graz Schlossberg', 15.43748, 47.07603],
  ['Linz Hauptplatz', 14.28583, 48.30639],
  ['Salzburg Dom', 13.04651, 47.79767],
  ['Innsbruck Goldenes Dachl', 11.39333, 47.26861],
  ['Bregenz Hafen', 9.74216, 47.50478],
  ['Klagenfurt Lindwurm', 14.30862, 46.62453],
  ['Eisenstadt Schloss', 16.51816, 47.84811],
  ['St. Pölten town hall', 15.62311, 48.20432],
  ['Großglockner', 12.69403, 47.07458],
  ['Gmünd, northern edge', 14.98, 48.77],
  ['Bad Radkersburg, south-eastern edge', 15.99, 46.69],
];

const cases = places.map(([name, lon, lat]) => {
  const [easting, northing] = proj4('WGS84', EPSG_31287, [lon, lat]);
  return { name, easting: Number(easting.toFixed(3)), northing: Number(northing.toFixed(3)), lon, lat };
});

const target = new URL('../../backend/tests/Fixtures/projection/epsg31287-reference.json', import.meta.url);
writeFileSync(target, JSON.stringify({
  description: 'Reference points EPSG:31287 → WGS84, generated with proj4js (tools/projection/reference.mjs). Tolerance 0.5 m.',
  definition: EPSG_31287,
  generatedWith: `proj4js ${proj4.version ?? ''}`.trim(),
  cases,
}, null, 2) + '\n');
console.log(`${cases.length} reference points written`);
