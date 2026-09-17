/**
 * Playwright configuration for end-to-end tests.
 *
 * Extends the @wordpress/scripts defaults. `npm run test:e2e` sets WP_BASE_URL
 * to the wp-env tests instance from .wp-env.json (port 5885).
 */
const path = require('path');
const baseConfig = require('@wordpress/scripts/config/playwright.config.js');

module.exports = {
	...baseConfig,
	testDir: './tests/e2e/specs',
	// Authenticate as admin first, then rebuild the fixture content.
	globalSetup: [
		require.resolve('@wordpress/scripts/config/playwright/global-setup.js'),
		path.resolve(__dirname, 'tests/e2e/setup/fixtures.js'),
	],
	webServer: {
		...baseConfig.webServer,
		// The package's own `wp-env` script adds --xdebug and can't take `start`.
		command: 'npx wp-env start',
	},
};
