/**
 * Holds the frontend's own German UI texts (I-01); backend texts live in contract/messages.
 */
export interface PluralText {
  one: string;
  other: string;
}

export type UiText = string | PluralText;

export const uiTextsDe = {
  'app.place': '{country} · {region}',
  'app.lastFetch': 'Abgerufen: {time}',
  'app.sources': 'Datenquellen',
  'app.version': 'Version {version}',
  'app.poweredBy': 'Powered by',
  'app.poweredByName': 'CommonSight',

  'header.theme': 'Darstellung: {mode}',
  'header.themeNext': '{label} (wechseln zu {next})',
  'header.theme.auto': 'Automatisch',
  'header.theme.light': 'Hell',
  'header.theme.dark': 'Dunkel',

  'members.title': '{name} ist für Mitglieder von {community}',
  'members.communityFallback': 'der Gemeinschaft',
  'members.text':
    'Die Lagekarte steht den angemeldeten Mitgliedern von {community} zur Verfügung. Melde dich dort an und öffne diese Seite danach erneut.',
  'members.offer.map':
    'Amtliche Warnungen, Wetter, Luftqualität, Pegel und Strahlung für Deutschland, Österreich und die Schweiz auf einer Karte',
  'members.offer.border': 'Messpunkte der Nachbarländer im Grenzgebiet',
  'members.offer.sources': 'Jeder Wert mit Quelle, Messzeit und Link zur amtlichen Stelle',
  'members.login': 'Anmelden',
  'members.register': 'Kostenlos registrieren',
  'members.reloadBefore': 'Schon angemeldet? Dann',
  'members.reload': 'Seite neu laden',

  'place.label': 'Land / Region',
  'place.all': 'Alle Länder',
  'place.allName': 'Deutschland, Österreich und Schweiz',

  'view.tabs': 'Ansicht wählen',
  'view.overview': 'Lagekarte',
  'view.measurements': 'Messwerte',
  'view.events': 'Warnungen & Ereignisse',

  'region.whole': 'Ganz {country}',
  'region.matched': {
    one: '{region} · {count} regional zugeordneter Eintrag',
    other: '{region} · {count} regional zugeordnete Einträge',
  },

  'offline.banner': 'Keine Internetverbindung. Angezeigte Daten können veraltet sein.',

  'layers.title': 'Ebenen',
  'layers.count': '{active} / {total} aktiv',
  'layers.show': '{layer} ansehen',
  'layers.other': 'Weitere Lagedaten ohne Karte',
  'layers.hint': 'Orte und Messpunkte anklicken, um Details und Datenstand zu sehen.',
  'layers.sourceStatus': 'Quellenstatus',
  'layers.connected': '{count} von {total} Quellen verbunden',
  'layers.unreachable': '{count} derzeit nicht erreichbar',

  'availability.ok': 'Quelle verbunden',
  'availability.partial': 'Teilweise verfügbar',
  'availability.setup': 'Zugang benötigt',
  'availability.error': 'Nicht verfügbar',
  'availability.loading': 'Wird geladen',
  'availability.pending': 'Daten werden abgerufen ...',

  'notice.close': 'Kartenhinweis schließen',
  'notice.show': 'Kartenhinweis einblenden',
  'notice.showEntries': 'Anzeigen',

  'map.aria': 'Interaktive Karte für {place}',
  'map.error': 'Die Karte konnte nicht geladen werden. Die Datenlisten bleiben verfügbar.',
  'map.tools': 'Kartenwerkzeuge',
  'map.resetCountry': 'Landesansicht zurücksetzen',
  'map.resetRegion': 'Regionsansicht zurücksetzen',
  'map.locate': 'Karte auf meinen Standort zentrieren',
  'map.fullscreen': 'Karte im Vollbild',
  'map.outlineLoading': 'Regionsgrenze wird geladen ...',
  'map.outlineError': 'Regionsgrenze konnte nicht geladen werden. Der Regionsfilter bleibt wirksam.',
  'map.retry': 'Erneut versuchen',
  'map.zoomIn': 'Hineinzoomen',
  'map.zoomOut': 'Herauszoomen',
  'map.attribution': 'Quellenangaben der Karte ein- oder ausblenden',
  'map.twoFingers': 'Karte mit zwei Fingern verschieben',

  'legend.title': 'Kartenlegende',
  'legend.activeLayers': { one: '{count} Ebene aktiv', other: '{count} Ebenen aktiv' },
  'live.countdown': 'Aktualisierung in {seconds} s',
  'live.countdownShort': '{seconds} s',
  'live.offlineShort': 'Offline',
  'live.auto': 'Automatische Aktualisierung',
  'live.syncing': 'Wird aktualisiert ...',
  'live.offline': 'Offline · Aktualisierung pausiert',
  'live.failed': 'Aktualisierung fehlgeschlagen',
  'live.failedRetry': 'Aktualisierung fehlgeschlagen · neuer Versuch in {seconds} s',
  'live.pending': 'Wird geladen ...',
  'live.hint':
    'Die Daten werden automatisch jede Minute abgeglichen, solange die Seite geöffnet ist. Neu laden ist nicht nötig.',
  'live.hintSince':
    'Die Daten werden automatisch jede Minute abgeglichen, solange die Seite geöffnet ist. Neu laden ist nicht nötig. Letzter Abgleich: {time}',
  'legend.open': 'Legende: Farben der Messpunkte',
  'legend.link': 'Legende',
  'legend.colors': 'Farben auf der Karte',
  'legend.orange': 'Orange',
  'legend.red': 'Rot',
  'legend.gray': 'Grau',
  'legend.gray.text': 'Einordnung fehlt, Quelle meldet Störung oder Datenstand ist veraltet.',
  'legend.gray.noAllClear': 'Ein grauer Punkt ist keine Entwarnung.',
  'legend.points':
    'Markierte Punkte sind größer und zeigen beim Berühren oder Anklicken ihre Einordnung mit Quelle und Datenstand. Maßgeblich bleiben die amtlichen Warnungen.',
  'legend.empty': 'Keine Messpunkte mit Farbeinordnung auf der Karte.',
  'legend.empty.layers': 'Eine Farbeinordnung haben: {layers}.',

  'news.topics': 'Thema filtern',
  'news.topic.all': 'Alle',
  'news.empty': 'Keine passenden Meldungen in den geladenen Feeds der letzten 72 Stunden.',
  'news.error': 'Nachrichten sind gerade nicht erreichbar.',
  'news.openSource': 'Nachrichtenquelle öffnen',
  'news.footer': { one: 'Aktualisierung jede Minute', other: 'Aktualisierung alle {count} Minuten' },
  'news.partial': 'einzelne Feeds fehlen',
  'news.loading': 'Nachrichten werden geladen ...',

  'metrics.title': 'Messwerte im Überblick',
  'metrics.noData': 'Keine Daten',
  'metrics.loading': 'Wird geladen ...',
  'metrics.open': 'Details öffnen',

  'list.layerSelect': 'Datenquelle auswählen',
  'list.filter': 'Einträge filtern',
  'list.filterPlaceholder': 'Ort oder Stichwort ...',
  'list.count': { one: '{count} Eintrag', other: '{count} Einträge' },
  'list.sourceDate': 'Quelldatum: {time}',
  'border.toggle': 'Grenzgebiet',
  'border.hint':
    'Messpunkte der Nachbarländer bis {km} km jenseits der Grenze auf der Karte und in den Listen anzeigen',
  'list.border': { one: 'Grenzgebiet: {count} Eintrag', other: 'Grenzgebiet: {count} Einträge' },
  'list.borderHint':
    'Einträge jenseits der Grenze bis {km} km von {place}. Sie zählen nicht zu den Angaben oben.',
  'list.coverage': 'Abdeckung & Quelle',
  'list.more': 'Weitere Einträge anzeigen ({count})',
  'list.viewMode': 'Darstellung',
  'list.cards': 'Boxen',
  'list.table': 'Tabelle',
  'list.lastError': 'Letzte Aktualisierung fehlgeschlagen ({time}). Angezeigt wird der letzte gute Stand.',
  'list.loadError': 'Die Quelle ist derzeit nicht erreichbar. Das ist keine Entwarnung.',
  'list.stale': 'Der Datenstand ist älter als für diese Ebene erwartet.',
  'list.overregional': 'Diese Ebene bleibt überregional und wird nicht nach Region gefiltert.',
  'list.issues': 'Hinweise zur Vollständigkeit',

  'empty.setup': 'Datenzugang noch nicht eingerichtet',
  'empty.error': 'Quelle derzeit nicht verfügbar',
  'empty.noMatch': 'Keine passenden Einträge',
  'empty.none': 'Keine Einträge im abgefragten Umfang',
  'empty.pending': 'Daten werden abgerufen ...',
  'empty.loading': 'Daten werden geladen ...',
  'empty.openSource': 'Originalquelle öffnen',

  'table.caption': 'Messwerte in Tabellenform',
  'table.place': 'Ort / Station',
  'table.value': 'Wert',
  'table.time': 'Datenstand',
  'table.source': 'Quelle',
  'table.map': 'Karte',

  'card.showOnMap': 'Auf Karte anzeigen',
  'card.openSource': 'Originalquelle öffnen',
  'card.details': 'Details öffnen: {title}',
  'card.validity': 'Gültig ab {from} bis {until}',
  'card.validFrom': 'Gültig ab {from}',
  'card.validUntil': 'Gültig bis {until}',
  'card.modelHint': 'Modellwert, keine Messung',
  'card.sourceValue': 'Originalstufe der Quelle: {value}',
  'card.measuredAt': 'Messzeit: {time}',
  'card.previous': 'Letzte Einordnung: {label}',
  'card.origin.source': 'Einstufung der Quelle',
  'card.origin.display': 'Darstellungsschwelle, keine amtliche Stufe',
  'card.magnitude': 'Magnitude',
  'card.magnitudeValue': 'M {value}',
  'card.depth': 'Tiefe',
  'card.depthValue': '{value} km',
  'card.place': 'Ort',
  'card.road': 'Strecke',
  'card.noticeType': 'Art',
  'card.start': 'Beginn',
  'card.scaleValue': '{value} von {max}',
  'card.history': 'Verlauf',
  'card.language': 'Originaltext ({lang})',
  'card.source': 'Quelle: {source}',

  'severity.Extreme': 'Extrem',
  'severity.Severe': 'Schwer',
  'severity.Moderate': 'Mäßig',
  'severity.Minor': 'Gering',
  'severity.Unknown': 'Unbekannte Stufe',
  'awareness.yellow': 'Warnstufe Gelb',
  'awareness.orange': 'Warnstufe Orange',
  'awareness.red': 'Warnstufe Rot',
  'section.description': 'Beschreibung',
  'section.situation': 'Wetterlage',
  'section.impact': 'Auswirkungen',
  'section.advice': 'Empfehlungen',
  'section.update': 'Aktualisierung',

  'assessment.stale': 'Datenstand veraltet',
  'assessment.none': 'Keine aktuelle Einordnung',

  'sheet.close': 'Schließen',
  'sheet.sources.title': 'Datenquellen & Abdeckung',
  'sheet.sources.subtitle': '{country} · Quellenstatus und Aktualität',
  'sheet.sources.rhythm':
    'Die Karte fragt jede Minute den Stand aller Ebenen ab und lädt nur geänderte Daten. Die Quellen werden in diesen Abständen abgerufen; sie selbst können größere Zeitabstände haben:',
  'sheet.sources.interval': { one: '{layer}: jede Minute', other: '{layer}: alle {count} Minuten' },
  'sheet.sources.fetched': 'Abgerufen: {time}',
  'sheet.sources.lastFetch': 'letzter Abruf: {time}',
  'sheet.sources.lastFetchStale': 'letzter Abruf: {time}, veraltet',
  'sheet.sources.lastFetchFailed': 'letzter Abruf: {time}, fehlgeschlagen',
  'sheet.sources.sourceDate': 'Quelldatum: {time}',
  'sheet.sources.viewData': 'Daten ansehen',
  'sheet.sources.dataUse': 'Datennutzung',
  'sheet.sources.originals':
    'Die Daten werden nach den Nutzungsbedingungen der jeweiligen Anbieter verwendet; Rechte an Meldungen und Überschriften liegen bei den Anbietern. Maßgeblich sind die verlinkten Originalmeldungen.',
  'sheet.sources.privacy':
    'Daten und Karte kommen vom eigenen Server dieses Angebots. Beim Anzeigen erhalten keine Drittanbieter Verbindungsdaten.',
  'sheet.layer.subtitle': '{country} · Quelle, Datenstand und Einordnung',
  'sheet.layer.openOfficial': 'Offizielle Quelle öffnen',
  'sheet.layer.moreOfficial': 'Weitere amtliche Informationen',
  'sheet.layer.limit':
    'Die Liste zeigt die ersten 150 Einträge. Weitere Messpunkte sind auf der Karte verfügbar.',
  'sheet.layer.empty': 'Keine Einträge im abgefragten Umfang. Dies ist keine allgemeine Entwarnung.',
  'sheet.item.noText':
    'Die vollständige Warnmeldung mit Verhaltenshinweisen findest du in der offiziellen Quelle.',
  'sheet.item.openFull': 'Vollständige Originalquelle öffnen',
  'sheet.item.allEntries': 'Alle Einträge dieser Ebene',
  'sheet.item.missing': 'Dieser Eintrag ist im aktuellen Datenstand nicht mehr enthalten.',
  'sheet.unassigned.title': 'Ohne Ortszuordnung',
  'sheet.unassigned.link': 'Ohne Ortszuordnung ({count})',
  'sheet.unassigned.explain':
    'Für diese Einträge lässt sich nicht sicher bestimmen, ob sie {region} betreffen. Sie werden deshalb nicht als regionale Treffer gezählt, bleiben aber hier erreichbar.',
  'sheet.unassigned.check': 'Originalquelle prüfen',
  'sheet.unassigned.empty': 'Keine Einträge ohne sichere Ortszuordnung.',

  'note.region':
    'Region {region}: {matched} zugeordnete Einträge, {unassigned} ohne sichere Regionalzuordnung.',

  'toast.region': 'Statusmeldungen',
  'toast.close': 'Meldung schließen',
  'toast.located': 'Karte auf deinen Standort zentriert',
  'toast.locateUnsupported': 'Standort ist in diesem Browser nicht verfügbar',
  'toast.locateFailed': 'Standort konnte nicht ermittelt werden',
  'toast.fullscreenUnsupported': 'Vollbild wird von diesem Browser nicht unterstützt',
  'toast.mapError': 'Die Karte konnte nicht geladen werden',

  'time.none': 'kein Datenstand',
} satisfies Record<string, UiText>;

export type UiTextKey = keyof typeof uiTextsDe;
