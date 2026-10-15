/**
 * ESLint flat config extending `@wordpress/eslint-plugin`.
 *
 * Synced to all plugins via config-templates. Do not edit in plugin directories —
 * changes should be made in .github/config-templates/plugin/eslint.config.cjs and synced.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/#lint-js
 */

const wpPlugin = require( '@wordpress/eslint-plugin' );
const globals = require( 'globals' );

module.exports = [
	{
		ignores: [
			'**/build/**',
			'**/node_modules/**',
			'**/vendor/**',
			'tests/e2e/**',
			'playwright.config.js',
		],
	},

	...wpPlugin.configs.recommended,

	{
		languageOptions: {
			globals: {
				...globals.browser,
				Alpine: 'readonly',
			},
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
		},
		settings: {
			// Tell eslint-plugin-import that @wordpress/* packages are externals
			// (provided by WordPress at runtime, not installed as npm dependencies)
			'import/core-modules': [
				'@wordpress/block-editor',
				'@wordpress/blocks',
				'@wordpress/components',
				'@wordpress/compose',
				'@wordpress/data',
				'@wordpress/element',
				'@wordpress/escape-html',
				'@wordpress/hooks',
				'@wordpress/i18n',
				'@wordpress/interactivity',
				'@wordpress/interactivity-router',
				'@wordpress/rich-text',
			],
		},
		rules: {
			// Disable import/no-unresolved for @wordpress/* packages
			// These are externals provided by WordPress at runtime
			'import/no-unresolved': [
				'error',
				{
					ignore: [ '^@wordpress/' ],
				},
			],
			// Disable import/no-extraneous-dependencies for @wordpress/* packages
			'import/no-extraneous-dependencies': [
				'error',
				{
					devDependencies: true,
					peerDependencies: true,
				},
			],
		},
	},

	// Unit tests run on Vitest with `globals: true` (see vitest.config.mjs).
	...wpPlugin.configs[ 'test-unit' ].map( ( config ) => ( {
		...config,
		files: [ 'tests/unit/**/*.js' ],
	} ) ),
	{
		files: [ 'tests/unit/**/*.js' ],
		rules: {
			'vitest/no-conditional-expect': 'off',
		},
	},
	{
		files: [ 'tests/**/*.js' ],
		languageOptions: {
			globals: {
				...globals.node,
				...globals.vitest,
			},
		},
		rules: {
			'import/no-extraneous-dependencies': 'off',
		},
	},
	{
		files: [ 'webpack.config.js', 'eslint.config.cjs', 'vitest.config.mjs' ],
		languageOptions: {
			globals: globals.node,
		},
		rules: {
			'import/no-extraneous-dependencies': 'off',
		},
	},
];
