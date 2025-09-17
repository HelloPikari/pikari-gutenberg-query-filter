<?php

use Pikari\GutenbergQueryFilter\Helpers\FilterHelper;

// Initialize variables.
global $wp_query;

$id               = 'pikari-gutenberg-query-filter-' . wp_generate_uuid4();
$display_type     = $attributes['displayType'] ?? 'select';
$layout_direction = $attributes['layoutDirection'] ?? 'vertical';

// Get query configuration.
$query_config = FilterHelper::get_filter_query_config( $block );
$query_var    = $query_config['query_var'];
$page_var     = $query_config['page_var'];
$base_url     = $query_config['base_url'];

// Get post types.
$post_types = FilterHelper::get_filter_post_types( $block );

if ( empty( $post_types ) ) {
    return;
}
// Get current selection.
$current_value = FilterHelper::get_current_filter_value( $query_var );

// Prepare template variables.
$label_text    = $attributes['label'] ?? __( 'Content Type', 'pikari-gutenberg-query-filter' );
$empty_label   = $attributes['emptyLabel'] ?: __( 'All', 'pikari-gutenberg-query-filter' );
$show_label    = $attributes['showLabel'] ?? true;
$label_class   = $show_label ? '' : ' screen-reader-text';
$layout_class  = $layout_direction === 'horizontal' ? ' horizontal' : '';

$wrapper_attributes = get_block_wrapper_attributes(
    array(
        'class' => 'wp-block-pikari-gutenberg-query-filter',
    )
);
?>

<div <?php echo wp_kses_post( $wrapper_attributes ); ?> data-wp-interactive="pikari-gutenberg-query-filter" data-wp-context="{}">
    <label class="wp-block-pikari-gutenberg-query-filter-post-type__label wp-block-pikari-gutenberg-query-filter__label<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $id ); ?>">
        <?php echo esc_html( $label_text ); ?>
    </label>

    <?php if ( $display_type === 'select' ) : ?>
        <select class="wp-block-pikari-gutenberg-query-filter-post-type__select wp-block-pikari-gutenberg-query-filter__select" id="<?php echo esc_attr( $id ); ?>" data-wp-on--change="actions.navigate">
            <option value="<?php echo esc_attr( $base_url ); ?>"><?php echo esc_html( $empty_label ); ?></option>
        <?php foreach ( $post_types as $post_type ) : ?>
            <?php $option_url = FilterHelper::build_filter_url( $base_url, $query_var, $page_var, $post_type->name ); ?>
                <option value="<?php echo esc_attr( $option_url ); ?>" <?php selected( $post_type->name, $current_value ); ?>>
            <?php echo esc_html( $post_type->label ); ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php elseif ( $display_type === 'radio' ) : ?>
        <div class="wp-block-pikari-gutenberg-query-filter-post-type__radio-group wp-block-pikari-gutenberg-query-filter__radio-group<?php echo esc_attr( $layout_class ); ?>">
            <label>
                <input type="radio" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $base_url ); ?>" data-wp-on--change="actions.navigate" <?php checked( empty( $current_value ) ); ?> />
        <?php echo esc_html( $empty_label ); ?>
            </label>
        <?php foreach ( $post_types as $post_type ) : ?>
            <?php $option_url = FilterHelper::build_filter_url( $base_url, $query_var, $page_var, $post_type->name ); ?>
                <label>
                    <input type="radio" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $option_url ); ?>" data-wp-on--change="actions.navigate" <?php checked( $post_type->name, $current_value ); ?> />
            <?php echo esc_html( $post_type->label ); ?>
                </label>
            <?php endforeach; ?>
        </div>

    <?php elseif ( $display_type === 'checkbox' ) : ?>
        <div class="wp-block-pikari-gutenberg-query-filter-post-type__checkbox-group wp-block-pikari-gutenberg-query-filter__checkbox-group<?php echo esc_attr( $layout_class ); ?>">
        <?php
        $selected_types = ! empty( $current_value ) ? explode( ',', $current_value ) : array();
        foreach ( $post_types as $post_type ) :
            $is_checked   = in_array( $post_type->name, $selected_types, true );
            $new_types    = $is_checked
            ? array_diff( $selected_types, array( $post_type->name ) )
            : array_merge( $selected_types, array( $post_type->name ) );
            $new_types    = array_filter( $new_types );
            $checkbox_url = empty( $new_types )
            ? $base_url
            : FilterHelper::build_filter_url( $base_url, $query_var, $page_var, implode( ',', $new_types ) );
            ?>
                <label>
                    <input type="checkbox" value="<?php echo esc_attr( $checkbox_url ); ?>" data-wp-on--change="actions.navigate" <?php checked( $is_checked ); ?> />
            <?php echo esc_html( $post_type->label ); ?>
                </label>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>