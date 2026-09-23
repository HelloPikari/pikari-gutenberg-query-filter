/* eslint-disable jsx-a11y/label-has-associated-control */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	Warning,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import classNames from 'classnames';
import { Notice, PanelBody, SelectControl } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { useEntityRecords } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import FilterInput from '../../components/FilterInput';
import FilterInspectorControls from '../../components/FilterInspectorControls';
import getOptionClassName from '../../utils/option-class-name';
import {
	RENDERS_NOTHING,
	getFilterNotices,
	getNoticeMessage,
	hasDuplicateFilter,
} from '../../utils/filter-notices';
import {
	AUTHOR_PREVIEW_QUERY,
	TERM_PREVIEW_QUERY,
} from '../../utils/preview-queries';

export default function Edit( {
	attributes,
	setAttributes,
	context,
	clientId,
} ) {
	const {
		filterType,
		taxonomy,
		emptyLabel,
		label,
		showLabel,
		displayType,
		layoutDirection,
	} = attributes;

	const id = useInstanceId( Edit, 'query-filter' );

	// An inherited loop's post types come from the page's main query, which
	// the editor can't see; context.query.postType is only the default.
	const isInherited = !! context.query?.inherit;

	const allPostTypes = useSelect(
		( select ) => {
			if ( filterType !== 'post-type' ) {
				return [];
			}
			return (
				select( 'core' ).getPostTypes( { per_page: 100 } ) || []
			).filter( ( type ) => type.viewable );
		},
		[ filterType ]
	);

	const taxonomies = useSelect(
		( select ) => {
			if ( filterType !== 'taxonomy' ) {
				return [];
			}
			return (
				select( 'core' ).getTaxonomies( { per_page: 100 } ) || []
			).filter( ( tax ) => tax.visibility.publicly_queryable );
		},
		[ filterType ]
	);

	// A new taxonomy filter starts on the first taxonomy. Only the taxonomy
	// is stored; the label default is resolved at render.
	useEffect( () => {
		if ( filterType === 'taxonomy' && ! taxonomy && taxonomies.length ) {
			setAttributes( { taxonomy: taxonomies[ 0 ].slug } );
		}
	}, [ filterType, taxonomy, taxonomies, setAttributes ] );

	const { records: terms, status: termsStatus } = useEntityRecords(
		'taxonomy',
		taxonomy || '',
		TERM_PREVIEW_QUERY,
		{ enabled: filterType === 'taxonomy' && !! taxonomy }
	);

	const { records: authors, status: authorsStatus } = useEntityRecords(
		'root',
		'user',
		AUTHOR_PREVIEW_QUERY,
		{ enabled: filterType === 'author' }
	);

	const hasDuplicate = useSelect(
		( select ) => hasDuplicateFilter( select( blockEditorStore ), clientId ),
		[ clientId ]
	);

	let contextPostTypes = [];
	if ( filterType === 'post-type' && context.query && ! isInherited ) {
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

	const postTypes = contextPostTypes.map(
		( postType ) =>
			allPostTypes.find( ( type ) => type.slug === postType ) || {
				slug: postType,
				name: postType,
			}
	);

	const getDefaultLabel = () => {
		switch ( filterType ) {
			case 'post-type':
				return __( 'Content Type', 'pikari-gutenberg-query-filter' );
			case 'taxonomy':
				return (
					taxonomies.find( ( tax ) => tax.slug === taxonomy )?.name ||
					__( 'Filter by', 'pikari-gutenberg-query-filter' )
				);
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
			taxonomy: ( terms || [] ).map( ( term ) => ( {
				key: term.id,
				value: term.slug,
				label: term.name,
				slug: term.slug,
			} ) ),
			author: ( authors || [] ).map( ( author ) => ( {
				key: author.id,
				value: author.slug,
				label: author.name,
				slug: author.slug,
			} ) ),
		}[ filterType ] || [];

	const notices = getFilterNotices( attributes, {
		hasDuplicate,
		optionsResolved:
			{ taxonomy: termsStatus, author: authorsStatus }[ filterType ] ===
			'SUCCESS',
		optionCount: previewOptions.length,
	} );
	const blankNotices = notices.filter( ( key ) =>
		RENDERS_NOTHING.includes( key )
	);

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
	// Mirrors FilterHelper::get_label().
	const labelText = label?.trim() ? label : getDefaultLabel();
	const inheritedNote = __(
		"Options come from the page's query.",
		'pikari-gutenberg-query-filter'
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Filter Settings',
						'pikari-gutenberg-query-filter'
					) }
				>
					{ notices.map( ( key ) => (
						<Notice key={ key } status="warning" isDismissible={ false }>
							{ getNoticeMessage( key ) }
						</Notice>
					) ) }
					{ filterType === 'post-type' && isInherited && (
						<Notice status="info" isDismissible={ false }>
							{ inheritedNote }
						</Notice>
					) }
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
					<FilterInspectorControls
						attributes={ attributes }
						setAttributes={ setAttributes }
						defaultLabel={ getDefaultLabel() }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ blankNotices.length > 0 && (
					<Warning>
						{ blankNotices.map( getNoticeMessage ).join( ' ' ) }
					</Warning>
				) }

				{ ! blankNotices.length && displayType === 'select' && (
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

				{ ! blankNotices.length &&
					( displayType === 'radio' || displayType === 'checkbox' ) && (
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

				{ ! blankNotices.length &&
					filterType === 'post-type' &&
					isInherited && <p>{ inheritedNote }</p> }
			</div>
		</>
	);
}
