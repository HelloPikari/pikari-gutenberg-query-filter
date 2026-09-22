/**
 * Filtering a custom Query Loop: checkboxes, author select, search.
 */
const { test, expect } = require('@playwright/test');
const { AUTHOR_SLUG, PAGES, newestTitles } = require('../fixtures/content');
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
const authorParam = `query-${queryId}-author`;
const pageParam = `query-${queryId}-page`;
const isNews = (post) => post.category === 'news';
const isSam = (post) => post.author === 'sam-lee';

/**
 * Hold the first page fetch a loop makes, so a second change can overtake it.
 *
 * Every later fetch goes straight through, so the overtaking change lands at
 * once and the held response only arrives after release() lets it.
 *
 * @param {import('@playwright/test').Page} page     Page.
 * @param {string}                          pathname Path whose fetches to hold.
 * @return {Promise<Function>} Releases the held response.
 */
const holdFirstFetch = async (page, pathname) => {
	let release;
	const held = new Promise((resolve) => {
		release = resolve;
	});
	let first = true;

	await page.route(
		(url) => url.pathname === pathname,
		async (route) => {
			if (first) {
				first = false;
				await held;
			}

			await route.continue();
		}
	);

	return release;
};

// FilterHelper::get_taxonomy_filter_terms() calls get_terms() with no
// orderby, so terms render name-ASC: Events before News. buildUrl() groups
// the FormData entries, which the browser emits in DOM order rather than
// click order, so both checked always serializes as "events,news".

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

test('restores the following filter on Forward', async ({ page }) => {
	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');
	await page.getByRole('checkbox', { name: 'Events' }).check();
	await waitForParam(page, categoryParam, 'events,news');

	await page.goBack();
	await waitForParam(page, categoryParam, 'news');
	await page.goForward();
	await waitForParam(page, categoryParam, 'events,news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles());
	await expect(page.getByRole('checkbox', { name: 'Events' })).toBeChecked();
});

test('exposes every option through the accessibility tree', async ({
	page,
}) => {
	// The role assertions above prove the group and the select carry their
	// names; this pins what a screen reader actually walks through, which is
	// the option list those names introduce.
	await expect(
		page.locator('.wp-block-pikari-gutenberg-query-filter__fieldset')
	).toMatchAriaSnapshot(`
- group "Category":
  - checkbox "Events"
  - checkbox "News"
  - checkbox "Uncategorized"
  - checkbox "新闻"
`);

	await expect(
		page.locator('.wp-block-pikari-gutenberg-query-filter__select')
	).toMatchAriaSnapshot(`
- combobox "Author":
  - option "All" [selected]
  - option "Jane Doe"
  - option "Sam Lee"
  - option "山田太郎"
`);
});

test('keeps focus on the control that triggered a navigation', async ({
	page,
}) => {
	const news = page.getByRole('checkbox', { name: 'News' });

	await news.check();
	await waitForParam(page, categoryParam, 'news');
	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));

	// The router patches the region rather than replacing it, so the control
	// the visitor is still on survives the swap.
	await expect(news).toBeFocused();
});

test('navigates when Enter is pressed on a checkbox', async ({ page }) => {
	// News is already checked and the loop is on page 2, so no change event
	// fires and submitting the form is the only thing that can move this URL.
	await page.goto(`${path}?${categoryParam}=news&${pageParam}=2`);
	await expect.poll(() => resultTitles(page)).toEqual(['Kiwi']);
	await markDocument(page);

	await page.getByRole('checkbox', { name: 'News' }).press('Enter');
	await waitForParam(page, pageParam, null);

	expect(param(page, categoryParam)).toBe('news');
	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));
	// A native submit would have reloaded the page and written category[].
	expect(await isSameDocument(page)).toBe(true);
});

test('filters a URL that carries a fragment', async ({ page }) => {
	const errors = [];
	page.on('pageerror', (error) => errors.push(error.message));
	page.on('console', (message) => {
		if (message.type() === 'error') {
			errors.push(message.text());
		}
	});

	// The beforeEach already sits on this path, so leave it first: otherwise
	// the fragment is a same-document jump, nothing reloads, and the page
	// never hydrates with `#results` in window.location. A null response
	// means exactly that, so the guard keeps it from creeping back.
	await page.goto('about:blank');
	const response = await page.goto(`${path}#results`);
	expect(response).not.toBeNull();

	await page.getByRole('checkbox', { name: 'News' }).check();
	await waitForParam(page, categoryParam, 'news');

	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));
	// buildUrl() drops the fragment, as a GET submit would.
	expect(new URL(page.url()).hash).toBe('');
	expect(errors).toEqual([]);
});

test('a select changed while a response is in flight wins', async ({
	page,
}) => {
	const release = await holdFirstFetch(page, path);
	const author = page.getByRole('combobox', { name: 'Author' });

	const held = page.waitForRequest(
		(request) => request.url().includes('jane-doe'),
		{ timeout: 10_000 }
	);
	await author.selectOption({ label: 'Jane Doe' });
	const heldRequest = await held;

	await author.selectOption({ label: 'Sam Lee' });
	await waitForParam(page, authorParam, AUTHOR_SLUG);
	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isSam));

	// Let the overtaken response arrive, and prove it cannot take the page back.
	release();
	await (await heldRequest.response()).finished();
	await page.waitForTimeout(250);

	expect(param(page, authorParam)).toBe(AUTHOR_SLUG);
	expect(await resultTitles(page)).toEqual(newestTitles(isSam));
});

test('a checkbox changed while a response is in flight wins', async ({
	page,
}) => {
	const release = await holdFirstFetch(page, path);

	const held = page.waitForRequest(
		(request) => request.url().includes('category=news'),
		{ timeout: 10_000 }
	);
	await page.getByRole('checkbox', { name: 'News' }).check();
	const heldRequest = await held;

	await page.getByRole('checkbox', { name: 'Events' }).check();
	await waitForParam(page, categoryParam, 'events,news');
	await expect.poll(() => resultTitles(page)).toEqual(newestTitles());

	release();
	await (await heldRequest.response()).finished();
	await page.waitForTimeout(250);

	expect(param(page, categoryParam)).toBe('events,news');
	expect(await resultTitles(page)).toEqual(newestTitles());
});

test('a radio changed while a response is in flight wins', async ({ page }) => {
	const enhanced = PAGES.enhanced;
	const enhancedCategory = `query-${enhanced.queryId}-category`;
	await page.goto(enhanced.path);

	const release = await holdFirstFetch(page, enhanced.path);

	const held = page.waitForRequest(
		(request) => request.url().includes('category=events'),
		{ timeout: 10_000 }
	);
	await page.getByRole('radio', { name: 'Events' }).check();
	const heldRequest = await held;

	await page.getByRole('radio', { name: 'News' }).check();
	await waitForParam(page, enhancedCategory, 'news');
	await expect.poll(() => resultTitles(page)).toEqual(newestTitles(isNews));

	release();
	await (await heldRequest.response()).finished();
	await page.waitForTimeout(250);

	expect(param(page, enhancedCategory)).toBe('news');
	expect(await resultTitles(page)).toEqual(newestTitles(isNews));
});
