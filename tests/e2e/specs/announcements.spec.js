/**
 * The result announcement in a real browser (spec C/D §3.3): after a filter,
 * sort or search navigation, view.js speaks the loop's result count through
 * core's shared `#a11y-speak-polite` region, with the router's own
 * "loading"/"loaded" announcements turned off.
 */
const { test, expect } = require('@playwright/test');
const { PAGES, POSTS } = require('../fixtures/content');
const { visitor, waitForParam } = require('../utils');

const { path, queryId } = PAGES.filters;
const searchParam = `query-${queryId}-s`;
const categoryParam = `query-${queryId}-category`;
const isNews = (post) => post.category === 'news';
const isJane = (post) => post.author === 'jane-doe';

/**
 * The shared polite live region's current text, trimmed.
 *
 * speak() (@wordpress/a11y) appends a trailing space when a message repeats,
 * so a caller comparing exact text must trim it away.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<string>} Trimmed text.
 */
const spoken = async (page) =>
	(await page.locator('#a11y-speak-polite').textContent())?.trim() ?? '';

test.use({ storageState: visitor });

test('announces a count for a single search match', async ({ page }) => {
	await page.goto(path);

	await page.getByRole('searchbox', { name: 'Search' }).fill('Mango');
	await waitForParam(page, searchParam, 'Mango');

	// "Mango" is one fixture post's title. `s` also searches post content
	// and excerpt, but setup/fixtures.js creates every POSTS entry with no
	// content, so only the title can match (filters.spec.js's own search
	// test confirms no other post matches it).
	const count = POSTS.filter((post) => post.title === 'Mango').length;
	await expect.poll(() => spoken(page)).toBe(`${count} result found`);
});

test('announces zero results', async ({ page }) => {
	await page.goto(path);

	// No fixture post's title contains this string.
	await page.getByRole('searchbox', { name: 'Search' }).fill('Xyzzyxyzzy');
	await waitForParam(page, searchParam, 'Xyzzyxyzzy');

	await expect.poll(() => spoken(page)).toBe('No results found');
});

test('announces a plural count for a category filter', async ({ page }) => {
	await page.goto(path);

	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');

	// Even-indexed POSTS are News: Kiwi, Lemon, Mango, Nectarine, Orange,
	// Papaya (also confirmed by inherited-loops.spec.js). PAGES.filters
	// queries postType "post" only and per_page 5, but found_posts is the
	// total, not the page size, so it's the full News count.
	const count = POSTS.filter(isNews).length;
	await expect.poll(() => spoken(page)).toBe(`${count} results found`);
});

test("does not speak the router's own text", async ({ page }) => {
	await page.goto(path);

	// The router's own "Page Loaded." would only sit in the region for a
	// moment before this plugin's own announce() overwrites it (both
	// resolve through back-to-back `import('@wordpress/a11y')` microtasks),
	// so a single read after settling could miss it. A MutationObserver
	// records every value the region takes on instead.
	await page.evaluate(() => {
		window.e2eSpoken = [];
		const region = document.getElementById('a11y-speak-polite');
		new MutationObserver(() => {
			window.e2eSpoken.push(region.textContent);
		}).observe(region, {
			childList: true,
			characterData: true,
			subtree: true,
		});
	});

	await page.getByRole('searchbox', { name: 'Search' }).fill('Mango');
	await waitForParam(page, searchParam, 'Mango');
	// Test 1's own change: wait for it to fully settle before reading the
	// recorded history.
	await expect.poll(() => spoken(page)).toBe('1 result found');

	const history = await page.evaluate(() => window.e2eSpoken);
	expect(history.some((text) => /page loaded/i.test(text))).toBe(false);
});

test('an inherited loop announces a count', async ({ page }) => {
	await page.goto('/category/news/');

	await page
		.getByRole('combobox', { name: 'Author' })
		.selectOption({ label: 'Jane Doe' });
	await waitForParam(page, 'query-author', 'jane-doe');

	// News posts by Jane Doe: Kiwi, Lemon, Mango (indexes 0, 2, 4 — even
	// indexes are News, the first six are Jane's); also confirmed by
	// inherited-loops.spec.js's own author-filter test.
	const count = POSTS.filter(
		(post) => isNews(post) && isJane(post)
	).length;
	await expect.poll(() => spoken(page)).toBe(`${count} results found`);
});

test.describe('without JavaScript', () => {
	test.use({ javaScriptEnabled: false });

	test('carries the found-posts attributes on the loop form', async ({
		page,
	}) => {
		await page.goto(`${path}?${searchParam}=Mango`);

		// No view.js runs here; this proves the server-rendered count and
		// message on their own, with no announcement involved.
		const form = page.locator(
			`#pikari-gutenberg-query-filter-form-${queryId}`
		);
		await expect(form).toHaveAttribute('data-query-found-posts', '1');
		await expect(form).toHaveAttribute(
			'data-query-results-message',
			'1 result found'
		);
	});
});
