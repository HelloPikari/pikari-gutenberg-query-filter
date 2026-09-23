import {
	buildUrl,
	sameQuery,
	stripInheritedPagination,
} from '../../../src/utils/build-url';

const BASE = 'https://example.com/library/';

/**
 * Parameters of a built URL, as a plain object.
 *
 * @param {string} url URL.
 * @return {Object} Decoded parameters.
 */
const params = ( url ) =>
	Object.fromEntries( new URL( url ).searchParams.entries() );

/**
 * Default buildUrl() options, for tests that only need to override a few.
 *
 * @param {Object} overrides Options to override.
 * @return {Object} Options.
 */
const options = ( overrides = {} ) => ( {
	entries: [],
	ownedNames: [ 'query-3-category', 'query-3-sort' ],
	pageKey: 'query-3-page',
	inherit: false,
	paginationBase: 'page',
	...overrides,
} );

describe( 'buildUrl', () => {
	it( 'writes an owned control value', () => {
		const url = buildUrl(
			BASE,
			options( { entries: [ [ 'query-3-category', 'news' ] ] } )
		);

		expect( params( url ) ).toEqual( { 'query-3-category': 'news' } );
	} );

	it( 'joins repeated values of one name with commas', () => {
		const url = buildUrl(
			BASE,
			options( {
				entries: [
					[ 'query-3-category[]', 'news' ],
					[ 'query-3-category[]', 'events' ],
				],
			} )
		);

		expect( params( url ) ).toEqual( { 'query-3-category': 'news,events' } );
	} );

	it( 'drops an owned name with no value', () => {
		const url = buildUrl(
			`${ BASE }?query-3-category=news`,
			options( { entries: [ [ 'query-3-category', '' ] ] } )
		);

		expect( params( url ) ).toEqual( {} );
	} );

	it( 'drops a value that is only whitespace', () => {
		const url = buildUrl(
			BASE,
			options( {
				ownedNames: [ 'query-3-s' ],
				entries: [ [ 'query-3-s', '   ' ] ],
			} )
		);

		expect( params( url ) ).toEqual( {} );
	} );

	it( 'trims the value it writes', () => {
		const url = buildUrl(
			BASE,
			options( {
				ownedNames: [ 'query-3-s' ],
				entries: [ [ 'query-3-s', '  cats  ' ] ],
			} )
		);

		expect( params( url ) ).toEqual( { 'query-3-s': 'cats' } );
	} );

	it( 'leaves a parameter the form does not own', () => {
		// Hidden inputs are submitted but never owned: buildUrl must not
		// write them, even when the form carries a different value.
		const url = buildUrl(
			`${ BASE }?utm_source=newsletter&s=cats`,
			options( {
				entries: [
					[ 'utm_source', 'hijacked' ],
					[ 's', 'dogs' ],
				],
			} )
		);

		expect( params( url ) ).toEqual( {
			utm_source: 'newsletter',
			s: 'cats',
		} );
	} );

	it( 'does not add a parameter the form does not own', () => {
		const url = buildUrl(
			BASE,
			options( { entries: [ [ 'utm_source', 'newsletter' ] ] } )
		);

		expect( params( url ) ).toEqual( {} );
	} );

	it( 'resets the page key, page and cst', () => {
		const url = buildUrl(
			`${ BASE }?query-3-page=2&page=4&cst=1&lang=fr`,
			options()
		);

		expect( params( url ) ).toEqual( { lang: 'fr' } );
	} );

	it( 'drops a fragment', () => {
		expect( buildUrl( `${ BASE }#results`, options() ) ).toBe( BASE );
	} );

	it( 'strips an inherited pagination segment', () => {
		const url = buildUrl(
			'https://example.com/category/news/page/2/',
			options( {
				pageKey: 'paged',
				inherit: true,
				ownedNames: [ 'query-category' ],
			} )
		);

		expect( new URL( url ).pathname ).toBe( '/category/news/' );
	} );

	it( 'keeps the trailing-slash style when stripping pagination', () => {
		const url = buildUrl(
			'https://example.com/category/news/page/2',
			options( {
				pageKey: 'paged',
				inherit: true,
				ownedNames: [ 'query-category' ],
			} )
		);

		expect( new URL( url ).pathname ).toBe( '/category/news' );
	} );

	it( 'strips a root-level inherited pagination segment', () => {
		// The PHP side builds the same no-JS action for this request
		// (LoopFormTest::test_action_strips_a_root_level_pagination_segment).
		// Both must resolve `/page/2` to `/`, or filtering from page 2 of a
		// root-level inherited loop keeps a page the filtered query may not
		// have.
		const url = buildUrl(
			'https://example.com/page/2',
			options( {
				pageKey: 'paged',
				inherit: true,
				ownedNames: [ 'query-category' ],
			} )
		);

		expect( new URL( url ).pathname ).toBe( '/' );
	} );

	it( 'leaves a custom loop path alone', () => {
		const url = buildUrl( 'https://example.com/library/page/2/', options() );

		expect( new URL( url ).pathname ).toBe( '/library/page/2/' );
	} );

	it( 'handles a loop with no queryId', () => {
		const url = buildUrl(
			`${ BASE }?query-page=3`,
			options( {
				pageKey: 'query-page',
				ownedNames: [ 'query-0-category' ],
				entries: [ [ 'query-0-category', 'news' ] ],
			} )
		);

		expect( params( url ) ).toEqual( { 'query-0-category': 'news' } );
	} );

	it( 'keeps a percent-encoded slug intact', () => {
		const url = buildUrl(
			BASE,
			options( { entries: [ [ 'query-3-category', '%e6%96%b0%e9%97%bb' ] ] } )
		);

		expect( params( url ) ).toEqual( {
			'query-3-category': '%e6%96%b0%e9%97%bb',
		} );
	} );
} );

