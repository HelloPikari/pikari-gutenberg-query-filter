/**
 * The mock's own contract, which several suites depend on.
 *
 * WordPress's store proxy wraps every function it returns in withScope(),
 * and withScope() of a generator runs it to completion. Code that calls a
 * store action from a timer relies on that; so do its tests.
 */
import { store, withScope } from '@wordpress/interactivity';

describe( '@wordpress/interactivity mock', () => {
	it( 'passes a plain function through withScope unchanged', () => {
		// The modals plugin calls withScope with arrow functions.
		const fn = () => 'value';

		expect( withScope( fn ) ).toBe( fn );
	} );

	it( 'runs a generator wrapped in withScope', async () => {
		const seen = [];
		const wrapped = withScope( function* ( input ) {
			seen.push( yield Promise.resolve( input ) );
			return 'done';
		} );

		await expect( wrapped( 'a' ) ).resolves.toBe( 'done' );
		expect( seen ).toEqual( [ 'a' ] );
	} );

	it( 'runs a generator action read back off the store', async () => {
		const ran = [];
		store( 'test/ns', {
			actions: {
				*go( value ) {
					ran.push( yield Promise.resolve( value ) );
				},
			},
		} );

		await store( 'test/ns' ).actions.go( 'x' );

		expect( ran ).toEqual( [ 'x' ] );
	} );

	it( 'still hands getStore the raw definition', () => {
		store( 'test/raw', { actions: { *go() {} } } );

		expect(
			store.getStore( 'test/raw' ).actions.go().next
		).toBeInstanceOf( Function );
	} );

	it( 'gives an unregistered namespace an empty state', () => {
		expect( store( 'core/router' ) ).toEqual( { state: {} } );
	} );
} );
