/* eslint-disable jsx-a11y/label-has-associated-control */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import FilterInspectorControls from '../../components/FilterInspectorControls';

export default function Edit( { attributes, setAttributes } ) {
	const { emptyLabel, label, showLabel } = attributes;

	const id = `sort-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;

	const sortOptions = [
		{
			label: __( 'Date (Newest First)', 'pikari-gutenberg-query-filter' ),
			value: 'date-desc',
		},
		{
			label: __( 'Date (Oldest First)', 'pikari-gutenberg-query-filter' ),
			value: 'date-asc',
		},
		{
			label: __( 'Title (A-Z)', 'pikari-gutenberg-query-filter' ),
			value: 'title-asc',
		},
		{
			label: __( 'Title (Z-A)', 'pikari-gutenberg-query-filter' ),
			value: 'title-desc',
		},
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Sort Settings', 'pikari-gutenberg-query-filter' ) }>
					<FilterInspectorControls
						attributes={ attributes }
						setAttributes={ setAttributes }
						defaultLabel={ __( 'Sort By', 'pikari-gutenberg-query-filter' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps( { className: 'wp-block-pikari-gutenberg-query-filter' } ) }>
				{ showLabel && (
					<label htmlFor={ id } className="wp-block-pikari-gutenberg-query-filter-sort__label wp-block-pikari-gutenberg-query-filter__label">
						{ label || __( 'Sort By', 'pikari-gutenberg-query-filter' ) }
					</label>
				) }
				<select
					id={ id }
					className="wp-block-pikari-gutenberg-query-filter-sort__select wp-block-pikari-gutenberg-query-filter__select"
					inert
				>
					<option>
						{ emptyLabel || __( 'Date', 'pikari-gutenberg-query-filter' ) }
					</option>
					{ sortOptions.map( ( option ) => (
						<option key={ option.value }>{ option.label }</option>
					) ) }
				</select>
			</div>
		</>
	);
}
