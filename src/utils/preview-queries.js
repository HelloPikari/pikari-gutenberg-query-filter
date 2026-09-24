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
	// Mirrors AuthorHelper::author_has_published_posts(), which only counts
	// the 'post' post type. `true` would ask REST for every show_in_rest
	// post type (pages, patterns, CPTs), listing authors the frontend hides.
	has_published_posts: [ 'post' ],
	// core-data requests users with context=edit, which REST refuses to
	// anyone without list_users, leaving Editors with an empty preview.
	context: 'view',
};
