import {
	NOTICE,
	RENDERS_NOTHING,
	getFilterNotices,
	getNoticeMessage,
	hasDuplicateFilter,
} from '../../../src/utils/filter-notices';

const FILTER = 'pikari-gutenberg-query-filter/query-filter';

/**
 * Block-editor selectors over a fixed tree of { name, parent, attrs }.
 *
 * @param {Object} tree Blocks keyed by client ID.
 * @return {Object} The four selectors hasDuplicateFilter() uses.
 */
const selectorsFor = ( tree ) => {
	const ancestors = ( id ) => {
		const list = [];
		for ( let p = tree[ id ].parent; p; p = tree[ p ].parent ) {
			list.push( p );
		}
		return list;
	};

	return {
		getBlockName: ( id ) => tree[ id ].name,
		getBlockAttributes: ( id ) => tree[ id ].attrs,
		getBlockParentsByBlockName: ( id, name, ascending = false ) => {
			const matches = ancestors( id ).filter(
				( p ) => tree[ p ].name === name
			);
			return ascending ? matches : matches.reverse();
		},
		getClientIdsOfDescendants: ( root ) =>
			Object.keys( tree ).filter( ( id ) =>
				ancestors( id ).includes( root )
			),
	};
};

const filter = ( parent, attrs ) => ( { name: FILTER, parent, attrs } );
const category = { filterType: 'taxonomy', taxonomy: 'category' };

describe( 'hasDuplicateFilter', () => {
	const tree = {
		loop: { name: 'core/query', parent: null },
		group: { name: 'core/group', parent: 'loop' },
		first: filter( 'loop', category ),
		second: filter( 'group', category ),
		tags: filter( 'loop', { filterType: 'taxonomy', taxonomy: 'post_tag' } ),
		author: filter( 'loop', { filterType: 'author' } ),
		// A nested loop is a separate loop with its own parameters.
		inner: { name: 'core/query', parent: 'loop' },
		innerTags: filter( 'inner', {
			filterType: 'taxonomy',
			taxonomy: 'post_tag',
		} ),
		innerAuthor: filter( 'inner', { filterType: 'author' } ),
		sort: {
			name: 'pikari-gutenberg-query-filter/sort',
			parent: 'loop',
			attrs: {},
		},
		orphan: filter( null, category ),
	};
	const selectors = selectorsFor( tree );

	it( 'should flag two filters on the same taxonomy in one loop, at any depth', () => {
		expect( hasDuplicateFilter( selectors, 'first' ) ).toBe( true );
		expect( hasDuplicateFilter( selectors, 'second' ) ).toBe( true );
	} );

	it( 'should not flag a filter on a different taxonomy', () => {
		expect( hasDuplicateFilter( selectors, 'tags' ) ).toBe( false );
	} );

	it( 'should not count filters in a nested loop against the outer loop', () => {
		expect( hasDuplicateFilter( selectors, 'author' ) ).toBe( false );
		expect( hasDuplicateFilter( selectors, 'innerTags' ) ).toBe( false );
		expect( hasDuplicateFilter( selectors, 'innerAuthor' ) ).toBe( false );
	} );

	it( 'should not flag a filter outside any Query Loop', () => {
		expect( hasDuplicateFilter( selectors, 'orphan' ) ).toBe( false );
	} );

	it( 'should flag two post type or two author filters', () => {
		const pair = selectorsFor( {
			loop: { name: 'core/query', parent: null },
			a: filter( 'loop', { filterType: 'post-type' } ),
			b: filter( 'loop', { filterType: 'post-type' } ),
			c: filter( 'loop', { filterType: 'author' } ),
			d: filter( 'loop', { filterType: 'author' } ),
		} );

		expect( hasDuplicateFilter( pair, 'a' ) ).toBe( true );
		expect( hasDuplicateFilter( pair, 'c' ) ).toBe( true );
	} );

	it( 'should not flag two taxonomy filters that have no taxonomy yet', () => {
		const unset = selectorsFor( {
			loop: { name: 'core/query', parent: null },
			a: filter( 'loop', { filterType: 'taxonomy' } ),
			b: filter( 'loop', { filterType: 'taxonomy' } ),
		} );

		expect( hasDuplicateFilter( unset, 'a' ) ).toBe( false );
	} );
} );

describe( 'getFilterNotices', () => {
	it( 'should return nothing for a working filter', () => {
		expect(
			getFilterNotices( category, {
				optionsResolved: true,
				optionCount: 3,
			} )
		).toEqual( [] );
	} );

	it( 'should report a duplicate first', () => {
		expect(
			getFilterNotices(
				{ filterType: 'taxonomy' },
				{ hasDuplicate: true }
			)
		).toEqual( [ NOTICE.DUPLICATE, NOTICE.NO_TAXONOMY ] );
	} );

	it( 'should report a taxonomy filter with no taxonomy', () => {
		expect( getFilterNotices( { filterType: 'taxonomy' } ) ).toEqual( [
			NOTICE.NO_TAXONOMY,
		] );
	} );

	it( 'should report no terms only once the terms have loaded', () => {
		expect(
			getFilterNotices( category, { optionsResolved: false } )
		).toEqual( [] );
		expect(
			getFilterNotices( category, {
				optionsResolved: true,
				optionCount: 0,
			} )
		).toEqual( [ NOTICE.NO_TERMS ] );
	} );

	it( 'should report no authors only once the authors have loaded', () => {
		const author = { filterType: 'author' };

		expect(
			getFilterNotices( author, { optionsResolved: false } )
		).toEqual( [] );
		expect(
			getFilterNotices( author, {
				optionsResolved: true,
				optionCount: 0,
			} )
		).toEqual( [ NOTICE.NO_AUTHORS ] );
	} );

	it( 'should not report empty options for a post type filter', () => {
		expect(
			getFilterNotices(
				{ filterType: 'post-type' },
				{ optionsResolved: true, optionCount: 0 }
			)
		).toEqual( [] );
	} );
} );

describe( 'getNoticeMessage', () => {
	it.each( Object.values( NOTICE ) )(
		'should have a message for %s',
		( key ) => {
			expect( getNoticeMessage( key ) ).toEqual( expect.any( String ) );
			expect( getNoticeMessage( key ).length ).toBeGreaterThan( 0 );
		}
	);

	it( 'should treat every notice except a duplicate as rendering nothing', () => {
		expect( RENDERS_NOTHING ).toEqual( [
			NOTICE.NO_TAXONOMY,
			NOTICE.NO_TERMS,
			NOTICE.NO_AUTHORS,
		] );
	} );
} );
