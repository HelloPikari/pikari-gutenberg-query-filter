/**
 * The filters without JavaScript.
 *
 * Nothing in view.js runs here, so these specs prove the server-rendered
 * form on its own: the `form` attributes, the hidden inputs, `novalidate`
 * and the <noscript> buttons (spec §5.1, §5.2, §8.4).
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');
const { PAGES, newestTitles } = require('../fixtures/content');
const { ownedParam, param, resultTitles, visitor } = require('../utils');

test.use({ javaScriptEnabled: false, storageState: visitor });

const APPLY = 'Apply filters';

test.describe('Filters without JavaScript', () => {
	test('shows an Apply button for each filter block', async ({ page }) => {
		await page.goto(PAGES.filters.path);

		// One per Query Filter block, each submitting the same form.
		await expect(page.getByRole('button', { name: APPLY })).toHaveCount(2);
	});

	test('applies every filter at once, with an empty search box', async ({
		page,
	}) => {
		// core/search marks its input required; only the form's novalidate
		// lets this submit at all (spec §5.2).
		await page.goto(PAGES.filters.path);
		await page.getByRole('checkbox', { name: 'News' }).check();
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(ownedParam(page, 'query-1-category')).toBe('news');
		expect(await resultTitles(page)).toEqual(
			newestTitles((post) => post.category === 'news')
		);
	});

	test('honours a key[] URL', async ({ page }) => {
		await page.goto(
			`${PAGES.filters.path}?query-1-category[]=news&query-1-category[]=events`
		);

		await expect(page.getByRole('checkbox', { name: 'News' })).toBeChecked();
		await expect(
			page.getByRole('checkbox', { name: 'Events' })
		).toBeChecked();
	});

	test('submits the filters when Enter is pressed in the search box', async ({
		page,
	}) => {
		await page.goto(PAGES.filters.path);
		await page.getByRole('checkbox', { name: 'News' }).check();
		await page.getByRole('searchbox').fill('Apple');
		await page.getByRole('searchbox').press('Enter');

		expect(param(page, 'query-1-s')).toBe('Apple');
		expect(ownedParam(page, 'query-1-category')).toBe('news');
	});

	test('keeps a parameter it does not own', async ({ page }) => {
		await page.goto(`${PAGES.filters.path}?utm_source=newsletter&lang=fr`);
		await page.getByRole('checkbox', { name: 'News' }).check();
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(param(page, 'utm_source')).toBe('newsletter');
		expect(param(page, 'lang')).toBe('fr');
	});

	test('resets pagination when a filter changes', async ({ page }) => {
		await page.goto(`${PAGES.filters.path}?query-1-page=2`);
		await page.getByRole('checkbox', { name: 'News' }).check();
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(param(page, 'query-1-page')).toBeNull();
	});

	test('filters an archive that paginates through its path', async ({
		page,
	}) => {
		const response = await page.goto('/category/news/page/2/');
		expect(response.status()).toBe(200);

		await page.getByRole('combobox', { name: 'Author' }).selectOption({
			label: 'Jane Doe',
		});
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(new URL(page.url()).pathname).toBe('/category/news/');
		expect(ownedParam(page, 'query-author')).toBe('jane-doe');
	});

	test('filters by a term whose slug is percent-encoded', async ({
		page,
	}) => {
		await page.goto(PAGES.filters.path);

		const option = page.getByRole('checkbox', { name: '新闻' }).first();
		const slug = await option.getAttribute('value');

		// WordPress stores a non-Latin slug percent-encoded; the filter must
		// carry those octets through untouched (roadmap #33).
		expect(slug).toMatch(/%[0-9a-f]{2}/);

		await option.check();
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(ownedParam(page, 'query-1-category')).toBe(slug);
		expect(await resultTitles(page)).toEqual(['Zucchini']);
	});

	test('filters by an author whose nicename is percent-encoded', async ({
		page,
	}) => {
		await page.goto(PAGES.filters.path);

		const author = page.getByRole('combobox', { name: 'Author' });
		const option = author.getByRole('option', { name: '山田太郎' });
		const slug = await option.getAttribute('value');

		// WordPress stores a non-Latin nicename percent-encoded, the same as
		// a taxonomy term slug; the author filter must carry those octets
		// through untouched too (roadmap #33).
		expect(slug).toMatch(/%[0-9a-f]{2}/);

		await author.selectOption({ value: slug });
		await page.getByRole('button', { name: APPLY }).first().click();

		expect(ownedParam(page, 'query-1-author')).toBe(slug);
		expect(await resultTitles(page)).toEqual(['Zucchini']);
	});
});
