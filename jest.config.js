/**
 * Jest configuration extending @wordpress/scripts defaults.
 *
 * Synced to all plugins via config-templates. Do not edit in plugin directories —
 * changes should be made in .github/config-templates/plugin/jest.config.js and synced.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/#test-unit-js
 */

const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,

	// Test file locations — matches ESLint overrides in .eslintrc.js
	roots: [ '<rootDir>/tests/unit/' ],
	testMatch: [ '<rootDir>/tests/unit/**/*.test.js' ],

	// Module name mapping for @wordpress/* externals not in node_modules
	moduleNameMapper: {
		...( defaultConfig.moduleNameMapper || {} ),
		'^@wordpress/interactivity$':
			'<rootDir>/tests/unit/__mocks__/@wordpress/interactivity.js',
		'^@wordpress/interactivity-router$':
			'<rootDir>/tests/unit/__mocks__/@wordpress/interactivity-router.js',
		'^@wordpress/escape-html$':
			'<rootDir>/tests/unit/__mocks__/@wordpress/escape-html.js',
	},

	// Setup files run after test environment is installed (beforeEach available)
	setupFilesAfterEnv: [
		...( defaultConfig.setupFilesAfterEnv || [] ),
		'<rootDir>/tests/unit/setup.js',
	],

	// Pass with no tests (no failure when test suite is empty)
	passWithNoTests: true,

	// jsdom environment for DOM manipulation tests
	testEnvironment: 'jsdom',

	// Coverage collection from source files
	collectCoverageFrom: [
		'src/**/*.js',
		'!src/**/index.js',
		'!src/**/*.test.js',
	],
};
