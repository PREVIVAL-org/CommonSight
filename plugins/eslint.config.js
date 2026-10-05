/**
 * Lints the frontend parts of the layer packages (plugins/layers/<id>/frontend) with the rules of the frontend: ESLint takes
 * the configuration from the folder of a file, the rules themselves live in frontend/eslint.config.js.
 */
export { default } from '../frontend/eslint.config.js';
