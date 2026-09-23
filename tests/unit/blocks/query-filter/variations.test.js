import variations from '../../../../src/blocks/query-filter/variations';

describe( 'query-filter variations', () => {
	// A stored label freezes the editor's language into the post and goes
	// stale when the taxonomy changes. The default is resolved at render.
	it.each( variations.map( ( variation ) => [ variation.name, variation ] ) )(
		'should not store a label in the %s variation',
		( name, variation ) => {
			expect( variation.attributes ).not.toHaveProperty( 'label' );
		}
	);

	it( 'should still set one filter type per variation', () => {
		expect(
			variations.map( ( variation ) => variation.attributes.filterType )
		).toEqual( [ 'post-type', 'taxonomy', 'author' ] );
	} );
} );
