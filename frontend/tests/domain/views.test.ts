/**
 * View models per kind (Architecture 9.8, U-80, U-83): the same details for card, detail sheet and tooltip.
 */
import { describe, expect, it } from 'vitest';
import type { Item, Snapshot } from '../../src/contract/types';
import type { ItemView } from '../../src/domain/views/item-view';
import { toItemView } from '../../src/domain/views/item-view';
import { toTableRow } from '../../src/domain/views/table-row-view';
import { truncate } from '../../src/domain/views/truncate';
import { SEVERITY_COLORS } from '../../src/theme/fixed';
import { snapshot } from '../support/fixtures';
import { viewDeps } from '../support/deps';

function viewsOf(data: Snapshot): ItemView[] {
  return data.items.map((item: Item) =>
    toItemView(item, { layer: data.layer, source: data.source }, viewDeps()),
  );
}

function only<K extends ItemView['kind']>(views: ItemView[], kind: K): Extract<ItemView, { kind: K }>[] {
  return views.filter((view): view is Extract<ItemView, { kind: K }> => view.kind === kind);
}

describe('warning view', () => {
  const [storm, wind, heat] = only(viewsOf(snapshot('warnings-AT')), 'warning');

  it('shows level in fixed colour and as text, hazard, area and validity', () => {
    expect(storm?.badge).toEqual({ color: SEVERITY_COLORS.Severe, label: 'Warnstufe Orange', dashed: false });
    expect(storm?.hazard).toBe('Gewitter');
    expect(storm?.area).toBe('Wien, Niederösterreich');
    expect(storm?.validity).toBe('Gültig ab 28.09., 12:00 bis 29.09., 08:00');
    expect(storm?.base.category).toBe('Unwetter');
    expect(storm?.base.time).toBe('28.09., 11:30');
    expect(storm?.base.source).toBe('GeoSphere Austria');
  });

  it('lists sections with headings and a shortened first section for the compact card', () => {
    expect(storm?.sections.map((section) => section.heading)).toEqual(['Beschreibung', 'Empfehlungen']);
    expect(storm?.summary).toContain('Heftige Gewitter');
    expect(wind?.sections).toEqual([]);
    expect(wind?.summary).toBeNull();
  });

  it('falls back to severity colours without awareness and marks missing time', () => {
    expect(heat?.badge.color).toBe(SEVERITY_COLORS.Moderate);
    expect(heat?.badge.label).toBe('Mäßig');
    expect(heat?.base.time).toBe('kein Datenstand');
    expect(heat?.base.hasLocation).toBe(false);
  });
});

describe('measurement view', () => {
  const [cologne, dresden, passau, hamburg] = only(viewsOf(snapshot('water-DE')), 'measurement');

  it('shows value with unit and reference, current assessment with label and basis', () => {
    expect(cologne?.value).toBe('612 cm');
    expect(cologne?.reference).toBe('über lokalem Pegelnullpunkt');
    expect(cologne?.quantity).toBe('Wasserstand');
    expect(cologne?.assessment.level).toBe('elevated');
    expect(cologne?.assessment.badge.label).toBe('MHW erreicht / überschritten');
    expect(cologne?.assessment.basis).toContain('mittlerer Hochwasserstand');
    expect(cologne?.assessment.origin).toBe('Einstufung der Quelle');
  });

  it('turns an expired assessment grey with "Datenstand veraltet" and the last assessment (B-03)', () => {
    expect(dresden?.assessment.level).toBe('unknown');
    expect(dresden?.assessment.badge).toEqual({
      color: '#94a3b8',
      label: 'Datenstand veraltet',
      dashed: true,
    });
    expect(dresden?.assessment.previous).toBe('Letzte Einordnung: HSW erreicht / überschritten');
    expect(dresden?.facts).toEqual([{ label: 'Abfluss', value: '1.234,5 m³/s' }]);
  });

  it('keeps the backend label for unknown assessments and shows the previous one', () => {
    expect(passau?.assessment.badge.label).toBe('Keine aktuelle Einordnung');
    expect(passau?.assessment.basis).toBe('Messwert älter als 6 Stunden.');
    expect(passau?.assessment.previous).toBe('Letzte Einordnung: Unter MHW');
  });

  it('treats an assessment without validUntil as "Keine aktuelle Einordnung"', () => {
    expect(hamburg?.assessment.level).toBe('unknown');
    expect(hamburg?.assessment.badge.label).toBe('Keine aktuelle Einordnung');
  });

  it('formats dose rates with up to three decimals (I-04)', () => {
    const [vienna] = only(viewsOf(snapshot('radiation-AT')), 'measurement');
    expect(vienna?.value).toBe('0,099 µSv/h');
    expect(vienna?.reference).toBe('Mittelungsdauer 1 h');
    expect(vienna?.assessment.origin).toBe('Darstellungsschwelle, keine amtliche Stufe');
  });
});

