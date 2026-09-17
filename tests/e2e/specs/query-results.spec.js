/**
 * End-to-end coverage for the 1.0 URL contract: sticky posts, author
 * nicenames, and the author filter's edge cases (spec §3.2, §3.6).
 */
const { test, expect } = require('@playwright/test');
const {
	AUTHOR_SLUG,
	PAGES,
	STICKY_TITLE,
	newestTitles,
} = require('../fixtures/content');
const { param, resultTitles, visitor, waitForParam } = require('../utils');

const { path, queryId } = PAGES.filters;
const authorParam = `query-${queryId}-author`;
const categoryParam = `query-${queryId}-category`;
const isNews = (post) => post.category === 'news';
const isSam = (post) => post.author === 'sam-lee';

test.use({ storageState: visitor });

test("sticky posts don't leak into filtered results", async ({ page }) => {
	await page.goto(path);

	// Unfiltered, the sticky post pins to the front (spec §4.2).
	await expect
		.poll(() => resultTitles(page))
		.toEqual(newestTitles(undefined, undefined, { sticky: true }));

	// The sticky post isn't in News, and a category filter no longer pins it.
	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));
	expect(await resultTitles(page)).not.toContain(STICKY_TITLE);

	// A category's own tax_query happens to keep WordPress's native
	// is_home flag false, which already blocks the pin on its own. The
	// sticky post is Jane Doe's, so an author filter that excludes her
	// is the case that actually exercises QueryArgs::apply_sticky_posts().
	await page.getByRole('checkbox', { name: 'News' }).uncheck();
	await waitForParam(page, categoryParam, null);
	await page
		.getByRole('combobox', { name: 'Author' })
		.selectOption({ label: 'Sam Lee' });
	await waitForParam(page, authorParam, AUTHOR_SLUG);

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isSam));
	expect(await resultTitles(page)).not.toContain(STICKY_TITLE);
});

test('the author parameter uses the nicename', async ({ page }) => {
	await page.goto(path);

	// Sam Lee's REST slug (AUTHOR_SLUG) differs from his username.
	await page
		.getByRole('combobox', { name: 'Author' })
		.selectOption({ label: 'Sam Lee' });
	await waitForParam(page, authorParam, AUTHOR_SLUG);

	expect(param(page, authorParam)).toBe(AUTHOR_SLUG);
	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isSam));
});

test('old numeric author links still work', async ({ page, request }) => {
	const users = await request
		.get(`/wp-json/wp/v2/users?slug=${AUTHOR_SLUG}`)
		.then((response) => response.json());
	const [author] = users;

	await page.goto(`${path}?${authorParam}=${author.id}`);

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isSam));
});

test('an unknown author returns nothing', async ({ page }) => {
	const response = await page.goto(`${path}?${authorParam}=nobody`);

	expect(response.status()).toBe(200);
	await expect(
		page.locator('.wp-block-pikari-gutenberg-query-filter').first()
	).toBeVisible();
	await expect.poll(() => resultTitles(page)).toEqual([]);
});
