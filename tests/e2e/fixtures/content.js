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
	{ username: 'sam-lee', name: 'Sam Lee' },
];

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
}));

/**
 * Titles of the newest posts matching a predicate, newest first.
 *
 * @param {Function} predicate Receives a POSTS entry.
 * @param {number}   count     Posts per page.
 * @return {string[]} Titles.
 */
const newestTitles = (predicate = () => true, count = 5) =>
	POSTS.filter(predicate)
		.reverse()
		.slice(0, count)
		.map((post) => post.title);

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
	AUTHORS,
	CATEGORIES,
	INJECTED_COLOR,
	PAGES,
	POSTS,
	newestTitles,
	titlesByTitle,
};
