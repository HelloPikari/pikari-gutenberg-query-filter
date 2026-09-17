/**
 * Sorting a Query Loop that contains only a Sort block.
 */
const { test, expect } = require('@playwright/test');
const { PAGES, titlesByTitle } = require('../fixtures/content');
const {
	isSameDocument,
	markDocument,
	resultTitles,
	visitor,
} = require('../utils');

test.use({ storageState: visitor });

test('sorts a loop that contains only a Sort block', async ({ page }) => {
	await page.goto(PAGES.sortOnly.path);
	await markDocument(page);

	await page
		.getByRole('combobox', { name: 'Sort by' })
		.selectOption({ label: 'Title (A-Z)' });

	await expect.poll(() => resultTitles(page)).toEqual(titlesByTitle());
	expect(await isSameDocument(page)).toBe(true);
});
