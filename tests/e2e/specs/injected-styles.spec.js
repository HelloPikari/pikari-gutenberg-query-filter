/**
 * Styles injected by other scripts stay enabled after router navigation.
 */
const { test, expect } = require('@playwright/test');
const { INJECTED_COLOR, PAGES } = require('../fixtures/content');
const {
	isSameDocument,
	markDocument,
	param,
	visitor,
	waitForParam,
} = require('../utils');

test.use({ storageState: visitor });

test('keeps injected styles after filtering', async ({ page }) => {
	const { path, queryId } = PAGES.filters;
	await page.goto(path);
	const injected = page.locator('.e2e-injected');
	await expect(injected).toHaveCSS('color', INJECTED_COLOR);
	await markDocument(page);

	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, `query-${queryId}-category`, 'news');

	expect(await isSameDocument(page)).toBe(true);
	await expect(injected).toHaveCSS('color', INJECTED_COLOR);
});

test('keeps injected styles after enhanced pagination and a filter change', async ({
	page,
}) => {
	const { path, queryId } = PAGES.enhanced;
	await page.goto(path);
	await markDocument(page);
	const injected = page.locator('.e2e-injected');
	await expect(injected).toHaveCSS('color', INJECTED_COLOR);

	await page.locator('.wp-block-query-pagination-next').click();
	await waitForParam(page, `query-${queryId}-page`, '2');
	expect(await isSameDocument(page)).toBe(true);
	await expect(injected).toHaveCSS('color', INJECTED_COLOR);

	await page.getByRole('radio', { name: 'News' }).check();
	await waitForParam(page, `query-${queryId}-category`, 'news');
	expect(param(page, `query-${queryId}-page`)).toBeNull();
	await expect(injected).toHaveCSS('color', INJECTED_COLOR);
});
