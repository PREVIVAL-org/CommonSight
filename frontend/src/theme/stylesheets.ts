/**
 * Provides the element's own stylesheets as text so that the element attaches them via adoptedStyleSheets
 * (Architecture 9.5). The color of each layer comes from the registry: `--_layer-<id>` falls back to the color the
 * layer package declares and can be overridden by the host page with `--cs-layer-<id>` (T-06).
 */
import { LAYER_IDS, layerMeta } from '../contract/master-data';
import cardsCss from './cards.css?inline';
import componentsCss from './components.css?inline';
import layoutCss from './layout.css?inline';
import tokensCss from './tokens.css?inline';

/** `.root { --_layer-water: var(--cs-layer-water, #398ed4); … }` for every layer of the registry. */
export function layerColorsCss(): string {
  const lines = LAYER_IDS.map((id) => `  --_layer-${id}: var(--cs-layer-${id}, ${layerMeta(id).color});`);
  return `.root {\n${lines.join('\n')}\n}\n`;
}

export const ELEMENT_STYLESHEETS: readonly string[] = [
  tokensCss,
  layerColorsCss(),
  layoutCss,
  componentsCss,
  cardsCss,
];
