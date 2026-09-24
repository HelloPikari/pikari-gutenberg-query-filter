import { store, getContext, withSyncEvent } from '@wordpress/interactivity';
import { bareName, buildUrl, sameQuery } from '../../utils/build-url';

/*
 * The router disables every stylesheet that is not in the fetched page's HTML,
 * including styles other scripts inject at runtime, such as WPForms' honeypot CSS.
 * WordPress gives every stylesheet it prints an id, so id-less styles present
 * when the page hydrates are treated as injected and re-enabled after each
 * navigation. Capture them before the router can prefetch a page, which adds
 * that page's styles.
 */
let injectedStyles = null;

const captureInjectedStyles = () => {
	injectedStyles ??= Array.from(
		document.querySelectorAll(
			'style:not([id]), link[rel="stylesheet"]:not([id])'
		)
	);
};

const enableInjectedStyles = () => {
	injectedStyles?.forEach( ( { sheet } ) => {
		if ( sheet ) {
			sheet.disabled = false;
		}
	} );
};

// On back/forward the router re-renders the cached page in a microtask, so run after it.
window.addEventListener( 'popstate', () => setTimeout( enableInjectedStyles ) );

const NAMESPACE = 'pikari/gutenberg-query-filter';

// A filter change is one deliberate act; a keystroke is one of many, so
// typing waits longer before it costs a request (spec §6.2).
const FILTER_DELAY = 250;
const SEARCH_DELAY = 400;

// The router's own "loading" cue fires after 400ms; this matches it, since
// the router's announcements are turned off for this plugin's navigations.
const LOADING_DELAY = 400;

// The router's localized loading text, read once per page.
let loadingText;

/**
 * The router's localized "loading" text, as the router itself reads it.
 *
 * @return {string|null} Text, or null when the page doesn't carry it.
 */
const routerLoadingText = () => {
	if ( undefined === loadingText ) {
		try {
			loadingText =
				JSON.parse(
					document.getElementById(
						'wp-script-module-data-@wordpress/interactivity-router'
					)?.textContent ?? ''
				)?.i18n?.loading ?? null;
		} catch {
			loadingText = null;
		}
	}

	return loadingText;
};

/**
 * Speak a message politely, through core's shared live region.
 *
 * @param {string|null|undefined} message Message; nothing is spoken when empty.
 */
const announce = ( message ) => {
	if ( ! message ) {
		return;
	}

	import( '@wordpress/a11y' ).then(
		( { speak } ) => speak( message ),
		// As the router does, ignore a module that fails to load.
		() => {}
	);
};

// Pending debounce timers, keyed by form id, so two loops on one page don't
// cancel each other.
const timers = new Map();

// The URL the router is currently fetching, if any.
let inFlightUrl = null;

// True between the first search navigation of a typing burst and its end, so
// a pause every few keystrokes doesn't fill the history with near-identical
// entries. A burst ends on blur or on submit.
let searchBurst = false;

/**
 * Everything buildUrl() needs, read from a loop form.
 *
 * form.elements is the browser's own answer to "what filters this loop?" —
 * controls join by their `form` attribute rather than by nesting, though
 * block.json's `ancestor` still keeps them inside the loop.
 * Hidden inputs are submitted but never owned, so they pass through untouched.
 *
 * @param {HTMLFormElement} form The loop form.
 * @return {Object} buildUrl options.
 */
const formOptions = ( form ) => {
	const ownedNames = Array.from( form.elements )
		.filter( ( element ) => element.name && element.type !== 'hidden' )
		.map( ( element ) => bareName( element.name ) );

	return {
		entries: [ ...new FormData( form ) ],
		ownedNames: [ ...new Set( ownedNames ) ],
		pageKey: form.dataset.queryPageKey,
		inherit: form.dataset.queryInherit === 'true',
		paginationBase: form.dataset.queryPaginationBase,
	};
};

/**
 * Cancel a form's pending navigation, if it has one.
 *
 * @param {string} formId Form id.
 */
const cancel = ( formId ) => {
	if ( timers.has( formId ) ) {
		clearTimeout( timers.get( formId ) );
		timers.delete( formId );
	}
};

/**
 * Start the navigation, through the store so the generator actually runs.
 *
 * @param {string}  url      URL to navigate to.
 * @param {boolean} isSearch Whether a search keystroke triggered it.
 * @param {string}  formId   Id of the loop form that triggered it.
 */
