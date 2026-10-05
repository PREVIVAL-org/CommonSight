/**
 * Frontend dependency rules (Architecture 1.3.3, 9.1, V13). A violation breaks the build.
 */
const core = '^src/(domain|state|map|application|infrastructure|i18n|theme|contract|sdk)/';
const uiLibraries = 'node_modules/(react|react-dom|@radix-ui|lucide-react|scheduler)/';

module.exports = {
  forbidden: [
    {
      name: 'no-circular',
      severity: 'error',
      comment: 'Cycles are forbidden (1.3.3).',
      from: {},
      to: { circular: true },
    },
    {
      name: 'core-not-to-ui',
      severity: 'error',
      comment: 'domain, state, map, application and the other core layers never import from ui (9.1).',
      from: { path: core },
      to: { path: '^src/ui/' },
    },
    {
      name: 'react-only-in-ui',
      severity: 'error',
      comment:
        'Only ui/ imports React and React libraries (E10, 2.2); besides it the generated icon registry of the layers, used by ui/ only (L-D8).',
      from: { pathNot: '^src/(ui/|generated/layer-icons\\.ts$)' },
      to: { path: uiLibraries },
    },
    {
      name: 'maplibre-only-in-map-view',
      severity: 'error',
      comment:
        'map-view.ts is the only place that calls MapLibre and pmtiles (9.1). Type imports are allowed.',
      from: { pathNot: '^src/map/map-view\\.ts$' },
      to: { path: 'node_modules/(maplibre-gl|pmtiles)/', dependencyTypesNot: ['type-only'] },
    },
    {
      name: 'domain-is-pure',
      severity: 'error',
      comment: 'Domain logic depends only on contract, i18n and theme (1.3.2).',
      from: { path: '^src/domain/' },
      to: { path: '^src/(state|map|application|infrastructure|ui)/' },
    },
    {
      name: 'state-without-io',
      severity: 'error',
      comment: 'The store only changes state; no I/O, no map, no flow control (9.1).',
      from: { path: '^src/state/' },
      to: { path: '^src/(map|application|infrastructure|ui)/' },
    },
    {
      name: 'application-not-to-map-or-ui',
      severity: 'error',
      comment:
        'Flow control knows neither the map nor the presentation; the map follows the state via map-binding.',
      from: { path: '^src/application/' },
      to: { path: '^src/(map|ui)/' },
    },
    {
      name: 'infrastructure-only-contract',
      severity: 'error',
      comment: 'I/O talks to exactly one external system and knows only the contract types.',
      from: { path: '^src/infrastructure/' },
      to: { path: '^src/(domain|state|map|application|ui|i18n|theme)/' },
    },
    {
      name: 'pure-map-modules',
      severity: 'error',
      comment: 'map/layers, style and tooltip are pure (9.1).',
      from: { path: '^src/map/(layers/|style\\.ts|tooltip\\.ts)' },
      to: { path: '^src/(state|application|infrastructure|ui)/|^src/map/map-(view|binding)\\.ts$' },
    },
    {
      name: 'model-and-text-leaves',
      severity: 'error',
      comment: 'contract, i18n and theme depend on no higher layer.',
      from: { path: '^src/(contract|i18n|theme)/' },
      to: { path: '^src/(domain|state|map|application|infrastructure|ui)/' },
    },
    {
      name: 'ui-without-io',
      severity: 'error',
      comment: 'Components load no data and call neither I/O nor flow control nor the map (9.1).',
      from: { path: '^src/ui/' },
      to: { path: '^src/(infrastructure|application|map)/' },
    },
    {
      name: 'plugins-only-sdk-and-contract',
      severity: 'error',
      comment:
        'A layer package uses only the plugin API of the core, the contract and its own modules, never another package (layers as plugins, L3).',
      from: { path: '^\\.\\./plugins/layers/([^/]+)/', pathNot: '\\.test\\.ts$' },
      to: { pathNot: '^(src/sdk/|src/contract/|\\.\\./contract/|\\.\\./plugins/layers/$1/)' },
    },
    {
      name: 'plugin-tests-only-sdk-contract-and-vitest',
      severity: 'error',
      comment:
        'The tests of a layer package use the plugin API with its test aids, the contract (e.g. its fixtures), their own package and vitest.',
      from: { path: '^\\.\\./plugins/layers/([^/]+)/.*\\.test\\.ts$' },
      to: {
        pathNot:
          '^(src/sdk/|src/contract/|\\.\\./contract/|\\.\\./plugins/layers/$1/|node_modules/(vitest|@vitest)/)',
      },
    },
    {
      name: 'sdk-only-pure-parts',
      severity: 'error',
      comment:
        'The plugin API offers the pure parts of the core only: no state, flow control, input/output or registries (layers as plugins, L3).',
      from: { path: '^src/sdk/' },
      to: { path: '^src/(state|application|infrastructure|generated)/' },
    },
    {
      name: 'core-reaches-plugins-through-the-registries',
      severity: 'error',
      comment: 'Only the generated registries import the layer packages (L-D4).',
      from: { path: '^src/', pathNot: '^src/generated/' },
      to: { path: '^\\.\\./plugins/' },
    },
    {
      name: 'no-collection-modules',
      severity: 'error',
      comment: 'No catch-all modules such as utils, helpers, common, misc, manager (1.3.1).',
      from: {},
      to: { path: '(^|/)(utils?|helpers?|common|misc|managers?)(\\.[jt]sx?$|/)', pathNot: 'node_modules' },
    },
    {
      name: 'no-orphans',
      severity: 'warn',
      from: { orphan: true, pathNot: ['\\.d\\.ts$', '^src/element\\.ts$'] },
      to: {},
    },
  ],
  options: {
    doNotFollow: { path: 'node_modules' },
    exclude: { path: 'src/contract/generated\\.ts$' },
    tsPreCompilationDeps: true,
    tsConfig: { fileName: 'tsconfig.json' },
    enhancedResolveOptions: {
      exportsFields: ['exports'],
      conditionNames: ['import', 'require', 'node', 'default', 'types'],
      extensions: ['.ts', '.tsx', '.js', '.mjs', '.json'],
    },
    reporterOptions: { text: { highlightFocused: true } },
  },
};
