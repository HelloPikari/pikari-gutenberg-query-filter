import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import './style.scss';

registerBlockType( 'pikari-gutenberg-query-filter/sort', {
	edit: Edit,
} );
