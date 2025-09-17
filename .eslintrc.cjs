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
