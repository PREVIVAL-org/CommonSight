/**
 * CommonSight: situation level per country for a box of another site, e.g. a WoltLab sidebar box (concept: situation
 * level, L-D5). A classic script, no module, so that the host page loads it without CORS; the data comes from
 * api/situation.php, which the host page may read when it is named in situation.allowOrigins.
 *
 *   <div data-commonsight-situation="https://lagezentrum.example.org/"></div>
 *   <script src="https://lagezentrum.example.org/box/situation.js" defer></script>
 *
 * The list uses the classes of WoltLab's sidebar lists (sidebarItemList, box24, sidebarItemTitle), so it looks like
 * the boxes of the forum; elsewhere it falls back to the few inline styles below. Only textContent, no HTML from the data.
 */
(function () {
  'use strict';

  var REFRESH_MS = 5 * 60 * 1000;
  var COUNTRIES = [
    { code: 'DE', name: 'Deutschland', regions: 'Bundesländern' },
    { code: 'AT', name: 'Österreich', regions: 'Bundesländern' },
    { code: 'CH', name: 'Schweiz', regions: 'Kantonen' },
  ];
  // Fixed colours like the warnings of the map (T-07); grey = unknown, which is no all-clear.
  var LEVELS = {
    1: { label: 'Keine besondere Lage', color: '#7cc18a' },
    2: { label: 'Erhöhte Lage', color: '#e8c880' },
    3: { label: 'Erhebliche Lage', color: '#fa9966' },
    4: { label: 'Hohe Lage', color: '#f66f6f' },
    5: { label: 'Massive Lage', color: '#c38be0' },
  };
  var UNKNOWN = { label: 'Lage unbekannt', color: '#94a3b8' };
  var GRADES = [
    { key: 'violet', name: 'Violett' },
    { key: 'red', name: 'Rot' },
    { key: 'orange', name: 'Orange' },
    { key: 'yellow', name: 'Gelb' },
  ];

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  /** What the level rests on: the highest grade and how many regions it reaches. */
  function detail(country, situation) {
    if (!situation || situation.level === null) {
      return situation && situation.status === 'pending'
        ? 'Daten werden abgerufen …'
        : 'Warnquelle derzeit nicht erreichbar';
    }
    // Partial: a source or some warnings are missing, like "unvollständig" on the map (U-23).
    var suffix = situation.status === 'partial' ? ' · unvollständig' : '';
    for (var i = 0; i < GRADES.length; i++) {
      var grade = situation.grades && situation.grades[GRADES[i].key];
      if (grade && grade.warnings > 0) {
        return (
          (grade.regions > 0
            ? GRADES[i].name +
              ' in ' +
              grade.regions +
              ' von ' +
              situation.regionCount +
              ' ' +
              country.regions
            : GRADES[i].name + ', ohne Gebietszuordnung') + suffix
        );
      }
    }
    return 'Keine amtlichen Warnungen' + suffix;
  }

  function row(base, country, situation) {
    var level = situation && situation.level !== null ? LEVELS[situation.level] : null;
    var look = level || UNKNOWN;
    var item = el('li', 'box24');
    item.style.cssText = 'display:flex;gap:10px;align-items:flex-start;list-style:none;margin:0 0 10px';
    var badge = el('span', '', level ? String(situation.level) : '?');
    badge.title = look.label;
    badge.style.cssText =
      'flex:0 0 24px;height:24px;border-radius:4px;background:' +
      look.color +
      ';color:#1a1a1a;font-weight:700;font-size:13px;line-height:24px;text-align:center';
    var text = el('div', 'sidebarItemTitle');
    var title = el('h3');
    var link = el('a', '', country.name + ': ' + look.label);
    link.href = base;
    title.appendChild(link);
    text.appendChild(title);
    text.appendChild(el('small', '', detail(country, situation)));
    item.appendChild(badge);
    item.appendChild(text);
    return item;
  }

  function render(container, base, data) {
    var list = el('ul', 'sidebarItemList');
    list.style.cssText = 'margin:0;padding:0';
    COUNTRIES.forEach(function (country) {
      list.appendChild(row(base, country, data && data.countries ? data.countries[country.code] : null));
    });
    var note = el(
      'small',
      '',
      'Aus den amtlichen Warnungen, Stufe 1 bis 5. Amtliche Anweisungen haben Vorrang.',
    );
    note.style.cssText = 'display:block;opacity:.75';
    // Not replaceChildren: older Safari and WebViews of forum visitors lack it.
    while (container.firstChild) container.removeChild(container.firstChild);
    container.appendChild(list);
    container.appendChild(note);
  }

  /** One request per CommonSight address, rendered into all its boxes on the page. */
  function load(base, containers) {
    function renderAll(data) {
      containers.forEach(function (container) {
        render(container, base, data);
      });
    }
    fetch(base + 'api/situation.php', { credentials: 'omit' })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(renderAll, function () {
        // Unreachable: every country unknown, never "nothing".
        renderAll(null);
      });
  }

  function start() {
    var byBase = {};
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-commonsight-situation]'),
      function (container) {
        var base = container.getAttribute('data-commonsight-situation') || '/';
        if (base.charAt(base.length - 1) !== '/') base += '/';
        (byBase[base] = byBase[base] || []).push(container);
      },
    );
    Object.keys(byBase).forEach(function (base) {
      load(base, byBase[base]);
      setInterval(function () {
        if (document.visibilityState === 'visible') load(base, byBase[base]);
      }, REFRESH_MS);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
