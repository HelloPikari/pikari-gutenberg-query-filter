/**
 * Mock for @wordpress/interactivity-router.
 *
 * The query-filter plugin uses dynamic import():
 *   const { actions } = yield import( '@wordpress/interactivity-router' );
 *   yield actions.navigate( url );
 *
 * This mock provides navigate() as a jest.fn() for assertion.
 */

const actions = {
	navigate: jest.fn( () => Promise.resolve() ),
};

module.exports = { actions };
