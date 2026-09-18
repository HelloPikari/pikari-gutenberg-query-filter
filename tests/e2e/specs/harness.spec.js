/**
 * Smoke test: each fixture page renders its Query Loop and plugin blocks.
 */
const { test, expect } = require('@playwright/test');
const { PAGES, newestTitles } = require('../fixtures/content');
const { resultTitles, visitor } = require('../utils');

test.use({ storageState: visitor });

for (const [key, fixture] of Object.entries(PAGES)) {
	test(`renders the ${key} fixture page`, async ({ page }) => {
		const response = await page.goto(fixture.path);

		expect(response.status()).toBe(200);
		await expect(
			page.locator('.wp-block-pikari-gutenberg-query-filter').first()
		).toBeVisible();
		// Sticky posts pin to the front of an unfiltered loop's first page (spec §4.2).
		expect(await resultTitles(page)).toEqual(
			newestTitles(undefined, undefined, { sticky: true })
		);
	});
}
