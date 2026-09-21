/**
 * Deterministic content for the end-to-end suite.
 *
 * Titles are chosen so date order and title order differ, and each category
 * and author owns a different set of posts.
 */

const INJECTED_COLOR = 'rgb(1, 2, 3)';

const CATEGORIES = [
	{ name: 'News', slug: 'news' },
	{ name: 'Events', slug: 'events' },
];

const AUTHORS = [
	{ username: 'jane-doe', name: 'Jane Doe' },
	// A REST slug that differs from the username, so specs can tell the
	// author filter's URL value (the nicename) apart from the username.
	{ username: 'sam-lee', name: 'Sam Lee', slug: 'sam-the-editor' },
];

// The nicename Sam Lee's REST record resolves to; exported so specs can
// assert the author filter writes it to the URL (spec §3.2).
const AUTHOR_SLUG = 'sam-the-editor';

// A sticky post outside News and Events, older than every other post.
const STICKY_TITLE = 'Quince';

const TITLES = [
	'Kiwi',
	'Apple',
	'Lemon',
	'Banana',
	'Mango',
	'Cherry',
	'Nectarine',
	'Date',
	'Orange',
	'Elderberry',
	'Papaya',
	'Fig',
];

// Index 0 is the oldest post. Even indexes are News, odd are Events.
// The first six are Jane's, the last six Sam's.
const POSTS = TITLES.map((title, index) => ({
	title,
	date: `2026-01-${String(index + 1).padStart(2, '0')}T09:00:00`,
	category: index % 2 === 0 ? 'news' : 'events',
	author: index < 6 ? 'jane-doe' : 'sam-lee',
})).concat({
	title: STICKY_TITLE,
	// Older than every dated post above, so it only shows up via the sticky pin.
	date: '2025-12-31T09:00:00',
	category: 'uncategorized',
	author: 'jane-doe',
	sticky: true,
});

/**
 * Titles of the newest posts matching a predicate, newest first.
 *
 * WordPress pins a sticky post to the front of an unfiltered loop's first
 * page (spec §4.2); pass `sticky: true` to reflect that. When the sticky
 * post is older than every other match (as fixture STICKY_TITLE is), it
 * ranks outside the normal top `count` on its own date, so WordPress fetches
 * it separately and adds it as an extra post rather than displacing one —
 * the page shows `count + 1` titles. A loop with any filter applied ignores
 * sticky posts, so callers that already pass a predicate for an active
 * filter should leave this off.
 *
 * @param {Function} predicate      Receives a POSTS entry.
 * @param {number}   count          Posts per page.
 * @param {Object}   options
 * @param {boolean}  options.sticky Whether the sticky post is pinned first.
 * @return {string[]} Titles.
 */
const newestTitles = (predicate = () => true, count = 5, { sticky = false } = {}) => {
	// Sort by date rather than relying on array order: STICKY_TITLE is
	// appended last in POSTS but dated before everything else.
	const titles = POSTS.filter(predicate)
		.slice()
		.sort((a, b) => new Date(b.date) - new Date(a.date))
		.map((post) => post.title);

	if (!sticky) {
		return titles.slice(0, count);
	}

	const top = titles.slice(0, count);
	if (top.includes(STICKY_TITLE)) {
		return [STICKY_TITLE, ...top.filter((title) => title !== STICKY_TITLE)];
	}

	return [STICKY_TITLE, ...top];
};

/**
 * The first titles in alphabetical order.
 *
 * @param {number} count Posts per page.
 * @return {string[]} Titles.
 */
const titlesByTitle = (count = 5) => [...TITLES].sort().slice(0, count);

// A script that injects an id-less <style> while the page parses, the way
// WPForms adds its honeypot CSS. The router disables such styles on navigation.
const injectedStyleBlock = `<!-- wp:html -->
<script>document.head.appendChild(Object.assign(document.createElement('style'),{textContent:'.e2e-injected{color:${INJECTED_COLOR}}'}));</script>
<p class="e2e-injected">Injected style check</p>
<!-- /wp:html -->`;