describe('model value view', () => {
  it('shows temperature with one decimal, summary, facts and the model hint', () => {
    const [vienna] = only(viewsOf(snapshot('weather-AT')), 'modelValue');
    expect(vienna?.value).toBe('18,5 °C');
    expect(vienna?.shortValue).toBe('18°');
    expect(vienna?.summary).toBe('Bewölkt');
    expect(vienna?.facts[0]).toEqual({ label: 'Wind', value: '12,3 km/h' });
    expect(vienna?.modelHint).toBe('Modellwert, keine Messung');
  });

  it('shows the AQI without decimals', () => {
    const [air] = only(viewsOf(snapshot('air-AT')), 'modelValue');
    expect(air?.value).toBe('47 EU-AQI');
    expect(air?.summary).toBe('Mäßig');
  });
});

describe('earthquake, traffic, index and news views', () => {
  it('shows magnitude, depth and place', () => {
    const [quake] = only(viewsOf(snapshot('nature-AT')), 'earthquake');
    expect(quake).toMatchObject({
      magnitude: 'M 3,2',
      depth: '10,4 km',
      place: '10 km NE of Innsbruck, Austria',
    });
    expect(quake?.base.category).toBe('Naturgefahren');
  });

  it('shows road, type, start and description and the source language', () => {
    const [notice] = only(viewsOf(snapshot('traffic-AT')), 'trafficNotice');
    expect(notice).toMatchObject({
      road: 'A1 Westautobahn',
      noticeType: 'Baustelle',
      start: '28.09., 08:00',
    });
    expect(notice?.base.lang).toBe('en');
  });

  it('shows the value on its scale with history', () => {
    const [kp, g] = only(viewsOf(snapshot('space-global')), 'index');
    expect(kp?.value).toBe('Kp 5,33');
    expect(kp?.scaleText).toBe('5,33 von 9');
    expect(kp?.ratio).toBeCloseTo(5.33 / 9);
    expect(kp?.history).toHaveLength(8);
    expect(g?.scaleName).toBe('G 0-5');
    expect(g?.description).toBe('NOAA-Skala G0-G5. Globale Einordnung, keine lokale Empfangsprognose.');
  });

  it('shows topic and feed name', () => {
    const [first] = only(viewsOf(snapshot('news-global')), 'news');
    expect(first).toMatchObject({ topic: 'Unwetter', feed: 'tagesschau.de' });
    expect(first?.base.category).toBe('Unwetter');
  });
});

describe('table rows', () => {
  it('combine value and assessment of measurements (U-33)', () => {
    const [row] = viewsOf(snapshot('water-DE')).map(toTableRow);
    expect(row).toMatchObject({
      place: 'KÖLN · RHEIN',
      value: '612 cm',
      time: '28.09., 13:45',
      hasLocation: true,
    });
    expect(row?.badge?.label).toBe('MHW erreicht / überschritten');
  });
});

describe('truncate', () => {
  it('cuts at a word boundary', () => {
    expect(truncate('eins zwei drei vier', 12)).toBe('eins zwei ...');
    expect(truncate('kurz', 12)).toBe('kurz');
  });
});
