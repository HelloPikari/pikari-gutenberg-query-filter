import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'pikari/gutenberg-query-filter', {
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
			const { actions } = yield import( '@wordpress/interactivity-router' );
			yield actions.navigate( url.toString() );
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
			const { actions } = yield import( '@wordpress/interactivity-router' );
			yield actions.navigate( url.toString() );
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
			const { actions } = yield import( '@wordpress/interactivity-router' );
			yield actions.navigate( url.toString() );
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
			const { actions } = yield import( '@wordpress/interactivity-router' );
			yield actions.navigate( url.toString() );
		},
	},
} );