describe( 'sameQuery', () => {
	it( 'treats a comma list and its encoded form as equal', () => {
		expect(
			sameQuery(
				`${ BASE }?query-3-category=news,events`,
				`${ BASE }?query-3-category=news%2Cevents`
			)
		).toBe( true );
	} );

	it( 'treats repeated bracket names as one comma list', () => {
		expect(
			sameQuery(
				`${ BASE }?query-3-category[]=news&query-3-category[]=events`,
				`${ BASE }?query-3-category=news,events`
			)
		).toBe( true );
	} );

	it( 'ignores parameter order', () => {
		expect( sameQuery( `${ BASE }?a=1&b=2`, `${ BASE }?b=2&a=1` ) ).toBe(
			true
		);
	} );

	it( 'notices a different path', () => {
		expect( sameQuery( BASE, 'https://example.com/other/' ) ).toBe( false );
	} );

	it( 'notices a different value', () => {
		expect(
			sameQuery(
				`${ BASE }?query-3-category=news`,
				`${ BASE }?query-3-category=events`
			)
		).toBe( false );
	} );

	it( 'notices an extra parameter', () => {
		expect( sameQuery( BASE, `${ BASE }?a=1` ) ).toBe( false );
	} );
} );

describe( 'stripInheritedPagination', () => {
	it( 'removes a trailing pagination segment with a trailing slash', () => {
		expect(
			stripInheritedPagination( '/category/news/page/2/', 'page' )
		).toBe( '/category/news/' );
	} );

	it( 'preserves a path that had no trailing slash, rather than adding one', () => {
		expect(
			stripInheritedPagination( '/category/news/page/2', 'page' )
		).toBe( '/category/news' );
	} );

	it( 'honours a non-default pagination base', () => {
		expect(
			stripInheritedPagination( '/kategorie/neuigkeiten/seite/2/', 'seite' )
		).toBe( '/kategorie/neuigkeiten/' );
	} );

	it( 'leaves a path that merely contains the word "page" untouched', () => {
		expect( stripInheritedPagination( '/page-two/', 'page' ) ).toBe(
			'/page-two/'
		);
	} );

	it( 'leaves nothing of a root-level paginated path', () => {
		expect( stripInheritedPagination( '/page/2', 'page' ) ).toBe( '' );
	} );

	it( 'only strips a trailing segment, not one followed by more path', () => {
		expect(
			stripInheritedPagination( '/blog/page/2/extra/', 'page' )
		).toBe( '/blog/page/2/extra/' );
	} );

	it( 'leaves a path with no pagination segment untouched', () => {
		expect( stripInheritedPagination( '/category/news/', 'page' ) ).toBe(
			'/category/news/'
		);
	} );
} );
