import js from '@eslint/js';
import globals from 'globals';

export default [
	{ ignores: [ 'dist/', 'node_modules/', 'server/vendor/', 'playwright-report/', 'test-results/' ] },
	js.configs.recommended,
	{
		languageOptions: {
			ecmaVersion: 'latest',
			sourceType: 'module',
			globals: { ...globals.browser },
		},
		rules: {
			'no-console': [ 'error', { allow: [ 'warn', 'error' ] } ],
			eqeqeq: 'error',
			'prefer-const': 'error',
			'no-var': 'error',
		},
	},
	{
		files: [ 'public/sw.js' ],
		languageOptions: { globals: { ...globals.serviceworker } },
	},
	{
		files: [ 'tests/**', 'scripts/**', '*.config.js' ],
		languageOptions: { globals: { ...globals.node } },
	},
];
