/**
 * Jest test setup — runs before each test suite.
 *
 * Resets the jsdom environment between tests to prevent state leaking.
 */

beforeEach( () => {
	// Clear any DOM modifications from previous tests.
	document.body.innerHTML = '';
	document.head.innerHTML = '';

	// Reset body styles (modal tests modify overflow, etc.).
	document.body.removeAttribute( 'style' );
} );
