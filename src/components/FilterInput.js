/* eslint-disable jsx-a11y/label-has-associated-control */
import classNames from 'classnames';

export default function FilterInput( {
	type = 'checkbox',
	name,
	value,
	checked,
	disabled,
	className,
	onChange,
	children,
	...props
} ) {
	const labelClassName = classNames(
		`wp-block-pikari-gutenberg-query-filter__${ type }-item`,
		className
	);

	return (
		<label className={ labelClassName }>
			<input
				type={ type }
				name={ name }
				value={ value }
				checked={ checked }
				disabled={ disabled }
				onChange={ onChange }
				{ ...props }
			/>
			{ children }
		</label>
	);
}