/**
 * Global setup: rebuild the fixture content on the wp-env tests instance.
 *
 * Runs after the @wordpress/scripts global setup has saved the admin storage
 * state. Everything it creates is deleted and recreated on every run.
 */
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { request } = require('@playwright/test');
const { RequestUtils } = require('@wordpress/e2e-test-utils-playwright');
const {
	AUTHORS,
	CATEGORIES,
	NON_LATIN_AUTHOR,
	NON_LATIN_CATEGORY,
	PAGES,
	POSTS,
	TEMPLATES,
} = require('../fixtures/content');

// wp-env's default active theme (Twenty Twenty-Five).
const THEME = 'twentytwentyfive';

const deleteCategories = async (requestUtils) => {
	const categories = await requestUtils.rest({
		path: '/wp/v2/categories',
		params: { per_page: 100 },
	});

	// Category 1 is the default category, which WordPress won't delete.
	await Promise.all(
		categories
			.filter((category) => category.id !== 1)
			.map((category) =>
				requestUtils.rest({
					method: 'DELETE',
					path: `/wp/v2/categories/${category.id}`,
					params: { force: true },
				})
			)
	);
};

module.exports = async function fixtures(config) {
	const buildDir = path.resolve(__dirname, '../../../build/blocks');
	if (!fs.existsSync(buildDir)) {
		throw new Error(
			'build/blocks is missing. Run `npm run build` before `npm run test:e2e`.'
		);
	}

	const { storageState, baseURL } = config.projects[0].use;
	const requestContext = await request.newContext({ baseURL });
	const requestUtils = new RequestUtils(requestContext, {
		storageStatePath: storageState,
	});
	await requestUtils.setupRest();

	await requestUtils.deleteAllPosts();
	await requestUtils.deleteAllPages();
	await requestUtils.deleteAllUsers();
	await deleteCategories(requestUtils);
	await requestUtils.deleteAllTemplates('wp_template');

	// Five per page, matching newestTitles()'s default count, so the News
	// category's six posts (spec-fixture POSTS, filtered to `news`) span two
	// pages and inherited-loop pagination (tests/e2e/specs/inherited-loops.spec.js)
	// has a real page 2 to reset from. The default of 10 would leave News on
	// a single page. Custom Query Loop fixtures (PAGES below) set their own
	// perPage and are unaffected.
	await requestUtils.rest({
		method: 'POST',
		path: '/wp/v2/settings',
		data: { posts_per_page: 5 },
	});

	const categoryIds = {};
	for (const { name, slug } of CATEGORIES) {
		const term = await requestUtils.rest({
			method: 'POST',
			path: '/wp/v2/categories',
			data: { name, slug },
		});
		categoryIds[slug] = term.id;
	}

	// The default "Uncategorized" category can't be deleted (see
	// deleteCategories() above), so reuse it rather than create a duplicate.
	const [uncategorized] = await requestUtils.rest({
		path: '/wp/v2/categories',
		params: { slug: 'uncategorized' },
	});
	categoryIds.uncategorized = uncategorized.id;

	// Created from a name alone, so WordPress generates the slug rather than
	// this file assuming one — the path that produces a percent-encoded
	// slug (roadmap #33). 'nonLatin' is only the lookup key below, not the
	// generated slug itself; specs read the real slug from the DOM.
	const nonLatinTerm = await requestUtils.rest({
		method: 'POST',
		path: '/wp/v2/categories',
		data: { name: NON_LATIN_CATEGORY.name },
	});
	categoryIds.nonLatin = nonLatinTerm.id;

	const authorIds = {};
	for (const { username, name, slug } of AUTHORS) {
		const user = await requestUtils.rest({
			method: 'POST',
			path: '/wp/v2/users',
			data: {
				username,
				name,
				email: `${username}@example.com`,
				password: 'password',
				roles: ['author'],
				...(slug ? { slug } : {}),
			},
		});
		authorIds[username] = user.id;
	}

	// wp_insert_user() runs every user_nicename through sanitize_user(…,
	// true) before sanitize_title() ever sees it (wp-includes/user.php),
	// and that strict pass strips non-ASCII bytes and percent-encoded
	// octets alike — proven directly:
	//   sanitize_user( '山田太郎', true )                    === ''
	//   sanitize_user( sanitize_title( '山田太郎' ), true )   === ''
	// So no REST call or wp-cli command can produce a non-Latin nicename;
	// every such nicename in production is legacy, imported, or written to
	// the users table directly. This fixture does the same, over wp-env's
	// tests-cli container — a dependency this file already has (the
	// build/blocks check above, and the tests instance baseURL).
	const nonLatinAuthor = await requestUtils.rest({
		method: 'POST',
		path: '/wp/v2/users',
		data: {
			username: NON_LATIN_AUTHOR.username,
			name: NON_LATIN_AUTHOR.name,
			email: `${NON_LATIN_AUTHOR.username}@example.com`,
			password: 'password',
			roles: ['author'],
		},
	});
	authorIds[NON_LATIN_AUTHOR.username] = nonLatinAuthor.id;

	execFileSync(
		'npx',
		[
			'wp-env',
			'run',
			'tests-cli',
			'--',
			'wp',
			'eval',
			`global $wpdb; $wpdb->update( $wpdb->users, array( 'user_nicename' => sanitize_title( ${JSON.stringify(
				NON_LATIN_AUTHOR.slug
			)} ) ), array( 'ID' => ${nonLatinAuthor.id} ) ); clean_user_cache( ${
				nonLatinAuthor.id
			} );`,
		],
		{ stdio: 'inherit' }
	);

	for (const post of POSTS) {
		await requestUtils.rest({
			method: 'POST',
			path: '/wp/v2/posts',
			data: {
				title: post.title,
				status: 'publish',
				date: post.date,
				categories: [categoryIds[post.category]],
				author: authorIds[post.author],
				...(post.sticky ? { sticky: true } : {}),
			},
		});
	}

	for (const page of Object.values(PAGES)) {
		await requestUtils.rest({
			method: 'POST',
			path: '/wp/v2/pages',
			data: {
				title: page.title,
				slug: page.slug,
				status: 'publish',
				content: page.content,
			},
		});
	}

	for (const template of Object.values(TEMPLATES)) {
		await requestUtils.createTemplate('wp_template', {
			slug: template.slug,
			content: template.content,
			theme: THEME,
		});
	}

	await requestContext.dispose();
};
