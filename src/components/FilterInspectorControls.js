import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	TextControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';

export default function FilterInspectorControls({ attributes, setAttributes, defaultLabel }) {
	const { emptyLabel, label, showLabel, displayType, layoutDirection } = attributes;

	return (
		<>
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
			{ ( displayType === 'radio' || displayType === 'checkbox' ) && (
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
				defaultValue={ defaultLabel }
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
		</>
	);
}