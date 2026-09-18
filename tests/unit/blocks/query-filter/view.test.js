/**
 * The router disables every stylesheet that is not in the fetched page's HTML,
 * including styles other scripts injected at runtime. view.js re-enables those.
 */

let actions;
let callbacks;
let getContext;
let navigate;
let routerState;

const event = {
	preventDefault: () => {},
	target: { value: 'articles' },
};

/**
 * Step through a generator action, resolving each yielded promise.
 *
 * @param {Object} generator Action generator.
 */
async function run( generator ) {
	let result = generator.next();
	while ( ! result.done ) {
		result = generator.next( await result.value );
	}
}

/**
 * Append a <style> element to the document head.
 *
 * @param {string} [id] Element id. Omitted for script-injected styles.
 * @return {HTMLStyleElement} The appended element.
 */
function addStyle( id ) {
	const style = document.createElement( 'style' );
	if ( id ) {
		style.id = id;
	}
	style.textContent = '.x { color: red; }';
	document.head.appendChild( style );
	return style;
}

describe( 'pikari/gutenberg-query-filter view', () => {
	beforeEach( () => {
		// view.js keeps per-page state, so load a fresh copy for each test.
		jest.resetModules();
		let store;
		( { store, getContext } = require( '@wordpress/interactivity' ) );
		( {
			actions: { navigate },
		} = require( '@wordpress/interactivity-router' ) );
		require( '../../../../src/blocks/query-filter/view' );
		( { actions, callbacks } = store.getStore(
			'pikari/gutenberg-query-filter'
		) );
		( { state: routerState } = store( 'core/router' ) );

		getContext.mockReturnValue( {
			queryVar: 'query-3-category',
			pageVar: 'query-3-page',
		} );
	} );

	it( 'should navigate to the filtered URL', async () => {
		await run( actions.handleSelect( event ) );

		expect( navigate ).toHaveBeenCalledWith(
			'http://localhost/?query-3-category=articles'
		);
	} );

	it( 'should re-enable script-injected styles the router disables', async () => {
		const enqueued = addStyle( 'core-block-supports-inline-css' );
		const injected = addStyle();
		navigate.mockImplementation( () => {
			enqueued.sheet.disabled = true;
			injected.sheet.disabled = true;
			return Promise.resolve();
		} );

		await run( actions.handleSelect( event ) );

		expect( injected.sheet.disabled ).toBe( false );
		expect( enqueued.sheet.disabled ).toBe( true );
	} );

	it( 'should leave styles the router added from a fetched page to the router', async () => {
		let fromFetchedPage;
		navigate.mockImplementationOnce( () => {
			fromFetchedPage = addStyle();
			return Promise.resolve();
		} );
		await run( actions.handleSelect( event ) );

		navigate.mockImplementationOnce( () => {
			fromFetchedPage.sheet.disabled = true;
			return Promise.resolve();
		} );
		await run( actions.handleSelect( event ) );

		expect( fromFetchedPage.sheet.disabled ).toBe( true );
	} );

	it( 'should re-enable script-injected styles after back/forward navigation', async () => {
		const injected = addStyle();
		await run( actions.handleSelect( event ) );

		window.dispatchEvent( new window.PopStateEvent( 'popstate' ) );
		// The router re-renders the cached page in a microtask.
		injected.sheet.disabled = true;
		await new Promise( ( resolve ) => setTimeout( resolve ) );

		expect( injected.sheet.disabled ).toBe( false );
	} );

	it( 'should re-enable script-injected styles after navigation this plugin did not start', () => {
		const injected = addStyle();
		// The watch runs once on hydration, before any navigation.
		callbacks.restoreInjectedStyles();

		// Core's Query pagination navigates with the router directly.
		routerState.url = 'http://localhost/?query-3-page=2';
		injected.sheet.disabled = true;
		callbacks.restoreInjectedStyles();

		expect( injected.sheet.disabled ).toBe( false );
	} );

	it( 'should leave styles the router prefetched after hydration to the router', () => {
		callbacks.restoreInjectedStyles();
		// Hovering a pagination link prefetches the page and adds its styles.
		const fromFetchedPage = addStyle();

		routerState.url = 'http://localhost/?query-3-page=2';
		fromFetchedPage.sheet.disabled = true;
		callbacks.restoreInjectedStyles();

		expect( fromFetchedPage.sheet.disabled ).toBe( true );
	} );

	describe( 'handleSort', () => {
		beforeEach( () => {
			getContext.mockReturnValue( {
				sortVar: 'query-3-sort',
				pageVar: 'query-3-page',
			} );
		} );

		afterEach( () => {
			window.history.replaceState( null, '', '/' );
		} );

		it( 'writes a single sort parameter and drops the page parameter', async () => {
			window.history.replaceState( null, '', '/?query-3-page=2' );

			await run(
				actions.handleSort( {
					preventDefault: () => {},
					target: { value: 'title-asc' },
				} )
			);

			expect( navigate ).toHaveBeenCalledWith(
				'http://localhost/?query-3-sort=title-asc'
			);
		} );

		it( 'removes the sort parameter for an empty value', async () => {
			window.history.replaceState( null, '', '/?query-3-sort=title-asc' );

			await run(
				actions.handleSort( {
					preventDefault: () => {},
					target: { value: '' },
				} )
			);

			expect( navigate ).toHaveBeenCalledWith( 'http://localhost/' );
		} );
	} );
} );
