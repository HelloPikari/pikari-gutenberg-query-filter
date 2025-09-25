<?php

use Pikari\GutenbergQueryFilter\Helpers\SortHelper;

// Initialize variables.
$id = 'query-filter-' . wp_generate_uuid4();

// Get query configuration.
$query_config = SortHelper::get_sort_config( $block );
$orderby_var  = $query_config['orderby_var'];
$order_var    = $query_config['order_var'];
$page_var     = $query_config['page_var'];

// Get current sort selection.
$current_sort = SortHelper::get_current_sort_value( $orderby_var, $order_var );

// Prepare template variables.
$label_text    = $attributes['label'] ?? __( 'Sort By', 'pikari-gutenberg-query-filter' );
$empty_label   = $attributes['emptyLabel'] ?? __( 'Date', 'pikari-gutenberg-query-filter' );
$show_label    = $attributes['showLabel'] ?? true;
$label_class   = $show_label ? '' : ' screen-reader-text';

// Define sort options.
$sort_options = array(
    array(
        'label'   => __( 'Date (Newest First)', 'pikari-gutenberg-query-filter' ),
        'orderby' => 'date',
        'order'   => 'desc',
        'value'   => 'date-desc',
    ),
    array(
        'label'   => __( 'Date (Oldest First)', 'pikari-gutenberg-query-filter' ),
        'orderby' => 'date',
        'order'   => 'asc',
        'value'   => 'date-asc',
    ),
    array(
        'label'   => __( 'Title (A-Z)', 'pikari-gutenberg-query-filter' ),
        'orderby' => 'title',
        'order'   => 'asc',
        'value'   => 'title-asc',
    ),
    array(
        'label'   => __( 'Title (Z-A)', 'pikari-gutenberg-query-filter' ),
        'orderby' => 'title',
        'order'   => 'desc',
        'value'   => 'title-desc',
    ),
);

// Determine current selection.
$current_value = '';
if ( ! empty( $current_sort['orderby'] ) && ! empty( $current_sort['order'] ) ) {
    $current_value = $current_sort['orderby'] . '-' . $current_sort['order'];
}

// Check if this is the default state (no URL parameters).
$has_sort_params = isset( $_GET[ $orderby_var ] ) || isset( $_GET[ $order_var ] );
$is_default_sort = ! $has_sort_params && $current_value === 'date-desc';

$wrapper_attributes = get_block_wrapper_attributes(
    array(
        'class' => 'wp-block-pikari-gutenberg-query-filter',
    )
);
?>

<div <?php echo wp_kses_post( $wrapper_attributes ); ?> data-wp-interactive="pikari/gutenberg-query-filter" data-wp-context='
<?php
echo wp_json_encode(
    array(
        'orderbyVar' => $orderby_var,
        'orderVar' => $order_var,
        'pageVar' => $page_var,
    )
);
?>
'>
    <label class="wp-block-pikari-gutenberg-query-filter-sort__label wp-block-pikari-gutenberg-query-filter__label<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $id ); ?>">
        <?php echo esc_html( $label_text ); ?>
    </label>

    <select class="wp-block-pikari-gutenberg-query-filter-sort__select wp-block-pikari-gutenberg-query-filter__select" id="<?php echo esc_attr( $id ); ?>" data-wp-on--change="actions.handleSort">
        <?php if ( ! $is_default_sort ) : ?>
            <option value="" <?php selected( empty( $current_value ) || ( ! $has_sort_params && $current_value === 'date-desc' ) ); ?>><?php echo esc_html( $empty_label ); ?></option>
        <?php endif; ?>
        <?php foreach ( $sort_options as $option ) : ?>
            <option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $option['value'], $current_value ); ?>>
            <?php echo esc_html( $option['label'] ); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>