/**
 * Sanitize a string the way WordPress core's sanitize_html_class() does.
 *
 * @param {string} value Raw value.
 * @return {string} Value with percent-encoded octets and invalid characters removed.
 */
const sanitizeHtmlClass = ( value ) =>
	String( value ?? '' )
		.replace( /%[a-fA-F0-9]{2}/g, '' )
		.replace( /[^A-Za-z0-9_-]/g, '' );

/**
 * Build the unique class for a radio or checkbox filter option.
 *
 * Mirrors FilterHelper::get_option_classes() so theme CSS applies in the
 * editor preview as well as the frontend.
 *
 * @param {string} key  Taxonomy name, or `post-type` / `author`.
 * @param {string} slug Term slug, post type name, or user nicename.
 * @return {string} `{key}_{slug}`, or an empty string when the slug has no valid characters.
 */
export default function getOptionClassName( key, slug ) {
	const segment = sanitizeHtmlClass( slug );

	if ( ! segment ) {
		return '';
	}

	return `${ sanitizeHtmlClass( key ) }_${ segment }`;
}
