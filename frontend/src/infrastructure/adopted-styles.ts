/**
 * Attaches stylesheets to the shadow root of the element (Architecture 9.5). The element keeps its shadow root when it
 * is moved in the page (disconnected and connected again), so the sheets of a group (e.g. the element's own, the map's)
 * replace those of the same group instead of piling up.
 */
const groups = new WeakMap<ShadowRoot, Map<string, CSSStyleSheet[]>>();

export function adoptStyles(
  shadow: ShadowRoot,
  group: string,
  cssTexts: readonly string[],
  position: 'first' | 'last',
): void {
  const byGroup = groups.get(shadow) ?? new Map<string, CSSStyleSheet[]>();
  groups.set(shadow, byGroup);
  const previous = byGroup.get(group) ?? [];
  const sheets = cssTexts.map((css) => {
    const sheet = new CSSStyleSheet();
    sheet.replaceSync(css);
    return sheet;
  });
  byGroup.set(group, sheets);
  const others = shadow.adoptedStyleSheets.filter((sheet) => !previous.includes(sheet));
  shadow.adoptedStyleSheets = position === 'first' ? [...sheets, ...others] : [...others, ...sheets];
}
