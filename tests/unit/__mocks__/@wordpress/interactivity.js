/**
 * Mock for @wordpress/interactivity.
 *
 * The Interactivity API uses generator functions (function*) with yield
 * instead of async/await. This mock provides testable versions of store(),
 * getContext(), getElement(), withScope(), and withSyncEvent().
 *
 * Usage in tests:
 *
 *   import { store, getContext, getElement } from '@wordpress/interactivity';
 *
 *   // Import the module under test (calls store() at module scope):
 *   import '../../src/frontend/modal-store';
 *
 *   // Retrieve the registered store:
 *   const { state, actions } = store.getStore( 'pikari-modal' );
 *
 *   // Set up context for a test:
 *   getContext.mockReturnValue( { postId: 123, modalId: 'test-modal' } );
 *
 *   // Run a generator action:
 *   const gen = actions.openModal();
 *   const result = gen.next(); // First yield
 *   gen.next( mockResponse );  // Resume with yielded value
 */

const store = jest.fn( ( storeName, storeDefinition ) => {
	return storeDefinition;
} );

// Retrieve the last registered store definition.
store.getLastStore = () => {
	const { calls } = store.mock;
	if ( calls.length === 0 ) {
		throw new Error( 'store() has not been called yet' );
	}
	return calls[ calls.length - 1 ][ 1 ];
};

// Retrieve a store by name.
store.getStore = ( name ) => {
	const call = store.mock.calls.find( ( c ) => c[ 0 ] === name );
	if ( ! call ) {
		throw new Error( `store("${ name }") has not been called` );
	}
	return call[ 1 ];
};

const getContext = jest.fn( () => ( {} ) );

const getElement = jest.fn( () => ( {
	ref: document.createElement( 'div' ),
} ) );

const withScope = jest.fn( ( callback ) => callback );

const withSyncEvent = jest.fn( ( handler ) => handler );

module.exports = {
	store,
	getContext,
	getElement,
	withScope,
	withSyncEvent,
};
