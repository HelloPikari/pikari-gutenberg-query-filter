<?php

use Pikari\GutenbergQueryFilter\Helpers\FilterHelper;

// Validate required attributes.
if ( empty( $attributes['taxonomy'] ) ) {
    return;
}

// Initialize variables.
$id               = 'query-filter-' . wp_generate_uuid4();
$display_type     = $attributes['displayType'] ?? 'select';
$layout_direction = $attributes['layoutDirection'] ?? 'vertical';
$taxonomy         = get_taxonomy( $attributes['taxonomy'] );

// Get query configuration.
$query_config = FilterHelper::get_taxonomy_filter_config( $block, $attributes['taxonomy'] );
$query_var    = $query_config['query_var'];
$page_var     = $query_config['page_var'];
$base_url     = $query_config['base_url'];

// Get terms.
$terms = FilterHelper::get_taxonomy_filter_terms( $attributes['taxonomy'] );

if ( false === $terms ) {
    return;
}
// Get current selection.
$current_value = FilterHelper::get_current_filter_value( $query_var );

// Prepare template variables.
$label_text    = $attributes['label'] ?? $taxonomy->label;
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
    <label class="wp-block-pikari-gutenberg-query-filter-taxonomy__label wp-block-pikari-gutenberg-query-filter__label<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $id ); ?>">
        <?php echo esc_html( $label_text ); ?>
    </label>

    <?php if ( $display_type === 'select' ) : ?>
        <select class="wp-block-pikari-gutenberg-query-filter-taxonomy__select wp-block-pikari-gutenberg-query-filter__select" id="<?php echo esc_attr( $id ); ?>" data-wp-on--change="actions.navigate">
            <option value="<?php echo esc_attr( $base_url ); ?>"><?php echo esc_html( $empty_label ); ?></option>
        <?php foreach ( $terms as $term ) : ?>
            <?php $option_url = FilterHelper::build_filter_url( $base_url, $query_var, $page_var, $term->slug ); ?>
                <option value="<?php echo esc_attr( $option_url ); ?>" <?php selected( $term->slug, $current_value ); ?>>
            <?php echo esc_html( $term->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php elseif ( $display_type === 'radio' ) : ?>
        <div class="wp-block-pikari-gutenberg-query-filter-taxonomy__radio-group wp-block-pikari-gutenberg-query-filter__radio-group<?php echo esc_attr( $layout_class ); ?>">
            <label>
                <input type="radio" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $base_url ); ?>" data-wp-on--change="actions.navigate" <?php checked( empty( $current_value ) ); ?> />
        <?php echo esc_html( $empty_label ); ?>
            </label>
        <?php foreach ( $terms as $term ) : ?>
            <?php $option_url = FilterHelper::build_filter_url( $base_url, $query_var, $page_var, $term->slug ); ?>
                <label>
                    <input type="radio" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $option_url ); ?>" data-wp-on--change="actions.navigate" <?php checked( $term->slug, $current_value ); ?> />
            <?php echo esc_html( $term->name ); ?>
                </label>
            <?php endforeach; ?>
        </div>

    <?php elseif ( $display_type === 'checkbox' ) : ?>
        <div class="wp-block-pikari-gutenberg-query-filter-taxonomy__checkbox-group wp-block-pikari-gutenberg-query-filter__checkbox-group<?php echo esc_attr( $layout_class ); ?>">
        <?php
        $selected_terms = ! empty( $current_value ) ? explode( ',', $current_value ) : array();
        foreach ( $terms as $term ) :
            $is_checked   = in_array( $term->slug, $selected_terms, true );
            $new_terms    = $is_checked
            ? array_diff( $selected_terms, array( $term->slug ) )
            : array_merge( $selected_terms, array( $term->slug ) );
            $new_terms    = array_filter( $new_terms );
            $checkbox_url = empty( $new_terms )
            ? $base_url
            : FilterHelper::build_filter_url( $base_url, $query_var, $page_var, implode( ',', $new_terms ) );
            ?>
                <label>
                    <input type="checkbox" value="<?php echo esc_attr( $checkbox_url ); ?>" data-wp-on--change="actions.navigate" <?php checked( $is_checked ); ?> />
            <?php echo esc_html( $term->name ); ?>
                </label>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>