/**
 * Remove a trailing pagination path segment from a URL's pathname.
 *
 * An inherited loop paginates through a `/{base}/{n}/` path segment on
 * pretty permalinks, not only the `paged` query var (spec §3.3). Filtering
 * without stripping it can leave a request pointed at a page number the
 * filtered query no longer has, which core 404s (spec §4.4). Only a
 * trailing segment is stripped, so a path that merely contains the base
 * word elsewhere (`/page-two/`) or has it followed by more path
 * (`/blog/page/2/extra/`) is left untouched. The result keeps whatever
 * trailing-slash style the input had — a path with no trailing slash
 * (`/category/news/page/2`) resolves to `/category/news`, not
 * `/category/news/` — since forcing one on could trigger a canonical
 * redirect on a site whose permalinks omit it.
 *
 * PHP strips the same segment in `LoopForm::action()`, for the no-JavaScript
 * submit. The two must agree; `LoopFormTest` and `build-url.test.js` both
 * cover a root-level `/page/2`, which is where they last diverged.
 *
 * @param {string} pathname       URL pathname.
 * @param {string} paginationBase Rewrite pagination base, e.g. "page".
 * @return {string} Pathname with a trailing pagination segment removed.
 */
export const stripInheritedPagination = ( pathname, paginationBase ) => {
	const base = paginationBase.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );

	return pathname.replace( new RegExp( `/${ base }/\\d+(/?)$` ), '$1' );
};

// Names a filter change always clears, whichever way the form is submitted.
// PHP's LoopForm::reset_names() drops the same three from the hidden inputs;
// if the two lists disagreed, a no-JS submit would keep a page number that a
// JavaScript filter change resets.
const RESET_NAMES = [ 'page', 'cst' ];

/**
 * The bare name of a control, without a trailing `[]`.
 *
 * PHP applies the same rule in `LoopForm::bare_name()`, which both
 * `LoopForm::hidden_inputs()` and `BlockFilters::form_targets()` call.
 *
 * @param {string} name Control name.
 * @return {string} Bare name.
 */
export const bareName = ( name ) => name.replace( /\[\]$/, '' );

/**
 * Build the URL a filter change should navigate to.
 *
 * The form is the source of truth: every name it owns is rewritten from the
 * submitted entries, and every other parameter in the current URL is left
 * exactly as it is (spec §6.1).
 *
 * @param {string}   href                   URL the change happened on.
 * @param {Object}   options
 * @param {Array}    options.entries        FormData entries, as [ name, value ] pairs.
 * @param {string[]} options.ownedNames     Bare names the form owns.
 * @param {string}   options.pageKey        The loop's page parameter.
 * @param {boolean}  options.inherit        Whether the loop inherits the main query.
 * @param {string}   options.paginationBase The rewrite's pagination base.
 * @return {string} The URL to navigate to.
 */
export const buildUrl = (
	href,
	{ entries, ownedNames, pageKey, inherit, paginationBase }
) => {
	const url = new URL( href );
	url.hash = '';

	[ pageKey, ...RESET_NAMES ].forEach( ( name ) =>
		url.searchParams.delete( name )
	);
	ownedNames.forEach( ( name ) => url.searchParams.delete( name ) );

	if ( inherit ) {
		url.pathname = stripInheritedPagination(
			url.pathname,
			paginationBase || 'page'
		);
	}

	const owned = new Set( ownedNames );
	const grouped = new Map();

	entries.forEach( ( [ name, value ] ) => {
		const bare = bareName( name );
		const trimmed = typeof value === 'string' ? value.trim() : '';

		if ( ! owned.has( bare ) || trimmed === '' ) {
			return;
		}

		grouped.set( bare, [ ...( grouped.get( bare ) ?? [] ), trimmed ] );
	} );

	grouped.forEach( ( values, name ) =>
		url.searchParams.set( name, values.join( ',' ) )
	);

	return url.href;
};

/**
 * Whether two URLs describe the same query.
 *
 * `a,b`, `a%2Cb` and `key[]=a&key[]=b` all mean the same thing to this
 * plugin, so a change that produces any of them from any other is not worth
 * a navigation.
 *
 * @param {string} a First URL.
 * @param {string} b Second URL.
 * @return {boolean} True when both name the same path and parameters.
 */
export const sameQuery = ( a, b ) => {
	const first = new URL( a, window.location.href );
	const second = new URL( b, window.location.href );

	if ( first.pathname !== second.pathname ) {
		return false;
	}

	return normalize( first.searchParams ) === normalize( second.searchParams );
};

/**
 * A comparable form of a query string.
 *
 * @param {URLSearchParams} searchParams Parameters.
 * @return {string} Sorted, flattened parameters.
 */
const normalize = ( searchParams ) => {
	const flat = [];

	searchParams.forEach( ( value, name ) => {
		value
			.split( ',' )
			.forEach( ( part ) => flat.push( `${ bareName( name ) }=${ part }` ) );
	} );

	return flat.sort().join( '&' );
};
