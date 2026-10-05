/**
 * Resolves CSS variables to concrete colors via an invisible helper element, because MapLibre does not
 * understand variables (doc/ARCHITECTURE.md 10).
 */

export interface ColorResolver {
  /** Returns the computed color value for each variable name (e.g. `--_map-land`). */
  resolve(names: readonly string[]): Record<string, string>;
}

export function createColorResolver(container: HTMLElement): ColorResolver {
  return {
    resolve: (names) => {
      const probe = container.ownerDocument.createElement('span');
      probe.setAttribute('aria-hidden', 'true');
      probe.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden;visibility:hidden;';
      container.append(probe);
      const view = container.ownerDocument.defaultView;
      const colors: Record<string, string> = {};
      for (const name of names) {
        probe.style.color = `var(${name})`;
        colors[name] = view?.getComputedStyle(probe).color ?? '';
      }
      probe.remove();
      return colors;
    },
  };
}
