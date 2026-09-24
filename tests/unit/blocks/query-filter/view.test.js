/**
 * The store behind every filter control.
 *
 * Each test builds a real form in the JSDOM document, so `form.elements` and
 * `FormData` do the work they do in a browser: the controls join the form by
 * their `form` attribute, wherever they sit in the DOM.
 *
 * The injected-style tests are the pre-existing ones. Only their driver
 * changed — `handleSelect` is gone, so they navigate through `actions.navigate`
 * directly. The router disables every stylesheet that is not in the fetched
 * page's HTML, including styles other scripts injected at runtime, and view.js
 * re-enables those.
 */

jest.mock( '@wordpress/a11y', () => ( { speak: jest.fn() } ), {
	virtual: true,
} );

let actions;
let callbacks;
let getContext;
let navigate;
let routerState;
let speak;
let withSyncEvent;

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

/**
 * Build a loop form and its controls in the document.
 *
 * @param {Object}  options
 * @param {string}  options.pageKey Page parameter name.
 * @param {boolean} options.inherit Whether the loop inherits the main query.
 * @return {HTMLFormElement} The form.
 */
function addForm( { pageKey = 'query-3-page', inherit = false } = {} ) {
	document.body.innerHTML = `
		<div data-wp-interactive="pikari/gutenberg-query-filter">
			<select name="query-3-category" form="f"><option value=""></option><option value="news">News</option><option value="events">Events</option></select>
			<input type="search" name="query-3-s" form="f" value="" />
			<form id="f" method="get" action="/library/"
				data-query-page-key="${ pageKey }"
				data-query-inherit="${ inherit }"
				data-query-pagination-base="page">
				<input type="hidden" name="lang" value="fr" />
			</form>
		</div>`;
	return document.getElementById( 'f' );
}

/**
 * Hand the store an event, as the Interactivity API's directive would.
 *
 * @param {HTMLElement} control The control the event came from.
 * @param {string}      type    Event type: change, input or compositionend.
 * @param {Object}      [extra] Further event properties.
 */
function fire( control, type, extra = {} ) {
	actions.change( { type, target: control, ...extra } );
}

/**
 * Let a navigation's promise chain run, without advancing the clock.
 *
 * @return {Promise} Resolves once the pending microtasks have run.
 */
function flush() {
	return jest.advanceTimersByTimeAsync( 0 );
}

