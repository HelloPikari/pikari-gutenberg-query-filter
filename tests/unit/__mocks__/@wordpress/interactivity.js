/**
 * Mock for @wordpress/interactivity.
 *
 * The Interactivity API uses generator functions (function*) with yield
 * instead of async/await. This mock provides testable versions of store(),
 * getContext(), getElement(), getConfig(), withScope(), and withSyncEvent().
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
	if ( storeDefinition ) {
		registered.set( storeName, storeDefinition );
	}

	return storeDefinition ?? readStore( storeName );
} );

// Registered store definitions, by namespace. Derived from store.mock.calls
// this would not survive jest.clearAllMocks(), which the monorepo's own
// testing examples call in beforeEach — after which store( ns ).actions would
// be undefined and every timer-driven test would throw.
const registered = new Map();

// Stores read by namespace alone, such as store( 'core/router' ).
const readStores = new Map();

// WordPress's store proxy scopes every function it hands out, which is what
// makes `store( ns ).actions.go()` run a generator action.
const scopeHandlers = {
	get: ( target, key ) => {
		const result = Reflect.get( target, key );

		if ( typeof result === 'function' ) {
			return withScope( result );
		}

		return result;
	},
};

function readStore( storeName ) {
	const definition = registered.get( storeName );

	if ( definition ) {
		return new Proxy( definition, {
			get: ( target, key ) => {
				const result = Reflect.get( target, key );

				return result && typeof result === 'object'
					? new Proxy( result, scopeHandlers )
					: result;
			},
		} );
	}

	if ( ! readStores.has( storeName ) ) {
		readStores.set( storeName, { state: {} } );
	}
	return readStores.get( storeName );
}

// Retrieve the last registered store definition.
store.getLastStore = () => {
	const { calls } = store.mock;
	if ( calls.length === 0 ) {
		throw new Error( 'store() has not been called yet' );
	}
	return calls[ calls.length - 1 ][ 1 ];
};

// Retrieve a store by name. Always the raw definition — not scoped — so
// existing tests can keep stepping generators by hand with .next().
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

const getConfig = jest.fn( () => ( {} ) );

const withScope = jest.fn( ( func ) => {
	// WordPress returns non-generators untouched; the modals plugin passes
	// plain arrow functions through here.
	if ( func?.constructor?.name !== 'GeneratorFunction' ) {
		return func;
	}

	return async ( ...args ) => {
		const generator = func( ...args );
		let result = generator.next();

		while ( ! result.done ) {
			result = generator.next( await result.value );
		}

		return result.value;
	};
} );

const withSyncEvent = jest.fn( ( handler ) => handler );

module.exports = {
	store,
	getContext,
	getElement,
	getConfig,
	withScope,
	withSyncEvent,
};
