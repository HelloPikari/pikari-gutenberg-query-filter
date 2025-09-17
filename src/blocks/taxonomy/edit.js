/* eslint-disable jsx-a11y/label-has-associated-control */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

export default function Edit( { attributes, setAttributes } ) {
	const {
		taxonomy,
		emptyLabel,
		label,
		showLabel,
		displayType,
		layoutDirection,
	} = attributes;

	const id = `taxonomy-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;

	const taxonomies = useSelect(
		( select ) => {
			const results = (
				select( 'core' ).getTaxonomies( { per_page: 100 } ) || []
			).filter( ( tax ) => tax.visibility.publicly_queryable );

			if ( results && results.length > 0 && ! taxonomy ) {
				setAttributes( {
					taxonomy: results[ 0 ].slug,
					label: results[ 0 ].name,
				} );
			}

			return results;
		},
		[ taxonomy, setAttributes ]
	);

	const terms = useSelect(
		( select ) => {
			return (
				select( 'core' ).getEntityRecords( 'taxonomy', taxonomy, {
					number: 50,
				} ) || []
			);
		},
		[ taxonomy ]
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Taxonomy Settings', 'pikari-gutenberg-query-filter' ) }>
					<SelectControl
						label={ __( 'Select Taxonomy', 'pikari-gutenberg-query-filter' ) }
						value={ taxonomy }
						options={ ( taxonomies || [] ).map( ( tax ) => ( {
							label: tax.name,
							value: tax.slug,
						} ) ) }
						onChange={ ( newTaxonomy ) =>
							setAttributes( {
								taxonomy: newTaxonomy,
								label: taxonomies.find(
									( tax ) => tax.slug === newTaxonomy
								).name,
							} )
						}
					/>
					<SelectControl
						label={ __( 'Display Type', 'pikari-gutenberg-query-filter' ) }
						value={ displayType }
						options={ [
							{
								label: __(
									'Select (Dropdown)',
									'pikari-gutenberg-query-filter'
								),
								value: 'select',
							},
							{
								label: __(
									'Radio (Single Choice)',
									'pikari-gutenberg-query-filter'
								),
								value: 'radio',
							},
							{
								label: __(
									'Checkbox (Multiple Choice)',
									'pikari-gutenberg-query-filter'
								),
								value: 'checkbox',
							},
						] }
						onChange={ ( newDisplayType ) =>
							setAttributes( { displayType: newDisplayType } )
						}
					/>
					{ ( displayType === 'radio' ||
						displayType === 'checkbox' ) && (
						<ToggleGroupControl
							label={ __( 'Layout Direction', 'pikari-gutenberg-query-filter' ) }
							value={ layoutDirection }
							onChange={ ( newLayoutDirection ) =>
								setAttributes( { layoutDirection: newLayoutDirection } )
							}
							isBlock
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						>
							<ToggleGroupControlOption
								value="vertical"
								label={ __( 'Vertical', 'pikari-gutenberg-query-filter' ) }
							/>
							<ToggleGroupControlOption
								value="horizontal"
								label={ __( 'Horizontal', 'pikari-gutenberg-query-filter' ) }
							/>
						</ToggleGroupControl>
					) }
					<TextControl
						label={ __( 'Label', 'pikari-gutenberg-query-filter' ) }
						value={ label }
						help={ __(
							'If empty then no label will be shown',
							'pikari-gutenberg-query-filter'
						) }
						onChange={ ( newLabel ) => setAttributes( { label: newLabel } ) }
					/>
					<ToggleControl
						label={ __( 'Show Label', 'pikari-gutenberg-query-filter' ) }
						checked={ showLabel }
						onChange={ ( newShowLabel ) =>
							setAttributes( { showLabel: newShowLabel } )
						}
					/>
					<TextControl
						label={ __( 'Empty Choice Label', 'pikari-gutenberg-query-filter' ) }
						value={ emptyLabel }
						placeholder={ __( 'All', 'pikari-gutenberg-query-filter' ) }
						onChange={ ( newEmptyLabel ) =>
							setAttributes( { emptyLabel: newEmptyLabel } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps( { className: 'wp-block-pikari-gutenberg-query-filter' } ) }>
				{ showLabel && (
					<label htmlFor={ id } className="wp-block-pikari-gutenberg-query-filter-taxonomy__label wp-block-pikari-gutenberg-query-filter__label">
						{ label }
					</label>
				) }
				{ displayType === 'select' && (
					<select
						id={ id }
						className="wp-block-pikari-gutenberg-query-filter-taxonomy__select wp-block-pikari-gutenberg-query-filter__select"
						inert
					>
						<option>
							{ emptyLabel || __( 'All', 'pikari-gutenberg-query-filter' ) }
						</option>
						{ terms.map( ( term ) => (
							<option key={ term.slug }>{ term.name }</option>
						) ) }
					</select>
				) }
				{ displayType === 'radio' && (
					<div
						className={ `wp-block-pikari-gutenberg-query-filter-taxonomy__radio-group wp-block-pikari-gutenberg-query-filter__radio-group${
							layoutDirection === 'horizontal'
								? ' horizontal'
								: ''
						}` }
					>
						<label>
							<input
								type="radio"
								name="taxonomy-preview"
								defaultChecked
								inert
							/>
							{ emptyLabel || __( 'All', 'pikari-gutenberg-query-filter' ) }
						</label>
						{ terms.map( ( term ) => (
								<label key={ term.slug }>
								<input
									type="radio"
									name="taxonomy-preview"
									inert
								/>
								{ term.name }
							</label>
						) ) }
					</div>
				) }
				{ displayType === 'checkbox' && (
					<div
						className={ `wp-block-pikari-gutenberg-query-filter-taxonomy__checkbox-group wp-block-pikari-gutenberg-query-filter__checkbox-group${
							layoutDirection === 'horizontal'
								? ' horizontal'
								: ''
						}` }
					>
						{ terms.map( ( term ) => (
								<label key={ term.slug }>
								<input type="checkbox" inert />
								{ term.name }
							</label>
						) ) }
					</div>
				) }
			</div>
		</>
	);
}
