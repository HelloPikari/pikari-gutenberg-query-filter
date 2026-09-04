/**
 * Mock for @wordpress/i18n.
 *
 * Returns the input string as-is (passthrough) for testing purposes.
 */

const __ = jest.fn( ( text ) => text );
const _x = jest.fn( ( text ) => text );
const _n = jest.fn( ( single, plural, number ) => ( number === 1 ? single : plural ) );
const _nx = jest.fn( ( single, plural, number ) => ( number === 1 ? single : plural ) );
const sprintf = jest.fn( ( format, ...args ) => {
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
