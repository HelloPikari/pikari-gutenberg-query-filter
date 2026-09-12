import getOptionClassName from '../../../src/utils/option-class-name';

describe( 'getOptionClassName', () => {
	it( 'should join the filter key and option slug with an underscore', () => {
		expect( getOptionClassName( 'category', 'news' ) ).toBe(
			'category_news'
		);
		expect( getOptionClassName( 'post-type', 'page' ) ).toBe(
			'post-type_page'
		);
		expect( getOptionClassName( 'author', 'jane-doe' ) ).toBe(
			'author_jane-doe'
		);
	} );

	it( 'should strip characters that are invalid in a class name', () => {
		expect( getOptionClassName( 'category', 'big news!' ) ).toBe(
			'category_bignews'
		);
	} );

	it( 'should strip percent-encoded octets like sanitize_html_class', () => {
		expect( getOptionClassName( 'category', 'caf%c3%a9' ) ).toBe(
			'category_caf'
		);
	} );

	it( 'should return an empty string when the slug sanitizes to nothing', () => {
		expect( getOptionClassName( 'category', '%e6%97%a5' ) ).toBe( '' );
		expect( getOptionClassName( 'category', '' ) ).toBe( '' );
		expect( getOptionClassName( 'category', undefined ) ).toBe( '' );
	} );
} );
