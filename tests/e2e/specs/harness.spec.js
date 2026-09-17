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
		expect(await resultTitles(page)).toEqual(newestTitles());
	});
}
