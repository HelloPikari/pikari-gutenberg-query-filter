import fs from 'fs';
import path from 'path';

const blocksDir = path.resolve( __dirname, '../../../src/blocks' );
const blockNames = fs
	.readdirSync( blocksDir, { withFileTypes: true } )
	.filter( ( entry ) => entry.isDirectory() )
	.map( ( entry ) => entry.name );

const readBlockFile = ( block, file ) =>
	fs.readFileSync( path.join( blocksDir, block, file ), 'utf8' );

const readMetadata = ( block ) =>
	JSON.parse( readBlockFile( block, 'block.json' ) );

/**
 * The module ID WordPress generates for a block's `file:` viewScriptModule,
 * as generate_block_asset_handle() builds it.
 *
 * @param {string} blockName Block name, e.g. `namespace/block`.
 * @return {string} Script module ID.
 */
const viewScriptModuleId = ( blockName ) =>
	`${ blockName.replace( '/', '-' ) }-view-script-module`;

/**
 * Whether the block's index.js imports a stylesheet webpack emits as the given file.
 *
 * @param {string} block  Block directory name.
 * @param {string} output `style-index.css` or `index.css`.
 * @return {boolean} True when an import produces the file.
 */
const importsStylesheetFor = ( block, output ) => {
	const imports = [
		...readBlockFile( block, 'index.js' ).matchAll(
			/import\s+'\.\/([^']+\.scss)'/g
		),
	].map( ( match ) => match[ 1 ] );

	return output === 'style-index.css'
		? imports.some( ( file ) => file.startsWith( 'style' ) )
		: imports.some( ( file ) => ! file.startsWith( 'style' ) );
};

describe( 'block metadata', () => {
	describe.each( blockNames )( '%s block.json', ( block ) => {
		const metadata = readMetadata( block );
		const assetFields = [
			'editorScript',
			'script',
			'viewScript',
			'viewScriptModule',
			'editorStyle',
			'style',
			'viewStyle',
		];

		it.each( assetFields )(
			'should point %s at a file the build emits',
			( field ) => {
				const assets = [ metadata[ field ] ]
					.flat()
					.filter(
						( value ) =>
							typeof value === 'string' &&
							value.startsWith( 'file:' )
					)
					.map( ( value ) => value.replace( 'file:./', '' ) );

				assets.forEach( ( asset ) => {
					// block.json is copied to build/ as is, where only compiled CSS exists.
					if ( field.endsWith( 'Style' ) || field === 'style' ) {
						expect( asset ).toMatch( /\.css$/ );
					}

					if ( asset.endsWith( '.css' ) ) {
						expect( importsStylesheetFor( block, asset ) ).toBe(
							true
						);
					} else {
						expect(
							fs.existsSync( path.join( blocksDir, block, asset ) )
						).toBe( true );
					}
				} );
			}
		);

		it( 'should not hardcode a version, which the plugin sets at registration', () => {
			expect( metadata ).not.toHaveProperty( 'version' );
		} );
	} );

	it( 'should load the shared view module from the Sort block', () => {
		const queryFilter = readMetadata( 'query-filter' );

		expect( readMetadata( 'sort' ).viewScriptModule ).toBe(
			viewScriptModuleId( queryFilter.name )
		);
	} );

	it.each( blockNames )(
		'should not opt the site into view transitions from the %s stylesheet',
		( block ) => {
			const stylesheets = fs
				.readdirSync( path.join( blocksDir, block ) )
				.filter( ( file ) => file.endsWith( '.scss' ) );

			stylesheets.forEach( ( file ) => {
				expect( readBlockFile( block, file ) ).not.toContain(
					'@view-transition'
				);
			} );
		}
	);
} );