const run = ( url, isSearch, formId ) => {
	const replace = isSearch && searchBurst;
	searchBurst = isSearch;

	// The store proxy scopes and runs the generator; a bare generator here
	// would never execute. It reads no context or element, so it needs no scope.
	store( NAMESPACE )
		.actions.navigate( url, replace, formId )
		.catch( () => {} );
};

store( NAMESPACE, {
	callbacks: {
		/*
		 * Watched from the Query block. Reading the router's URL re-runs it after
		 * every navigation, including core's enhanced pagination, which never
		 * calls this store's actions.
		 */
		restoreInjectedStyles() {
			captureInjectedStyles();

			if ( store( 'core/router' ).state.url ) {
				enableInjectedStyles();
			}
		},
	},
	actions: {
		/**
		 * A control changed: work out where that points, and go there.
		 *
		 * @param {Event} event change, input or compositionend.
		 */
		change( event ) {
			// Mid-composition keystrokes are not yet a search term.
			if ( 'input' === event.type && event.isComposing ) {
				return;
			}

			const control = event.target;
			const isSearch =
				'input' === event.type || 'compositionend' === event.type;

			// Bound straight away, so the typed text survives the region
			// re-render that the navigation is about to cause.
			if ( isSearch ) {
				getContext().searchValue = control.value;
			}

			// The browser's own form-owner resolution: honours the `form`
			// attribute, and is null when it names nothing or a non-form.
			const form = control.form;
			if ( ! form ) {
				return;
			}

			// Built now, from the DOM as it is at the event, not as it will
			// be when the timer fires.
			const url = buildUrl( window.location.href, formOptions( form ) );

			// Cancel first, and unconditionally: this URL describes the whole
			// form as it now stands, so any pending one is already stale. A
			// user who picks a category and puts it back within the window
			// must end up going nowhere, not at the category they abandoned.
			cancel( form.id );

			if ( sameQuery( url, inFlightUrl ?? window.location.href ) ) {
				return;
			}

			// A request is already out: go now and let the router discard the
			// stale response, rather than leaving the newer choice waiting.
			if ( inFlightUrl ) {
				run( url, isSearch, form.id );
				return;
			}

			timers.set(
				form.id,
				setTimeout(
					() => {
						timers.delete( form.id );
						run( url, isSearch, form.id );
					},
					isSearch ? SEARCH_DELAY : FILTER_DELAY
				)
			);
		},

		// Submitted by an Apply button, by Enter in the search box, or by a
		// keyboard user activating a control. preventDefault() needs the
		// synchronous event (roadmap #21).
		submit: withSyncEvent( ( event ) => {
			event.preventDefault();

			const form = event.target;
			cancel( form.id );
			searchBurst = false;

			run(
				buildUrl( window.location.href, formOptions( form ) ),
				false,
				form.id
			);
		} ),

		/**
		 * End a typing burst, so the next search pushes a history entry.
		 */
		endBurst() {
			searchBurst = false;
		},

		/**
		 * Navigate, keeping script-injected styles alive across the swap, and
		 * announce the new result count (spec C/D §3.3).
		 *
		 * @param {string}  url     URL to navigate to.
		 * @param {boolean} replace Whether to replace the history entry.
		 * @param {string}  formId  Id of the loop form that triggered it.
		 */
		*navigate( url, replace, formId ) {
			captureInjectedStyles();
			inFlightUrl = url;

			const loading = setTimeout( () => {
				if ( inFlightUrl === url ) {
					announce( routerLoadingText() );
				}
			}, LOADING_DELAY );

			try {
				const { actions } = yield import(
					'@wordpress/interactivity-router'
				);
				yield actions.navigate( url, {
					...( replace ? { replace: true } : {} ),
					screenReaderAnnouncement: false,
				} );
				enableInjectedStyles();

				// The router returns without rendering when a newer navigation
				// has taken over, so only the current one may announce. The
				// form is looked up again: the swap replaced it.
				if ( inFlightUrl === url ) {
					announce(
						document.getElementById( formId )?.dataset
							.queryResultsMessage
					);
				}
			} finally {
				clearTimeout( loading );

				// Only if no newer navigation has taken over.
				if ( inFlightUrl === url ) {
					inFlightUrl = null;
				}
			}
		},
	},
} );
