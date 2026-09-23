/*
 * REST query args for the editor preview, so it lists what render.php will.
 * Keep in step with FilterHelper::get_taxonomy_filter_terms() and
 * AuthorHelper::get_filter_authors().
 */

export const TERM_PREVIEW_QUERY = {
	per_page: 100,
	hide_empty: true,
};

export const AUTHOR_PREVIEW_QUERY = {
	per_page: 100,
	has_published_posts: true,
	// core-data requests users with context=edit, which REST refuses to
	// anyone without list_users, leaving Editors with an empty preview.
	context: 'view',
};
