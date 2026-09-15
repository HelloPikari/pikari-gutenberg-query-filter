import { store, getContext, getElement } from '@wordpress/interactivity';

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

function* navigate( url ) {
	captureInjectedStyles();

	const { actions } = yield import( '@wordpress/interactivity-router' );
	yield actions.navigate( url );

	enableInjectedStyles();
}

store( 'pikari/gutenberg-query-filter', {
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
		*updateFilters( event ) {
			event.preventDefault();
			const { ref } = getElement();
			const context = getContext();
			const { queryVar, pageVar } = context;

			// Get all checkboxes in this filter block
			const filterBlock = ref.closest( '[data-wp-interactive="pikari/gutenberg-query-filter"]' );
			const checkboxes = filterBlock.querySelectorAll( 'input[type="checkbox"]:checked' );
			const values = Array.from( checkboxes ).map( ( cb ) => cb.value );

			// Build new URL with all current parameters
			const url = new URL( window.location );

			// Remove page parameter when filters change
			url.searchParams.delete( pageVar );
			if ( pageVar !== 'page' ) {
				url.searchParams.delete( 'page' );
			}

			// Update this filter's parameter
			if ( values.length > 0 ) {
				url.searchParams.set( queryVar, values.join( ',' ) );
			} else {
				url.searchParams.delete( queryVar );
			}

			// Navigate to new URL
			yield* navigate( url.toString() );
		},

		*handleSelect( event ) {
			event.preventDefault();
			const context = getContext();
			const { queryVar, pageVar } = context;
			const value = event.target.value;

			// Build new URL with all current parameters
			const url = new URL( window.location );

			// Remove page parameter when filters change
			url.searchParams.delete( pageVar );
			if ( pageVar !== 'page' ) {
				url.searchParams.delete( 'page' );
			}

			// Update this filter's parameter
			if ( value ) {
				url.searchParams.set( queryVar, value );
			} else {
				url.searchParams.delete( queryVar );
			}

			// Navigate to new URL
			yield* navigate( url.toString() );
		},

		*handleSort( event ) {
			event.preventDefault();
			const context = getContext();
			const { orderbyVar, orderVar, pageVar } = context;
			const value = event.target.value;

			// Build new URL with all current parameters
			const url = new URL( window.location );

			// Remove page parameter when sort changes
			url.searchParams.delete( pageVar );
			if ( pageVar !== 'page' ) {
				url.searchParams.delete( 'page' );
			}

			// Handle sort parameters
			if ( value ) {
				// Parse the value like 'date-desc' into orderby and order
				const [ orderby, order ] = value.split( '-' );
				url.searchParams.set( orderbyVar, orderby );
				url.searchParams.set( orderVar, order );
			} else {
				// Remove both sort parameters when empty
				url.searchParams.delete( orderbyVar );
				url.searchParams.delete( orderVar );
			}

			// Navigate to new URL
			yield* navigate( url.toString() );
		},

		*search( event ) {
			event.preventDefault();
			const { ref } = getElement();
			const context = getContext();
			let name, value;

			// Handle both form submission and input changes
			if ( ref.tagName === 'FORM' ) {
				const input = ref.querySelector( 'input[type="search"]' );
				name = input.name;
				value = input.value;
			} else {
				name = ref.name;
				value = ref.value;
			}

			// Don't navigate if the search didn't really change
			if ( value === context.searchValue ) {
				return;
			}

			// Update context
			context.searchValue = value;

			// Build new URL with search parameter
			const url = new URL( window.location );
			const pageVar = context.pageVar || 'page';

			// Remove page parameter when search changes
			url.searchParams.delete( pageVar );
			if ( pageVar !== 'page' ) {
				url.searchParams.delete( 'page' );
			}

			// Update search parameter
			if ( value.trim() ) {
				url.searchParams.set( name, value.trim() );
			} else {
				url.searchParams.delete( name );
			}

			// Navigate to new URL
			yield* navigate( url.toString() );
		},
	},
} );
