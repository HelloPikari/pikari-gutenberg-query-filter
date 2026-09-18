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

/**
 * Remove a trailing pagination path segment from a URL's pathname.
 *
 * An inherited loop paginates through a `/{base}/{n}/` path segment on
 * pretty permalinks, not only the `paged` query var (spec §3.3). Filtering
 * without stripping it can leave a request pointed at a page number the
 * filtered query no longer has, which core 404s (spec §4.4). Only a
 * trailing segment is stripped, so a path that merely contains the base
 * word elsewhere (`/page-two/`) or has it followed by more path
 * (`/blog/page/2/extra/`) is left untouched.
 *
 * @param {string} pathname       URL pathname.
 * @param {string} paginationBase Rewrite pagination base, e.g. "page".
 * @return {string} Pathname with a trailing pagination segment removed.
 */
export const stripInheritedPagination = ( pathname, paginationBase ) => {
	const base = paginationBase.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );

	return pathname.replace( new RegExp( `/${ base }/\\d+/?$` ), '/' );
};

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
			const { queryVar, pageVar, paginationBase } = context;

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

			// An inherited loop's page number can also live in the path.
			if ( pageVar === 'paged' ) {
				url.pathname = stripInheritedPagination(
					url.pathname,
					paginationBase || 'page'
				);
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
			const { queryVar, pageVar, paginationBase } = context;
			const value = event.target.value;

			// Build new URL with all current parameters
			const url = new URL( window.location );

			// Remove page parameter when filters change
			url.searchParams.delete( pageVar );
			if ( pageVar !== 'page' ) {
				url.searchParams.delete( 'page' );
			}

			// An inherited loop's page number can also live in the path.
			if ( pageVar === 'paged' ) {
				url.pathname = stripInheritedPagination(
					url.pathname,
					paginationBase || 'page'
				);
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
			const { sortVar, pageVar, paginationBase } = context;
			const value = event.target.value;

			// Build new URL with all current parameters
			const url = new URL( window.location );

			// Remove page parameter when sort changes
			url.searchParams.delete( pageVar );
			if ( pageVar !== 'page' ) {
				url.searchParams.delete( 'page' );
			}

			// An inherited loop's page number can also live in the path.
			if ( pageVar === 'paged' ) {
				url.pathname = stripInheritedPagination(
					url.pathname,
					paginationBase || 'page'
				);
			}

			// Update the sort parameter
			if ( value ) {
				url.searchParams.set( sortVar, value );
			} else {
				url.searchParams.delete( sortVar );
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
