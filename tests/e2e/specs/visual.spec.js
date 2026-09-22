/**
 * Visual baselines for the filter block's display types, and the layout
 * stability roadmap #17 established: a filter change (a router re-render of
 * the Query wrapper's contents) must not move or resize a filter block.
 *
 * Context, not what this file tests directly: the loop's hidden filter
 * `<form>` is injected by `BlockFilters::render_block_query()` as the LAST
 * child of the Query wrapper, and `LoopForm::render()` emits it with both
 * the `hidden` attribute and an inline `style="display:none"`, so that a
 * re-render can never turn it into a flow-layout participant that shifts
 * the blocks around it.
 *
 * Baselines are `<name>-chromium.png` under `__snapshots__/`, generated on
 * this machine (macOS / Chromium) and committed. Playwright does not run on
 * CI (roadmap #28), so nothing regenerates them automatically; when #28
 * lands, that job must either regenerate these baselines for its own
 * platform (Linux) or skip this file — a baseline generated on one platform
 * will not pixel-match a render on another.
 */
const { test, expect } = require('@playwright/test');
const { PAGES, newestTitles } = require('../fixtures/content');
const { resultTitles, visitor, waitForParam } = require('../utils');

const FILTER_BLOCK = '.wp-block-pikari-gutenberg-query-filter';

test.use({ storageState: visitor });

test.describe('display types', () => {
	test('select', async ({ page }) => {
		await page.goto(PAGES.filters.path);

		// PAGES.filters carries two filter blocks (category checkbox, author
		// select); narrow to the one holding the <select> so the locator
		// stays strict-mode-safe.
		const author = page
			.locator(FILTER_BLOCK)
			.filter({ has: page.locator('select') });

		await expect(author).toHaveScreenshot('select-display.png', {
			animations: 'disabled',
		});
	});

	test('checkbox', async ({ page }) => {
		await page.goto(PAGES.filters.path);

		const category = page
			.locator(FILTER_BLOCK)
			.filter({ has: page.getByRole('group', { name: 'Category' }) });

		await expect(category).toHaveScreenshot('checkbox-display.png', {
			animations: 'disabled',
		});
	});

	test('radio', async ({ page }) => {
		await page.goto(PAGES.enhanced.path);

		// PAGES.enhanced carries a single filter block, so the bare locator
		// is already strict-mode-safe.
		const topic = page.locator(FILTER_BLOCK);

		await expect(topic).toHaveScreenshot('radio-display.png', {
			animations: 'disabled',
		});
	});

	// No fixture page sets layoutDirection: horizontal — checked PAGES.filters,
	// PAGES.enhanced, PAGES.sortOnly and PAGES.buttonSearch in
	// tests/e2e/fixtures/content.js. Adding it would mean adding a query-filter
	// block to one of those pages (reshaping a shared fixture other specs
	// depend on) or adding a new PAGES entry (which harness.spec.js iterates
	// directly via `Object.entries(PAGES)`, so it would grow that spec's
	// executed test count too, and need its own baseline). Smallest proposed
	// addition, not applied here: a new `PAGES.horizontal` entry (queryId 5,
	// slug `e2e-horizontal`) with a single `{"filterType":"taxonomy",
	// "taxonomy":"category","displayType":"radio","layoutDirection":
	// "horizontal"}` query-filter block.
	test('horizontal layout', async () => {
		test.skip(
			true,
			'No fixture page sets layoutDirection: horizontal — see the comment above this test.'
		);
	});
});

test.describe('layout stability (roadmap #17)', () => {
	test("a filter block's bounding box is unchanged by a filter change", async ({
		page,
	}) => {
		const { path, queryId } = PAGES.filters;
		await page.goto(path);

		const category = page
			.locator(FILTER_BLOCK)
			.filter({ has: page.getByRole('group', { name: 'Category' }) });
		const author = page
			.locator(FILTER_BLOCK)
			.filter({ has: page.locator('select') });

		const categoryBefore = await category.boundingBox();
		const authorBefore = await author.boundingBox();

		await page.getByRole('checkbox', { name: 'News' }).check();
		await waitForParam(page, `query-${queryId}-category`, 'news');
		await expect
			.poll(() => resultTitles(page))
			.toEqual(newestTitles((post) => post.category === 'news'));

		expect(await category.boundingBox()).toEqual(categoryBefore);
		expect(await author.boundingBox()).toEqual(authorBefore);
	});
});
