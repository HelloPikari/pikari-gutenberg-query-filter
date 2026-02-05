module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended-with-formatting' ],
	env: {
		browser: true,
	},
	globals: {
		Alpine: 'readonly',
	},
	ignorePatterns: [
		'tests/e2e/**',
		'playwright.config.js',
	],
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
	overrides: [
		{
			files: [ 'tests/**/*.js' ],
			env: {
				jest: true,
				node: true,
			},
			globals: {
				jest: 'readonly',
				describe: 'readonly',
				it: 'readonly',
				expect: 'readonly',
				beforeEach: 'readonly',
				afterEach: 'readonly',
				beforeAll: 'readonly',
				afterAll: 'readonly',
			},
			rules: {
				'import/no-extraneous-dependencies': 'off',
				'jest/no-conditional-expect': 'off',
			},
		},
		{
			files: [ 'playwright.config.js', 'tests/e2e/**/*.js' ],
			env: {
				node: true,
			},
			rules: {
				'import/no-extraneous-dependencies': 'off',
			},
		},
		{
			files: [ 'tests/unit/**/*.js' ],
			globals: {
				KeyboardEvent: 'readonly',
			},
			rules: {
				'jest/no-conditional-expect': 'off',
			},
		},
		{
			files: [ 'webpack.config.js' ],
			env: {
				node: true,
			},
		},
	],
};
