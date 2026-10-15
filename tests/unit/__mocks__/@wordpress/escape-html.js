/**
 * Mock for `@wordpress/escape-html`.
 *
 * Provides functional implementations (not just pass-through) so tests
 * can verify that escaped output is correct.
 */

const escapeHTML = vi.fn( ( text ) =>
	String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
);

const escapeAttribute = vi.fn( ( text ) =>
	String( text ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' )
);

module.exports = { escapeHTML, escapeAttribute };
