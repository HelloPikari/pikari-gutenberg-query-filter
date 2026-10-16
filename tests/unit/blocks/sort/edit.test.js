/**
 * The Sort block's editor preview.
 *
 * The preview <select> is inert so it can't steal focus or clicks from block
 * selection. React 18 drops a bare boolean `inert` with a "non-boolean
 * attribute" warning, and React 19 drops the old `inert=""` workaround, so the
 * attribute has to survive whichever React the editor runs. This test runs on
 * the React installed in node_modules and covers that version only.
 */

import { createElement } from '@wordpress/element';
import { act } from 'react';
import { createRoot } from 'react-dom/client';

import Edit from '../../../../src/blocks/sort/edit';

vi.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: ( props ) => props,
	InspectorControls: () => null,
} ) );

vi.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => children,
} ) );

vi.mock( '@wordpress/compose', () => ( {
	useInstanceId: () => 'sort-0',
} ) );

vi.mock( '../../../../src/components/FilterInspectorControls', () => ( {
	default: () => null,
} ) );

describe( 'sort Edit', () => {
	let container;
	let root;

	beforeEach( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
	} );

	it( 'renders the preview select with the inert attribute', () => {
		act( () => {
			root.render(
				createElement( Edit, {
					attributes: { showLabel: true },
					setAttributes: vi.fn(),
				} )
			);
		} );

		expect(
			container.querySelector( 'select' ).hasAttribute( 'inert' )
		).toBe( true );
	} );
} );
