/**
 * Filtering an inherited Query Loop on real archive and search templates
 * (spec §3.4, §4.4). Unlike the custom-loop specs, these exercise WordPress's
 * own main query, its template hierarchy and its 404 handling, not a Query
 * Loop's own WP_Query.
 */
const { test, expect } = require('@playwright/test');
const { POSTS, newestTitles } = require('../fixtures/content');
const {
	isSameDocument,
	markDocument,
	param,
	resultTitles,
	visitor,
	waitForParam,
} = require('../utils');

// News: Kiwi, Lemon, Mango, Nectarine, Orange, Papaya (6 posts; per_page is 5,
// see setup/fixtures.js, so this spans two pages).
const isNews = (post) => post.category === 'news';
const isJane = (post) => post.author === 'jane-doe';

test.use({ storageState: visitor });

test('an inherited archive keeps its own identity while filtering by author', async ({
	page,
}) => {
	await page.goto('/category/news/');

	// Task 3: with no filter applied yet, the Sort dropdown starts on the
	// empty "Default" option rather than a pre-selected "Date (Newest First)"
	// (spec §4.3) — pinned here since this is the only runtime coverage it gets.
	const sort = page.getByRole('combobox', { name: 'Sort by' });
	await expect(sort).toHaveValue('');
	await expect(sort.locator('option:checked')).toHaveText('Default');

	await page
		.getByRole('combobox', { name: 'Author' })
		.selectOption({ label: 'Jane Doe' });
	await waitForParam(page, 'query-author', 'jane-doe');

	// The archive is still the News category archive, not hijacked into
	// looking like an author archive (spec §4.4's whole point).
	await expect(page.getByRole('heading', { name: /News/ })).toBeVisible();
	await expect
		.poll(() => resultTitles(page))
		.toEqual(newestTitles((post) => isNews(post) && isJane(post)));
});

test('a taxonomy filter cannot hijack the archive it runs on', async ({
	page,
}) => {
	// A full navigation, not a checkbox click: the Interactivity Router only
	// patches the Query block's own router region (BlockFilters::render_block_query()),
	// which sits beside the query-title heading, not around it. A client-side
	// filter change never re-renders the heading either way, so it can't
	// prove anything about whether the *server's* response for this URL
	// would have hijacked it. Only a real request exercises that.
	const response = await page.goto('/category/news/?query-category=events');
	expect(response.status()).toBe(200);

	// This is §4.4's actual risk: a valid, different term intersected with
	// the archive's own category, not merely an unknown one (see the next
	// test). The old approach — writing filter terms into the tax_query
	// query var — makes core think the page *is* that term: it overwrites
	// the category_name/cat query vars from WP_Tax_Query::queried_terms
	// (class-wp-query.php's "Ensure … 'cat', and 'category_name' vars are
	// set for backward compatibility" block), hijacking the title along
	// with it. TaxonomySubquery exists so it doesn't.
	await expect(page.getByRole('heading', { name: /News/ })).toBeVisible();
	await expect(page.getByRole('heading', { name: /Events/ })).toHaveCount(
		0
	);

	expect(param(page, 'query-category')).toBe('events');

	// News and Events are disjoint in this fixture — every post has exactly
	// one category (content.js) — so intersecting them returns nothing.
	await expect.poll(() => resultTitles(page)).toEqual([]);
});

test("an unknown taxonomy term doesn't 404 the archive", async ({ page }) => {
	const response = await page.goto(
		'/category/news/?query-category=does-not-exist'
	);

	expect(response.status()).toBe(200);
	await expect.poll(() => resultTitles(page)).toEqual([]);
});

test('filtering an archive does not reload the page', async ({ page }) => {
	await page.goto('/category/news/');
	await markDocument(page);

	await page
		.getByRole('combobox', { name: 'Author' })
		.selectOption({ label: 'Jane Doe' });
	await waitForParam(page, 'query-author', 'jane-doe');

	expect(await isSameDocument(page)).toBe(true);
});

test('filtering from page 2 of an archive resets to page 1', async ({
	page,
}) => {
	const response = await page.goto('/category/news/page/2/');
	expect(response.status()).toBe(200);
	await expect.poll(() => resultTitles(page)).toEqual(['Kiwi']);

	await page
		.getByRole('combobox', { name: 'Author' })
		.selectOption({ label: 'Jane Doe' });
	await waitForParam(page, 'query-author', 'jane-doe');

	expect(new URL(page.url()).pathname).not.toMatch(/\/page\/\d+\/?$/);
	expect(param(page, 'paged')).toBeNull();
	await expect
		.poll(() => resultTitles(page))
		.toEqual(newestTitles((post) => isNews(post) && isJane(post)));
});

test('sorting an archive orders it by title', async ({ page }) => {
	await page.goto('/category/news/');

	await page
		.getByRole('combobox', { name: 'Sort by' })
		.selectOption({ label: 'Title (A-Z)' });
	await waitForParam(page, 'query-sort', 'title-asc');

	const newsTitlesByTitle = POSTS.filter(isNews)
		.map((post) => post.title)
		.sort()
		.slice(0, 5);

	await expect.poll(() => resultTitles(page)).toEqual(newsTitlesByTitle);
});

test("a search template's post type options don't collapse to the chosen type", async ({
	page,
}) => {
	await page.goto('/?s=err');

	const type = page.getByRole('combobox', { name: 'Type' });
	await expect(type.getByRole('option', { name: 'Pages' })).toHaveCount(1);

	await type.selectOption({ label: 'Posts' });
	await waitForParam(page, 'query-post_type', 'post');

	// Task 3's fix: the option list is built from the request's recorded,
	// unfiltered post_type, not the just-applied filter, so "Pages" is still
	// offered even though only "Posts" is now selected (spec §4.5).
	await expect(type.getByRole('option', { name: 'Pages' })).toHaveCount(1);
});

test('a search term survives filtering by author', async ({ page }) => {
	// "err" matches exactly one post per author: Cherry (Jane) and
	// Elderberry (Sam). Neither fixture page title matches.
	await page.goto('/?s=err');

	await page
		.getByRole('combobox', { name: 'Author' })
		.selectOption({ label: 'Jane Doe' });
	await waitForParam(page, 'query-author', 'jane-doe');

	// This is the case that broke in review: the Search block sits outside
	// the loop on this template, so `s` has no control of its own to write
	// it back, and a naive "remove this loop's owned parameters" pass could
	// drop it (spec §3.3).
	expect(param(page, 's')).toBe('err');
	await expect.poll(() => resultTitles(page)).toEqual(['Cherry']);
});
