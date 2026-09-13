/* eslint-disable jsx-a11y/label-has-associated-control */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import classNames from 'classnames';
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
import FilterInput from '../../components/FilterInput';
import getOptionClassName from '../../utils/option-class-name';

export default function Edit( { attributes, setAttributes, context } ) {
	const {
		filterType,
		taxonomy,
		emptyLabel,
		label,
		showLabel,
		displayType,
		layoutDirection,
	} = attributes;

	const id = `query-filter-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;

	// Get data for post-type filter
	const allPostTypes = useSelect( ( select ) => {
		if ( filterType !== 'post-type' ) {
			return [];
		}
		return (
			( select( 'core' ).getPostTypes( { per_page: 100 } ) || [] ).filter(
				( type ) => type.viewable
			) || []
		);
	}, [ filterType ] );

	// Get data for taxonomy filter
	const taxonomies = useSelect(
		( select ) => {
			if ( filterType !== 'taxonomy' ) {
				return [];
			}
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
		[ filterType, taxonomy, setAttributes ]
	);

	const terms = useSelect(
		( select ) => {
			if ( filterType !== 'taxonomy' || ! taxonomy ) {
				return [];
			}
			return (
				select( 'core' ).getEntityRecords( 'taxonomy', taxonomy, {
					number: 50,
				} ) || []
			);
		},
		[ filterType, taxonomy ]
	);

	// Get data for author filter
	const authors = useSelect( ( select ) => {
		if ( filterType !== 'author' ) {
			return [];
		}
		return (
			select( 'core' ).getUsers( {
				who: 'authors',
				per_page: 100,
			} ) || []
		);
	}, [ filterType ] );

	// Process post types for display
	let contextPostTypes = [];
	if ( filterType === 'post-type' && context.query ) {
		contextPostTypes = ( context.query.postType || '' )
			.split( ',' )
			.map( ( type ) => type.trim() );

		// Support for enhanced query loop block plugin
		if ( Array.isArray( context.query.multiple_posts ) ) {
			contextPostTypes = contextPostTypes.concat(
				context.query.multiple_posts
			);
		}
	}

	const postTypes = contextPostTypes.map( ( postType ) => {
		return (
			allPostTypes.find( ( type ) => type.slug === postType ) || {
				slug: postType,
				name: postType,
			}
		);
	} );

	// Get default label based on filter type
	const getDefaultLabel = () => {
		switch ( filterType ) {
			case 'post-type':
				return __( 'Content Type', 'pikari-gutenberg-query-filter' );
			case 'taxonomy':
				return taxonomy && taxonomies.length > 0
					? taxonomies.find( ( tax ) => tax.slug === taxonomy )?.name || __( 'Filter by', 'pikari-gutenberg-query-filter' )
					: __( 'Filter by', 'pikari-gutenberg-query-filter' );
			case 'author':
				return __( 'Author', 'pikari-gutenberg-query-filter' );
			default:
				return __( 'Filter', 'pikari-gutenberg-query-filter' );
		}
	};

	// Normalize preview items, like FilterHelper::get_filter_options().
	const previewOptions =
		{
			'post-type': postTypes.map( ( postType ) => ( {
				key: postType.slug,
				value: postType.slug,
				label: postType.name,
				slug: postType.slug,
			} ) ),
			taxonomy: terms.map( ( term ) => ( {
				key: term.id,
				value: term.slug,
				label: term.name,
				slug: term.slug,
			} ) ),
			author: authors.map( ( author ) => ( {
				key: author.id,
				value: author.id,
				label: author.name,
				slug: author.slug,
			} ) ),
		}[ filterType ] || [];

	const blockProps = useBlockProps( {
		className: classNames( 'wp-block-pikari-gutenberg-query-filter', {
			'has-layout-horizontal': layoutDirection === 'horizontal',
		} ),
	} );

	const labelClassName = classNames(
		'wp-block-pikari-gutenberg-query-filter__label',
		{
			'screen-reader-text': ! showLabel,
		}
	);
	const labelText = label || getDefaultLabel();

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Filter Settings',
						'pikari-gutenberg-query-filter'
					) }
				>
					{ filterType === 'taxonomy' && (
						<SelectControl
							label={ __(
								'Taxonomy',
								'pikari-gutenberg-query-filter'
							) }
							value={ taxonomy || '' }
							options={ [
								{
									label: __(
										'Select a taxonomy',
										'pikari-gutenberg-query-filter'
									),
									value: '',
								},
								...taxonomies.map( ( tax ) => ( {
									label: tax.name,
									value: tax.slug,
								} ) ),
							] }
							onChange={ ( newTaxonomy ) =>
								setAttributes( { taxonomy: newTaxonomy } )
							}
						/>
					) }
					<SelectControl
						label={ __(
							'Display Type',
							'pikari-gutenberg-query-filter'
						) }
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
							label={ __(
								'Layout Direction',
								'pikari-gutenberg-query-filter'
							) }
							value={ layoutDirection }
							onChange={ ( newLayoutDirection ) =>
								setAttributes( {
									layoutDirection: newLayoutDirection,
								} )
							}
							isBlock
							__nextHasNoMarginBottom
						>
							<ToggleGroupControlOption
								value="vertical"
								label={ __(
									'Vertical',
									'pikari-gutenberg-query-filter'
								) }
							/>
							<ToggleGroupControlOption
								value="horizontal"
								label={ __(
									'Horizontal',
									'pikari-gutenberg-query-filter'
								) }
							/>
						</ToggleGroupControl>
					) }
					<TextControl
						label={ __( 'Label', 'pikari-gutenberg-query-filter' ) }
						value={ label }
						placeholder={ getDefaultLabel() }
						help={ __(
							'If empty then no label will be shown',
							'pikari-gutenberg-query-filter'
						) }
						onChange={ ( newLabel ) =>
							setAttributes( { label: newLabel } )
						}
					/>
					<ToggleControl
						label={ __(
							'Show Label',
							'pikari-gutenberg-query-filter'
						) }
						checked={ showLabel }
						onChange={ ( newShowLabel ) =>
							setAttributes( { showLabel: newShowLabel } )
						}
					/>
					<TextControl
						label={ __(
							'Empty Choice Label',
							'pikari-gutenberg-query-filter'
						) }
						value={ emptyLabel }
						placeholder={ __(
							'All',
							'pikari-gutenberg-query-filter'
						) }
						onChange={ ( newEmptyLabel ) =>
							setAttributes( { emptyLabel: newEmptyLabel } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ displayType === 'select' && (
					<>
						<label className={ labelClassName } htmlFor={ id }>
							{ labelText }
						</label>
						<select
							className="wp-block-pikari-gutenberg-query-filter__select"
							id={ id }
							disabled
						>
							<option value="">
								{ emptyLabel ||
									__( 'All', 'pikari-gutenberg-query-filter' ) }
							</option>
							{ previewOptions.map( ( option ) => (
								<option key={ option.key } value={ option.value }>
									{ option.label }
								</option>
							) ) }
						</select>
					</>
				) }

				{ ( displayType === 'radio' || displayType === 'checkbox' ) && (
					<fieldset className="wp-block-pikari-gutenberg-query-filter__fieldset">
						<legend className={ labelClassName }>{ labelText }</legend>
						<div
							className={ classNames(
								`wp-block-pikari-gutenberg-query-filter__${ displayType }-group`,
								{
									'has-layout-horizontal':
										layoutDirection === 'horizontal',
								}
							) }
						>
							{ displayType === 'radio' && (
								<FilterInput
									type="radio"
									disabled
									checked
									className={ getOptionClassName(
										attributes,
										'all'
									) }
								>
									{ emptyLabel ||
										__( 'All', 'pikari-gutenberg-query-filter' ) }
								</FilterInput>
							) }
							{ previewOptions.slice( 0, 3 ).map( ( option ) => (
								<FilterInput
									key={ option.key }
									type={ displayType }
									disabled
									className={ getOptionClassName(
										attributes,
										option.slug
									) }
								>
									{ option.label }
								</FilterInput>
							) ) }
						</div>
					</fieldset>
				) }
			</div>
		</>
	);
}
