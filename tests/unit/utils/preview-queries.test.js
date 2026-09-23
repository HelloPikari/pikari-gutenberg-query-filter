import {
	AUTHOR_PREVIEW_QUERY,
	TERM_PREVIEW_QUERY,
} from '../../../src/utils/preview-queries';

// Each query must ask REST for what the frontend helper renders. The keys
// are REST's, not get_terms()' or WP_User_Query's: REST ignores `number`.
describe( 'preview queries', () => {
	it( 'should match FilterHelper::get_taxonomy_filter_terms()', () => {
		expect( TERM_PREVIEW_QUERY ).toEqual( {
			per_page: 100,
			hide_empty: true,
		} );
	} );

	it( 'should match AuthorHelper::get_filter_authors(), readable below Administrator', () => {
		expect( AUTHOR_PREVIEW_QUERY ).toEqual( {
			per_page: 100,
			has_published_posts: true,
			context: 'view',
		} );
	} );
} );
