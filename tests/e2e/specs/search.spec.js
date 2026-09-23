/**
 * The core/search block as a loop filter: typing bursts, an archive's paged
 * path, and the button-only variant (spec §5.3, §6.2, §8.4).
 */
const { test, expect } = require('@playwright/test');
const { PAGES, STICKY_TITLE, newestTitles } = require('../fixtures/content');
const {
	isSameDocument,
	markDocument,
	param,
	resultTitles,
	visitor,
	waitForParam,
} = require('../utils');

const { path, queryId } = PAGES.filters;
const searchParam = `query-${queryId}-s`;

test.use({ storageState: visitor });

test('groups a typing burst into one navigation and keeps the text', async ({
	page,
}) => {
	await page.goto(path);

	const fetches = [];
	page.on('request', (request) => {
		if (
			request.resourceType() === 'fetch' &&
			new URL(request.url()).pathname === path
		) {
			fetches.push(request.url());
		}
	});

	const box = page.getByRole('searchbox', { name: 'Search' });

	// Three keystrokes inside one debounce window cost one request, not three.
	await box.pressSequentially('Man');
	await waitForParam(page, searchParam, 'Man');
	expect(fetches).toHaveLength(1);

	// The input is inside the router region, so the text only survives the
	// re-render because change() binds it to context.searchValue.
	await expect(box).toHaveValue('Man');

	await box.press('End');
	await box.pressSequentially('go');
	await waitForParam(page, searchParam, 'Mango');

	await expect(box).toHaveValue('Mango');
	await expect.poll(() => resultTitles(page)).toEqual(['Mango']);

	// Both navigations belong to one typing burst, so they share a single
	// history entry: one Back leaves the page unfiltered, not on "Man".
	await page.goBack();
	await waitForParam(page, searchParam, null);
	await expect
		.poll(() => resultTitles(page))
		.toEqual(newestTitles(undefined, undefined, { sticky: true }));
});

test('a Search block inside an archive loop resets the paged path', async ({
	page,
}) => {
	const response = await page.goto('/category/news/page/2/');
	expect(response.status()).toBe(200);
	await expect.poll(() => resultTitles(page)).toEqual(['Kiwi']);

	await page.getByRole('searchbox', { name: 'Search' }).fill('Mango');
	await waitForParam(page, 's', 'Mango');

	// `?s=` on an archive renders the search template, not the category one —
	// is_search comes before is_archive — so the path and the parameter are
	// the only safe things to assert here, never the archive heading.
	expect(new URL(page.url()).pathname).toBe('/category/news/');
	expect(param(page, 's')).toBe('Mango');
	await expect.poll(() => resultTitles(page)).toEqual(['Mango']);
});

test('the button-only Search variant expands without submitting', async ({
	page,
}) => {
	const { path: buttonPath, queryId: buttonQueryId } = PAGES.buttonSearch;
	const pageKey = `query-${buttonQueryId}-page`;

	// Page 2, because pagination is what a wrongly submitted loop form would
	// reset. A submit that rebuilt the URL the page is already on would not
	// even cost a request: the router renders it from its own cache.
	await page.goto(`${buttonPath}?${pageKey}=2`);
	// The sticky post pins to every page of this loop, not only the first.
	const secondPage = [
		STICKY_TITLE,
		'Nectarine',
		'Cherry',
		'Mango',
		'Banana',
		'Lemon',
	];
	expect(await resultTitles(page)).toEqual(secondPage);
	await markDocument(page);

	const button = page.locator('.wp-block-search__button');
	const input = page.locator('.wp-block-search__input');

	// Collapsed, the input is aria-hidden and the button is not a submit
	// button, so neither is reachable by role. Waiting for core/search to
	// bind type="button" also proves the block has hydrated.
	await expect(button).toHaveAttribute('type', 'button');
	await expect(input).toBeHidden();

	await button.click();

	await expect(input).toBeVisible();
	await expect(input).toBeFocused();

	// Long enough for a submit's navigation to have landed.
	await page.waitForTimeout(500);

	expect(param(page, pageKey)).toBe('2');
	expect(await resultTitles(page)).toEqual(secondPage);
	expect(await isSameDocument(page)).toBe(true);
});
