/**
 * ESLint: TypeScript rules, React hooks, size and complexity limits (Architecture 1.3.6)
 * and the ban on browser APIs in domain logic and presentation (1.3.2).
 */
import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

const browserApi = [
  'window',
  'document',
  'navigator',
  'localStorage',
  'sessionStorage',
  'fetch',
  'location',
  'history',
  'setTimeout',
  'setInterval',
  'requestAnimationFrame',
  'matchMedia',
  'getComputedStyle',
  'XMLHttpRequest',
].map((name) => ({ name, message: 'Keine Browser-API in dieser Schicht (Architektur 1.3.2).' }));

export default tseslint.config(
  {
    ignores: [
      'dist/',
      'dist-analyze/',
      'node_modules/',
      'src/contract/generated.ts',
      'src/generated/',
      'playwright-report/',
      'test-results/',
    ],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['**/*.{ts,tsx}'],
    languageOptions: { globals: { ...globals.browser } },
    rules: {
      complexity: ['error', 10],
      'max-params': ['error', 4],
      'max-depth': ['error', 3],
      'max-lines-per-function': ['warn', { max: 30, skipBlankLines: true, skipComments: true }],
      'max-lines': ['warn', { max: 200, skipBlankLines: true, skipComments: true }],
      '@typescript-eslint/consistent-type-imports': 'error',
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
    },
  },
  // Layer packages (plugins/layers/<id>/frontend, layers as plugins L3): the same rules, and only the plugin API and the
  // contract as imports, never npm packages or other core modules.
  {
    basePath: '..',
    files: ['plugins/layers/*/frontend/**/*.{ts,tsx}'],
    languageOptions: { parser: tseslint.parser },
    rules: {
      complexity: ['error', 10],
      'max-params': ['error', 4],
      'max-depth': ['error', 3],
      'max-lines-per-function': ['warn', { max: 30, skipBlankLines: true, skipComments: true }],
      'max-lines': ['warn', { max: 200, skipBlankLines: true, skipComments: true }],
      '@typescript-eslint/consistent-type-imports': 'error',
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      'no-restricted-imports': [
        'error',
        {
          patterns: [
            {
              regex: '^(?!@sdk/|@contract/|\\.\\.?/)',
              message:
                'A layer package imports only the plugin API (@sdk/…), the contract (@contract/…) and its own modules (./…); dependency-cruiser keeps relative imports inside the package.',
            },
          ],
        },
      ],
    },
  },
  // Tests of a layer package: additionally vitest.
  {
    basePath: '..',
    files: ['plugins/layers/*/frontend/**/*.test.ts'],
    rules: {
      'max-lines-per-function': 'off',
      'no-restricted-imports': [
        'error',
        {
          patterns: [
            {
              regex: '^(?!@sdk/|@contract/|\\.\\.?/|vitest$)',
              message:
                'A test of a layer package imports only the plugin API (@sdk/…), the contract (@contract/…), its own modules (./…) and vitest.',
            },
          ],
        },
      ],
    },
  },
  {
    files: ['src/ui/**/*.tsx', 'src/ui/**/*.ts'],
    ...reactHooks.configs.flat.recommended,
  },
  {
    files: [
      'src/domain/**/*.ts',
      'src/state/**/*.ts',
      'src/i18n/**/*.ts',
      'src/theme/**/*.ts',
      'src/map/layers/**/*.ts',
      'src/map/style.ts',
      'src/map/tooltip.ts',
      'src/contract/**/*.ts',
      'src/sdk/**/*.ts',
      'src/ui/**/*.{ts,tsx}',
    ],
    rules: { 'no-restricted-globals': ['error', ...browserApi] },
  },
  {
    // Layer packages describe data and pure functions; the core does all input/output (layers as plugins, L3).
    basePath: '..',
    files: ['plugins/layers/*/frontend/**/*.{ts,tsx}'],
    rules: { 'no-restricted-globals': ['error', ...browserApi] },
  },
  {
    // Text catalogs are data; their length is no sign of mixed responsibilities.
    files: ['src/i18n/ui-texts.*.ts'],
    rules: { 'max-lines': 'off' },
  },
  {
    files: ['tests/**/*.{ts,tsx}'],
    languageOptions: { globals: { ...globals.node } },
    rules: { 'max-lines-per-function': 'off', 'max-lines': 'off' },
  },
  {
    files: ['scripts/**/*.mjs', '*.config.{js,ts,cjs}', '.dependency-cruiser.cjs'],
    languageOptions: { globals: { ...globals.node } },
    rules: { 'max-lines-per-function': 'off', '@typescript-eslint/no-require-imports': 'off' },
  },
);
