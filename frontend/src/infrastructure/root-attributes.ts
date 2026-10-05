/**
 * Sets theme and embedding mode as attributes on the root node in the shadow root, which the stylesheets
 * key on (T-05, T-10). The theme in effect is also mirrored on the element itself (data-theme), so that the host page
 * can give its --cs-* variables a value per theme, also when the visitor chose it with the switch of the header.
 */

export interface RootAttributes {
  apply(values: { theme: 'light' | 'dark'; embedded: boolean }): void;
}

export function createRootAttributes(root: HTMLElement, host: HTMLElement): RootAttributes {
  return {
    apply: ({ theme, embedded }) => {
      root.dataset.theme = theme;
      host.dataset.theme = theme;
      root.dataset.embedded = String(embedded);
    },
  };
}
