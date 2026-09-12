import getOptionClassName from '../../../src/utils/option-class-name';

const category = { filterType: 'taxonomy', taxonomy: 'category' };

describe( 'getOptionClassName', () => {
	it( 'should use the taxonomy name as the key for taxonomy filters', () => {
		expect( getOptionClassName( category, 'news' ) ).toBe(
			'category_news'
		);
	} );

	it( 'should use the filter type as the key for post type and author filters', () => {
		expect(
			getOptionClassName( { filterType: 'post-type' }, 'page' )
		).toBe( 'post-type_page' );
		expect(
			getOptionClassName( { filterType: 'author' }, 'jane-doe' )
		).toBe( 'author_jane-doe' );
	} );

	it( 'should strip characters that are invalid in a class name', () => {
		expect( getOptionClassName( category, 'big news!' ) ).toBe(
			'category_bignews'
		);
	} );

	it( 'should strip percent-encoded octets like sanitize_html_class', () => {
		expect( getOptionClassName( category, 'caf%c3%a9' ) ).toBe(
			'category_caf'
		);
	} );

	it( 'should return an empty string when the slug sanitizes to nothing', () => {
		expect( getOptionClassName( category, '%e6%97%a5' ) ).toBe( '' );
		expect( getOptionClassName( category, '' ) ).toBe( '' );
		expect( getOptionClassName( category, undefined ) ).toBe( '' );
	} );
} );
