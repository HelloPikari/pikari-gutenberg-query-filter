/**
 * Helpers shared by the end-to-end specs.
 */

// Browse as a logged-out visitor rather than the admin storage state.
const visitor = { cookies: [], origins: [] };

/**
 * Post titles rendered by the page's Query Loop, in display order.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<string[]>} Titles.
 */
const resultTitles = async (page) =>
	(
		await page.locator('.wp-block-query .wp-block-post-title').allTextContents()
	).map((title) => title.trim());

/**
 * Mark the current document, so a later check can tell whether a full page load happened.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
const markDocument = (page) =>
	page.evaluate(() => {
		window.e2eSameDocument = true;
	});

/**
 * Whether the document marked by markDocument() is still the current one.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<boolean>} True when no full page load happened.
 */
const isSameDocument = (page) =>
	page.evaluate(() => window.e2eSameDocument === true);

/**
 * A URL parameter of the current page, decoded.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          name Parameter name.
 * @return {string|null} Value, or null when absent.
 */
const param = (page, name) => new URL(page.url()).searchParams.get(name);

/**
 * Wait until a URL parameter has a value, or is absent when value is null.
 *
 * Times out after 10s rather than the full test timeout, so a missed
 * navigation fails fast instead of waiting out the whole test.
 *
 * @param {import('@playwright/test').Page} page  Page.
 * @param {string}                          name  Parameter name.
 * @param {string|null}                     value Expected value.
 */
const waitForParam = (page, name, value) =>
	page.waitForURL((url) => new URL(url).searchParams.get(name) === value, {
		timeout: 10_000,
	});

module.exports = {
	isSameDocument,
	markDocument,
	param,
	resultTitles,
	visitor,
	waitForParam,
};
