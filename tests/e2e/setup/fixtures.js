/**
 * Global setup: rebuild the fixture content on the wp-env tests instance.
 *
 * Runs after the @wordpress/scripts global setup has saved the admin storage
 * state. Everything it creates is deleted and recreated on every run.
 */
const fs = require('fs');
const path = require('path');
const { request } = require('@playwright/test');
const { RequestUtils } = require('@wordpress/e2e-test-utils-playwright');
const { AUTHORS, CATEGORIES, PAGES, POSTS } = require('../fixtures/content');

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

	await requestContext.dispose();
};
