/**
 * Mock for `@wordpress/i18n`.
 *
 * Returns the input string as-is (passthrough) for testing purposes.
 */

const __ = vi.fn( ( text ) => text );
const _x = vi.fn( ( text ) => text );
const _n = vi.fn( ( single, plural, number ) => ( number === 1 ? single : plural ) );
const _nx = vi.fn( ( single, plural, number ) => ( number === 1 ? single : plural ) );
const sprintf = vi.fn( ( format, ...args ) => {
	let i = 0;
	return format.replace( /%s/g, () => args[ i++ ] );
} );

module.exports = {
	__,
	_x,
	_n,
	_nx,
	sprintf,
};
