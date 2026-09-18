/**
 * Sorting a Query Loop that contains only a Sort block.
 */
const { test, expect } = require('@playwright/test');
const { PAGES, newestTitles, titlesByTitle } = require('../fixtures/content');
const {
	isSameDocument,
	markDocument,
	param,
	resultTitles,
	visitor,
	waitForParam,
} = require('../utils');

test.use({ storageState: visitor });

test('sorts a loop that contains only a Sort block', async ({ page }) => {
	await page.goto(PAGES.sortOnly.path);
	await markDocument(page);

	await page
		.getByRole('combobox', { name: 'Sort by' })
		.selectOption({ label: 'Title (A-Z)' });
	await waitForParam(page, 'query-3-sort', 'title-asc');

	await expect.poll(() => resultTitles(page)).toEqual(titlesByTitle());
	expect(await isSameDocument(page)).toBe(true);
	expect(param(page, 'query-3-sort')).toBe('title-asc');
});

test("choosing the loop's own order removes the sort parameter", async ({
	page,
}) => {
	await page.goto(PAGES.sortOnly.path);

	await page
		.getByRole('combobox', { name: 'Sort by' })
		.selectOption({ label: 'Title (A-Z)' });
	await waitForParam(page, 'query-3-sort', 'title-asc');

	await page
		.getByRole('combobox', { name: 'Sort by' })
		.selectOption({ label: 'Date (Newest First)' });
	await waitForParam(page, 'query-3-sort', null);

	// No filter is active once the sort parameter is removed, so the
	// sticky post pins to the front again (spec §4.2).
	await expect
		.poll(() => resultTitles(page))
		.toEqual(newestTitles(undefined, undefined, { sticky: true }));
});
