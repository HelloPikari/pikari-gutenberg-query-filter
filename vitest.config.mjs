/**
 * Vitest configuration for unit tests.
 *
 * Synced to all plugins via config-templates. Do not edit in plugin directories —
 * changes should be made in .github/config-templates/plugin/vitest.config.mjs and synced.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/#test-unit-js
 */

import { fileURLToPath } from 'node:url';
import { transformWithOxc } from 'vite';
import { defineConfig } from 'vitest/config';

const mock = ( name ) =>
	fileURLToPath(
		new URL( `./tests/unit/__mocks__/@wordpress/${ name }.js`, import.meta.url )
	);

// WordPress provides @wordpress/* packages at runtime (webpack externals), so
// most are not installed. Resolve any that are missing to an empty module so
// tests can vi.mock() them.
const wordpressExternals = {
	name: 'wordpress-externals',
	enforce: 'pre',
	async resolveId( id, importer ) {
		if ( ! id.startsWith( '@wordpress/' ) ) {
			return null;
		}
		const resolved = await this.resolve( id, importer, { skipSelf: true } );
		return resolved ?? `\0wordpress-external:${ id }`;
	},
	load( id ) {
		return id.startsWith( '\0wordpress-external:' ) ? 'export {};' : null;
	},
};

// Source and test files are .js but contain JSX, which webpack handles at
// build time. Vite only parses JSX in .jsx files, so transform them here.
const jsxInJs = {
	name: 'jsx-in-js',
	enforce: 'pre',
	transform( code, id ) {
		if ( id.includes( '/node_modules/' ) || ! /\/(src|tests)\/.*\.js$/.test( id ) ) {
			return null;
		}
		return transformWithOxc( code, id, {
			lang: 'jsx',
			jsx: { runtime: 'automatic' },
		} );
	},
};

export default defineConfig( {
	plugins: [ wordpressExternals, jsxInJs ],
	resolve: {
		// @wordpress/* externals not in node_modules
		alias: [
			{ find: /^@wordpress\/interactivity$/, replacement: mock( 'interactivity' ) },
			{
				find: /^@wordpress\/interactivity-router$/,
				replacement: mock( 'interactivity-router' ),
			},
			{ find: /^@wordpress\/escape-html$/, replacement: mock( 'escape-html' ) },
			{ find: /^@wordpress\/i18n$/, replacement: mock( 'i18n' ) },
		],
	},
	test: {
		environment: 'jsdom',
		// Jest's jsdom URL. Vitest defaults to http://localhost:3000.
		environmentOptions: { jsdom: { url: 'http://localhost/' } },
		globals: true,
		include: [ 'tests/unit/**/*.test.js' ],
		setupFiles: [ './tests/unit/setup.js' ],
		passWithNoTests: true,
		coverage: {
			include: [ 'src/**/*.js' ],
			exclude: [ 'src/**/index.js', 'src/**/*.test.js' ],
		},
	},
} );