describe( 'pikari/gutenberg-query-filter view', () => {
	beforeEach( () => {
		// view.js keeps per-page state, so load a fresh copy for each test.
		jest.resetModules();
		let store;
		( {
			store,
			getContext,
			withSyncEvent,
		} = require( '@wordpress/interactivity' ) );
		( {
			actions: { navigate },
		} = require( '@wordpress/interactivity-router' ) );
		( { speak } = require( '@wordpress/a11y' ) );
		require( '../../../../src/blocks/query-filter/view' );
		( { actions, callbacks } = store.getStore(
			'pikari/gutenberg-query-filter'
		) );
		( { state: routerState } = store( 'core/router' ) );
	} );

	afterEach( () => {
		window.history.replaceState( null, '', '/' );
	} );

	it( 'should re-enable script-injected styles the router disables', async () => {
		const enqueued = addStyle( 'core-block-supports-inline-css' );
		const injected = addStyle();
		navigate.mockImplementation( () => {
			enqueued.sheet.disabled = true;
			injected.sheet.disabled = true;
			return Promise.resolve();
		} );

		await run( actions.navigate( 'http://localhost/?query-3-category=news' ) );

		expect( injected.sheet.disabled ).toBe( false );
		expect( enqueued.sheet.disabled ).toBe( true );
	} );

	it( 'should leave styles the router added from a fetched page to the router', async () => {
		let fromFetchedPage;
		navigate.mockImplementationOnce( () => {
			fromFetchedPage = addStyle();
			return Promise.resolve();
		} );
		await run( actions.navigate( 'http://localhost/?query-3-category=news' ) );

		navigate.mockImplementationOnce( () => {
			fromFetchedPage.sheet.disabled = true;
			return Promise.resolve();
		} );
		await run(
			actions.navigate( 'http://localhost/?query-3-category=events' )
		);

		expect( fromFetchedPage.sheet.disabled ).toBe( true );
	} );

	it( 'should re-enable script-injected styles after back/forward navigation', async () => {
		const injected = addStyle();
		await run( actions.navigate( 'http://localhost/?query-3-category=news' ) );

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

	describe( 'form-driven navigation', () => {
		let form;
		let select;
		let search;

		beforeEach( () => {
			jest.useFakeTimers();
			form = addForm();
			select = document.querySelector( 'select' );
			search = document.querySelector( 'input[type="search"]' );
		} );

		afterEach( () => {
			jest.useRealTimers();
		} );

		it( 'navigates 250ms after a filter changes, and not before', async () => {
			select.value = 'news';
			fire( select, 'change' );

			await jest.advanceTimersByTimeAsync( 249 );
			expect( navigate ).not.toHaveBeenCalled();

			await jest.advanceTimersByTimeAsync( 1 );
			expect( navigate ).toHaveBeenCalledWith(
				'http://localhost/?query-3-category=news',
				{ screenReaderAnnouncement: false }
			);
		} );

		it( 'waits longer for a keystroke than for a filter change', async () => {
			search.value = 'mango';
			fire( search, 'input' );

			await jest.advanceTimersByTimeAsync( 250 );
			expect( navigate ).not.toHaveBeenCalled();

			await jest.advanceTimersByTimeAsync( 150 );
			expect( navigate ).toHaveBeenCalledWith(
				'http://localhost/?query-3-s=mango',
				{ screenReaderAnnouncement: false }
			);
		} );

		it( 'replaces a pending navigation with the later one', async () => {
			select.value = 'news';
			fire( select, 'change' );

			await jest.advanceTimersByTimeAsync( 100 );
			select.value = 'events';
			fire( select, 'change' );

			await jest.advanceTimersByTimeAsync( 400 );
			expect( navigate ).toHaveBeenCalledTimes( 1 );
			expect( navigate ).toHaveBeenCalledWith(
				'http://localhost/?query-3-category=events',
				{ screenReaderAnnouncement: false }
			);
		} );

		it( 'ignores a keystroke that is still part of a composition', async () => {
			search.value = 'mang';
			fire( search, 'input', { isComposing: true } );

			expect( jest.getTimerCount() ).toBe( 0 );

			await jest.advanceTimersByTimeAsync( 400 );
			expect( navigate ).not.toHaveBeenCalled();
		} );

		it( 'navigates on compositionend even while a composition is pending', async () => {
			search.value = 'mango';
			fire( search, 'compositionend', { isComposing: true } );

			await jest.advanceTimersByTimeAsync( 400 );
			expect( navigate ).toHaveBeenCalledWith(
				'http://localhost/?query-3-s=mango',
				{ screenReaderAnnouncement: false }
			);
		} );

		it( 'binds the typed text to the context before navigating', () => {
			const context = {};
			getContext.mockReturnValue( context );

			search.value = 'mang';
			fire( search, 'input' );

			expect( context.searchValue ).toBe( 'mang' );
			expect( navigate ).not.toHaveBeenCalled();
		} );

		it( 'does not navigate when the form already describes this URL', async () => {
			window.history.replaceState( null, '', '/?query-3-category=news' );
			select.value = 'news';
			fire( select, 'change' );

			expect( jest.getTimerCount() ).toBe( 0 );

			await jest.advanceTimersByTimeAsync( 400 );
			expect( navigate ).not.toHaveBeenCalled();
		} );

		it( 'cancels the pending navigation when the form returns to where it started', async () => {
			select.value = 'news';
			fire( select, 'change' );

			await jest.advanceTimersByTimeAsync( 100 );
			select.value = '';
			fire( select, 'change' );

			await jest.advanceTimersByTimeAsync( 400 );
			expect( navigate ).not.toHaveBeenCalled();
		} );

		it( 'does not navigate again to the URL already being fetched', async () => {
			navigate.mockImplementation( () => new Promise( () => {} ) );

			select.value = 'news';
			fire( select, 'change' );
			await jest.advanceTimersByTimeAsync( 250 );
			expect( navigate ).toHaveBeenCalledTimes( 1 );

			fire( select, 'change' );

			await jest.advanceTimersByTimeAsync( 400 );
			expect( navigate ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'navigates at once while a request is already out', async () => {
			navigate.mockImplementation( () => new Promise( () => {} ) );

			select.value = 'news';
			fire( select, 'change' );
			await jest.advanceTimersByTimeAsync( 250 );

			select.value = 'events';
			fire( select, 'change' );

			// It went out at once, not after a debounce delay: proven below by
			// what it was called with, since a debounce timer would still be
			// pending here alongside navigate()'s own loading-announcement timer.
			await flush();
			expect( navigate ).toHaveBeenLastCalledWith(
				'http://localhost/?query-3-category=events',
				{ screenReaderAnnouncement: false }
			);
		} );

		it( 'submits at once, cancelling anything pending', async () => {
			const preventDefault = jest.fn();

			select.value = 'news';
			fire( select, 'change' );

			actions.submit( { type: 'submit', target: form, preventDefault } );

			expect( preventDefault ).toHaveBeenCalled();

			// Proves it went out immediately, not after the cancelled debounce
			// delay: a debounce timer never fires within a single flush().
			await flush();
			expect( navigate ).toHaveBeenCalledTimes( 1 );

			await jest.advanceTimersByTimeAsync( 400 );
			expect( navigate ).toHaveBeenCalledTimes( 1 );
			expect( navigate ).toHaveBeenCalledWith(
				'http://localhost/?query-3-category=news',
				{ screenReaderAnnouncement: false }
			);
		} );

		it( 'wraps the submit handler, so preventDefault stays synchronous', () => {
			expect( withSyncEvent ).toHaveBeenCalledWith( expect.any( Function ) );
		} );

		it( 'replaces the history entry for a second search in one burst', async () => {
			search.value = 'man';
			fire( search, 'input' );
			await jest.advanceTimersByTimeAsync( 400 );

			expect( navigate ).toHaveBeenNthCalledWith(
				1,
				'http://localhost/?query-3-s=man',
				{ screenReaderAnnouncement: false }
			);

			search.value = 'mango';
			fire( search, 'input' );
			await jest.advanceTimersByTimeAsync( 400 );

			expect( navigate ).toHaveBeenNthCalledWith(
				2,
				'http://localhost/?query-3-s=mango',
				{ replace: true, screenReaderAnnouncement: false }
			);
		} );

		it( 'pushes a history entry again once the burst has ended', async () => {
			search.value = 'man';
			fire( search, 'input' );
			await jest.advanceTimersByTimeAsync( 400 );

			actions.endBurst();

			search.value = 'mango';
			fire( search, 'input' );
			await jest.advanceTimersByTimeAsync( 400 );

			expect( navigate ).toHaveBeenNthCalledWith(
				2,
				'http://localhost/?query-3-s=mango',
				{ screenReaderAnnouncement: false }
			);
		} );

		it( 'ends the burst when another control navigates', async () => {
			search.value = 'man';
			fire( search, 'input' );
			await jest.advanceTimersByTimeAsync( 400 );

			select.value = 'news';
			fire( select, 'change' );
			await jest.advanceTimersByTimeAsync( 250 );

			search.value = 'mango';
			fire( search, 'input' );
			await jest.advanceTimersByTimeAsync( 400 );

			expect( navigate ).toHaveBeenNthCalledWith(
				3,
				'http://localhost/?query-3-category=news&query-3-s=mango',
				{ screenReaderAnnouncement: false }
			);
		} );

		it( 'uses the form as it was when the event fired', async () => {
			select.value = 'news';
			fire( select, 'change' );

			select.value = 'events';

			await jest.advanceTimersByTimeAsync( 250 );

			expect( navigate ).toHaveBeenCalledWith(
				'http://localhost/?query-3-category=news',
				{ screenReaderAnnouncement: false }
			);
		} );

		describe( 'result announcements', () => {
			/**
			 * Make the router "render" a new page: replace the loop form, as the
			 * router's region swap does, carrying the new page's message.
			 *
			 * @param {string|null} message New form's message, or null for none.
			 */
			const rendersPageWith = ( message ) => {
				navigate.mockImplementationOnce( () => {
					const next = form.cloneNode( true );
					if ( null === message ) {
						delete next.dataset.queryResultsMessage;
					} else {
						next.dataset.queryResultsMessage = message;
					}
					form.replaceWith( next );
					return Promise.resolve();
				} );
			};

			beforeEach( () => {
				form.dataset.queryResultsMessage = '9 results found';
			} );

			it( 'turns off the router announcement', async () => {
				select.value = 'news';
				fire( select, 'change' );
				await jest.advanceTimersByTimeAsync( 250 );

				expect( navigate ).toHaveBeenCalledWith(
					expect.any( String ),
					expect.objectContaining( {
						screenReaderAnnouncement: false,
					} )
				);
			} );

			it( 'speaks the new page form message, not the old one', async () => {
				rendersPageWith( '3 results found' );
				select.value = 'news';
				fire( select, 'change' );
				await jest.advanceTimersByTimeAsync( 250 );
				await flush();

				expect( speak ).toHaveBeenCalledWith( '3 results found' );
				expect( speak ).not.toHaveBeenCalledWith( '9 results found' );
			} );

			it( 'announces after a submit too', async () => {
				rendersPageWith( 'No results found' );
				actions.submit( {
					type: 'submit',
					target: form,
					preventDefault: () => {},
				} );
				await flush();

				expect( speak ).toHaveBeenCalledWith( 'No results found' );
			} );

			it( 'stays silent when the new form has no message', async () => {
				rendersPageWith( null );
				select.value = 'news';
				fire( select, 'change' );
				await jest.advanceTimersByTimeAsync( 250 );
				await flush();

				expect( speak ).not.toHaveBeenCalled();
			} );

			it( 'stays silent when a newer navigation took over', async () => {
				let finishFirst;
				navigate.mockImplementationOnce(
					() =>
						new Promise( ( resolve ) => {
							finishFirst = resolve;
						} )
				);
				rendersPageWith( '1 result found' );

				select.value = 'news';
				fire( select, 'change' );
				await jest.advanceTimersByTimeAsync( 250 );

				// A second change while the first is in flight navigates at once.
				select.value = 'events';
				fire( select, 'change' );
				await flush();
				expect( speak ).toHaveBeenCalledWith( '1 result found' );

				speak.mockClear();
				finishFirst();
				await flush();

				expect( speak ).not.toHaveBeenCalled();
			} );

			it( 'speaks the router loading text only once 400ms have passed in flight', async () => {
				document.body.insertAdjacentHTML(
					'beforeend',
					'<script type="application/json" id="wp-script-module-data-@wordpress/interactivity-router">{"i18n":{"loading":"Loading page…","loaded":"Page Loaded."}}</script>'
				);
				let finish;
				navigate.mockImplementationOnce(
					() =>
						new Promise( ( resolve ) => {
							finish = resolve;
						} )
				);

				select.value = 'news';
				fire( select, 'change' );
				await jest.advanceTimersByTimeAsync( 250 );

				await jest.advanceTimersByTimeAsync( 399 );
				expect( speak ).not.toHaveBeenCalledWith( 'Loading page…' );

				await jest.advanceTimersByTimeAsync( 1 );
				expect( speak ).toHaveBeenCalledWith( 'Loading page…' );

				finish();
				await flush();
			} );

			it( 'does not speak the loading text for a fast navigation', async () => {
				document.body.insertAdjacentHTML(
					'beforeend',
					'<script type="application/json" id="wp-script-module-data-@wordpress/interactivity-router">{"i18n":{"loading":"Loading page…"}}</script>'
				);
				rendersPageWith( '3 results found' );

				select.value = 'news';
				fire( select, 'change' );
				await jest.advanceTimersByTimeAsync( 250 );
				await flush();
				await jest.advanceTimersByTimeAsync( 1000 );

				expect( speak ).not.toHaveBeenCalledWith( 'Loading page…' );
			} );
		} );
	} );
} );