const queryBlock = (queryId, innerBlocks, attributes = {}) =>
	`<!-- wp:query ${JSON.stringify({
		queryId,
		query: {
			perPage: 5,
			pages: 0,
			offset: 0,
			postType: 'post',
			order: 'desc',
			orderBy: 'date',
			author: '',
			search: '',
			exclude: [],
			sticky: '',
			inherit: false,
		},
		...attributes,
	})} -->
<div class="wp-block-query">
${innerBlocks}
<!-- wp:post-template -->
<!-- wp:post-title /-->
<!-- /wp:post-template -->
<!-- wp:query-pagination -->
<!-- wp:query-pagination-previous /-->
<!-- wp:query-pagination-numbers /-->
<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->`;

// A Query Loop that inherits the main query, the shape core itself gives
// every archive and search template (no queryId, "inherit":true). Used by
// TEMPLATES below to prove filtering works on the main query, not just a
// custom loop (spec §3.4, §4.4).
const inheritedQueryBlock = (innerBlocks) =>
	`<!-- wp:query ${JSON.stringify({
		query: {
			perPage: 10,
			pages: 0,
			offset: 0,
			postType: 'post',
			order: 'desc',
			orderBy: 'date',
			author: '',
			search: '',
			exclude: [],
			sticky: '',
			inherit: true,
		},
	})} -->
<div class="wp-block-query">
${innerBlocks}
<!-- wp:post-template -->
<!-- wp:post-title /-->
<!-- /wp:post-template -->
<!-- wp:query-pagination -->
<!-- wp:query-pagination-previous /-->
<!-- wp:query-pagination-numbers /-->
<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->`;

// Two wp_template overrides for the active theme (Twenty Twenty-Five),
// exercising inherited-loop filtering on real archive and search requests.
// Twenty Twenty-Five defines no `category` template of its own, so adding
// one is safe. `search` is modelled on the theme's own template: the Search
// block sits outside the loop, the layout that exposed a bug in review
// (spec §3.3's "s on a search template whose Search block sits outside the
// loop").
const TEMPLATES = {
	category: {
		slug: 'category',
		content: `<!-- wp:query-title {"type":"archive"} /-->
${inheritedQueryBlock(
	`<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"taxonomy","taxonomy":"category","label":"Category","displayType":"checkbox"} /-->
<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"author","label":"Author"} /-->
<!-- wp:pikari-gutenberg-query-filter/sort {"label":"Sort by"} /-->`
)}`,
	},
	search: {
		slug: 'search',
		content: `<!-- wp:query-title {"type":"search"} /-->
<!-- wp:search {"label":"Search","buttonText":"Search"} /-->
${inheritedQueryBlock(
	`<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"post-type","label":"Type"} /-->
<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"taxonomy","taxonomy":"category","label":"Category","displayType":"checkbox"} /-->
<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"author","label":"Author"} /-->
<!-- wp:pikari-gutenberg-query-filter/sort {"label":"Sort by"} /-->`
)}`,
	},
};

const PAGES = {
	filters: {
		slug: 'e2e-filters',
		path: '/e2e-filters/',
		queryId: 1,
		title: 'E2E filters',
		content: `${queryBlock(
			1,
			`<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"taxonomy","taxonomy":"category","label":"Category","displayType":"checkbox"} /-->
<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"author","label":"Author"} /-->
<!-- wp:search {"label":"Search","buttonText":"Search"} /-->`
		)}
${injectedStyleBlock}`,
	},
	enhanced: {
		slug: 'e2e-enhanced',
		path: '/e2e-enhanced/',
		queryId: 2,
		title: 'E2E enhanced pagination',
		content: `${queryBlock(
			2,
			'<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"taxonomy","taxonomy":"category","label":"Topic","displayType":"radio"} /-->',
			{ enhancedPagination: true }
		)}
${injectedStyleBlock}`,
	},
	sortOnly: {
		slug: 'e2e-sort-only',
		path: '/e2e-sort-only/',
		queryId: 3,
		title: 'E2E sort only',
		content: queryBlock(
			3,
			'<!-- wp:pikari-gutenberg-query-filter/sort {"label":"Sort by"} /-->'
		),
	},
};

module.exports = {
	AUTHOR_SLUG,
	AUTHORS,
	CATEGORIES,
	INJECTED_COLOR,
	PAGES,
	POSTS,
	STICKY_TITLE,
	TEMPLATES,
	newestTitles,
	titlesByTitle,
};
