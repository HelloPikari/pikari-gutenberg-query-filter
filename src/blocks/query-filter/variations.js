import { __ } from '@wordpress/i18n';

const variations = [
	{
		name: 'post-type',
		title: __( 'Post Type Filter', 'pikari-gutenberg-query-filter' ),
		description: __( 'Filter posts by post type', 'pikari-gutenberg-query-filter' ),
		attributes: {
			filterType: 'post-type',
			label: __( 'Content Type', 'pikari-gutenberg-query-filter' ),
		},
		isDefault: true,
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) => blockAttributes.filterType === 'post-type',
		icon: 'filter',
	},
	{
		name: 'taxonomy',
		title: __( 'Taxonomy Filter', 'pikari-gutenberg-query-filter' ),
		description: __( 'Filter posts by taxonomy terms', 'pikari-gutenberg-query-filter' ),
		attributes: {
			filterType: 'taxonomy',
			label: __( 'Filter by', 'pikari-gutenberg-query-filter' ),
		},
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) => blockAttributes.filterType === 'taxonomy',
		icon: 'category',
	},
	{
		name: 'author',
		title: __( 'Author Filter', 'pikari-gutenberg-query-filter' ),
		description: __( 'Filter posts by author', 'pikari-gutenberg-query-filter' ),
		attributes: {
			filterType: 'author',
			label: __( 'Author', 'pikari-gutenberg-query-filter' ),
		},
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) => blockAttributes.filterType === 'author',
		icon: 'admin-users',
	},
];

export default variations;
