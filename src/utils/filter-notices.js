import { __ } from '@wordpress/i18n';

export const NOTICE = {
	DUPLICATE: 'duplicate',
	NO_TAXONOMY: 'no-taxonomy',
	NO_TERMS: 'no-terms',
	NO_AUTHORS: 'no-authors',
};

// Notices whose filter renders nothing at all on the frontend.
export const RENDERS_NOTHING = [
	NOTICE.NO_TAXONOMY,
	NOTICE.NO_TERMS,
	NOTICE.NO_AUTHORS,
];

/**
 * Whether two filters write the same URL parameter.
 *
 * @param {Object} a Block attributes.
 * @param {Object} b Block attributes.
 * @return {boolean} True when they share a parameter.
 */
const isSameFilter = ( a, b ) =>
	a.filterType === b.filterType &&
	( a.filterType !== 'taxonomy' ||
		( !! a.taxonomy && a.taxonomy === b.taxonomy ) );

/**
 * Whether another filter in this block's Query Loop writes the same URL
 * parameter. Two such filters behave as one control: radios merge into a
 * single group across both blocks (spec C/D §4.2).
 *
 * @param {Object}   selectors                            core/block-editor selectors.
 * @param {Function} selectors.getBlockName               Get a block's name.
 * @param {Function} selectors.getBlockAttributes         Get a block's attributes.
 * @param {Function} selectors.getBlockParentsByBlockName Get a block's ancestors matching a name.
 * @param {Function} selectors.getClientIdsOfDescendants  Get a block's descendant client IDs.
 * @param {string}   clientId                             This block's client ID.
 * @return {boolean} True when a duplicate exists.
 */
export function hasDuplicateFilter( selectors, clientId ) {
	const {
		getBlockName,
		getBlockAttributes,
		getBlockParentsByBlockName,
		getClientIdsOfDescendants,
	} = selectors;

	const nearestLoop = ( id ) =>
		getBlockParentsByBlockName( id, 'core/query', true )[ 0 ];

	const loop = nearestLoop( clientId );
	if ( ! loop ) {
		return false;
	}

	const name = getBlockName( clientId );
	const attributes = getBlockAttributes( clientId );

	return getClientIdsOfDescendants( loop ).some(
		( id ) =>
			id !== clientId &&
			getBlockName( id ) === name &&
			// A nested loop has its own parameters.
			nearestLoop( id ) === loop &&
			isSameFilter( attributes, getBlockAttributes( id ) )
	);
}

/**
 * The editor notices that apply to a filter, in display order.
 *
 * @param {Object}  attributes            Block attributes.
 * @param {string}  attributes.filterType `post-type`, `taxonomy`, or `author`.
 * @param {string}  attributes.taxonomy   Taxonomy name, for taxonomy filters.
 * @param {Object}  state                 What the editor knows.
 * @param {boolean} state.hasDuplicate    From hasDuplicateFilter().
 * @param {boolean} state.optionsResolved Whether the preview's options have loaded.
 * @param {number}  state.optionCount     How many options loaded.
 * @return {string[]} NOTICE values.
 */
export function getFilterNotices(
	{ filterType, taxonomy },
	{ hasDuplicate = false, optionsResolved = false, optionCount = 0 } = {}
) {
	const notices = [];

	if ( hasDuplicate ) {
		notices.push( NOTICE.DUPLICATE );
	}

	if ( filterType === 'taxonomy' && ! taxonomy ) {
		notices.push( NOTICE.NO_TAXONOMY );
	} else if ( optionsResolved && optionCount === 0 ) {
		if ( filterType === 'taxonomy' ) {
			notices.push( NOTICE.NO_TERMS );
		} else if ( filterType === 'author' ) {
			notices.push( NOTICE.NO_AUTHORS );
		}
	}

	return notices;
}

/**
 * The message for a notice.
 *
 * @param {string} key A NOTICE value.
 * @return {string} Translated message.
 */
export function getNoticeMessage( key ) {
	return {
		[ NOTICE.DUPLICATE ]: __(
			'Another filter in this Query Loop filters by the same thing. Both use one URL parameter, so they act as one control. Remove one of them.',
			'pikari-gutenberg-query-filter'
		),
		[ NOTICE.NO_TAXONOMY ]: __(
			'Choose a taxonomy. This filter displays nothing until you do.',
			'pikari-gutenberg-query-filter'
		),
		[ NOTICE.NO_TERMS ]: __(
			'This taxonomy has no terms with posts yet, so this filter displays nothing.',
			'pikari-gutenberg-query-filter'
		),
		[ NOTICE.NO_AUTHORS ]: __(
			'No authors have published posts yet, so this filter displays nothing.',
			'pikari-gutenberg-query-filter'
		),
	}[ key ];
}
