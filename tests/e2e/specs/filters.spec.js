/**
 * Filtering a custom Query Loop: checkboxes, author select, search.
 */
const { test, expect } = require('@playwright/test');
const { PAGES, newestTitles } = require('../fixtures/content');
const {
	isSameDocument,
	markDocument,
	param,
	resultTitles,
	visitor,
	waitForParam,
} = require('../utils');

const { path, queryId } = PAGES.filters;
const categoryParam = `query-${queryId}-category`;
const isNews = (post) => post.category === 'news';

// FilterHelper::get_taxonomy_filter_terms() calls get_terms() with no
// orderby, so terms render name-ASC: Events before News. updateFilters()
// joins the checked checkboxes in DOM order, not click order, so both
// checked always serializes as "events,news".

test.use({ storageState: visitor });

test.beforeEach(async ({ page }) => {
	await page.goto(path);
});

test('names the category group and labels the author select', async ({
	page,
}) => {
	await expect(page.getByRole('group', { name: 'Category' })).toBeVisible();
	await expect(page.getByRole('combobox', { name: 'Author' })).toBeVisible();
});

test('filters by a category without a full page load', async ({ page }) => {
	await markDocument(page);

	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));
	expect(await isSameDocument(page)).toBe(true);
});

test('writes checked categories as one comma-separated parameter', async ({
	page,
}) => {
	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));

	await page.getByRole('checkbox', { name: 'Events' }).check();
	await waitForParam(page, categoryParam, 'events,news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles());
});

test('filters by author', async ({ page }) => {
	await page
		.getByRole('combobox', { name: 'Author' })
		.selectOption({ label: 'Jane Doe' });

	await expect
		.poll(() => resultTitles(page))
		.toEqual(newestTitles((post) => post.author === 'jane-doe'));
});

test('filters by search text', async ({ page }) => {
	await page.getByRole('searchbox', { name: 'Search' }).fill('Mango');
	await waitForParam(page, `query-${queryId}-s`, 'Mango');

	await expect.poll(() => resultTitles(page)).toEqual(['Mango']);
});

test('returns to page 1 when a filter changes', async ({ page }) => {
	await page.goto(`${path}?query-${queryId}-page=2`);

	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');

	expect(param(page, `query-${queryId}-page`)).toBeNull();
});

test('restores the previous filter on Back', async ({ page }) => {
	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');
	await page.getByRole('checkbox', { name: 'Events' }).check();
	await waitForParam(page, categoryParam, 'events,news');

	await page.goBack();
	await waitForParam(page, categoryParam, 'news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));
	await expect(
		page.getByRole('checkbox', { name: 'Events' })
	).not.toBeChecked();
});
