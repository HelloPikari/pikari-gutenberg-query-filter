/* eslint-disable jsx-a11y/label-has-associated-control */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

export default function Edit( { attributes, setAttributes, context } ) {
	const { emptyLabel, label, showLabel, displayType, layoutDirection } =
		attributes;

	const id = `post-type-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;

	const allPostTypes = useSelect( ( select ) => {
		return (
			( select( 'core' ).getPostTypes( { per_page: 100 } ) || [] ).filter(
				( type ) => type.viewable
			) || []
		);
	}, [] );

	let contextPostTypes = ( context.query.postType || '' )
		.split( ',' )
		.map( ( type ) => type.trim() );

	// Support for enhanced query loop block plugin.
	if ( Array.isArray( context.query.multiple_posts ) ) {
		contextPostTypes = contextPostTypes.concat(
			context.query.multiple_posts
		);
	}

	const postTypes = contextPostTypes.map( ( postType ) => {
		return (
			allPostTypes.find( ( type ) => type.slug === postType ) || {
				slug: postType,
				name: postType,
			}
		);
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Post Type Settings', 'pikari-gutenberg-query-filter' ) }>
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
						defaultValue={ __( 'Content Type', 'pikari-gutenberg-query-filter' ) }
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
					<label htmlFor={ id } className="wp-block-pikari-gutenberg-query-filter-post-type__label wp-block-pikari-gutenberg-query-filter__label">
						{ label || __( 'Content Type', 'pikari-gutenberg-query-filter' ) }
					</label>
				) }
				{ displayType === 'select' && (
					<select
						id={ id }
						className="wp-block-pikari-gutenberg-query-filter-post-type__select wp-block-pikari-gutenberg-query-filter__select"
						inert
					>
						<option>
							{ emptyLabel || __( 'All', 'pikari-gutenberg-query-filter' ) }
						</option>
						{ postTypes.map( ( type ) => (
							<option key={ type.slug }>{ type.name }</option>
						) ) }
					</select>
				) }
				{ displayType === 'radio' && (
					<div
						className={ `wp-block-pikari-gutenberg-query-filter-post-type__radio-group wp-block-pikari-gutenberg-query-filter__radio-group${
							layoutDirection === 'horizontal'
								? ' horizontal'
								: ''
						}` }
					>
						<label>
							<input
								type="radio"
								name="post-type-preview"
								defaultChecked
								inert
							/>
							{ emptyLabel || __( 'All', 'pikari-gutenberg-query-filter' ) }
						</label>
						{ postTypes.map( ( type ) => (
							<label key={ type.slug }>
								<input
									type="radio"
									name="post-type-preview"
									inert
								/>
								{ type.name }
							</label>
						) ) }
					</div>
				) }
				{ displayType === 'checkbox' && (
					<div
						className={ `wp-block-pikari-gutenberg-query-filter-post-type__checkbox-group wp-block-pikari-gutenberg-query-filter__checkbox-group${
							layoutDirection === 'horizontal'
								? ' horizontal'
								: ''
						}` }
					>
						{ postTypes.map( ( type ) => (
							<label key={ type.slug }>
								<input type="checkbox" inert />
								{ type.name }
							</label>
						) ) }
					</div>
				) }
			</div>
		</>
	);
}
