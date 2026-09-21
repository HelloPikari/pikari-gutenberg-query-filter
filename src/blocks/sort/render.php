<?php

use Pikari\GutenbergQueryFilter\Integrations\MainQueryFilter;
use Pikari\GutenbergQueryFilter\Query\SortOptions;
use Pikari\GutenbergQueryFilter\Url\QueryParams;

// Initialize variables.
$id = 'query-filter-' . wp_generate_uuid4();

// Get query parameter names.
$params   = QueryParams::from_block( $block );
$sort_var = $params->key( 'sort' );
$form_id  = $params->form_id();

// Resolve the requested sort key against the allowlist (spec §3.2).
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtering parameters don't require nonces.
$requested_key = isset( $_GET[ $sort_var ] ) ? sanitize_text_field( wp_unslash( $_GET[ $sort_var ] ) ) : '';
$requested     = SortOptions::find( $requested_key );

// Find the option matching the loop's own order, which renders with an empty
// value so choosing it removes the parameter (spec §4.3).
if ( $params->is_inherit() ) {
    // Block context always claims date/desc for an inherited loop, so the
    // main query's own unfiltered order is read instead. An empty orderby,
    // as on a search request, means relevance and matches no option.
    $default_orderby = (string) ( MainQueryFilter::original( 'orderby' ) ?? '' );
    $default_order   = (string) ( MainQueryFilter::original( 'order' ) ?? '' );
} else {
    $default_orderby = $block->context['query']['orderBy'] ?? '';
    $default_order   = $block->context['query']['order'] ?? '';
}
$default_option = SortOptions::match( $default_orderby, $default_order );

// Prepare template variables.
$label_text  = $attributes['label'] ?? __( 'Sort By', 'pikari-gutenberg-query-filter' );
$empty_label = $attributes['emptyLabel'] ?: __( 'Default', 'pikari-gutenberg-query-filter' );
$show_label  = $attributes['showLabel'] ?? true;
$label_class = $show_label ? '' : ' screen-reader-text';

$wrapper_attributes = get_block_wrapper_attributes(
    array(
        'class' => 'wp-block-pikari-gutenberg-query-filter',
    )
);
?>

<div <?php echo wp_kses_post( $wrapper_attributes ); ?> data-wp-interactive="pikari/gutenberg-query-filter">
    <label class="wp-block-pikari-gutenberg-query-filter-sort__label wp-block-pikari-gutenberg-query-filter__label<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $id ); ?>">
        <?php echo esc_html( $label_text ); ?>
    </label>

    <select class="wp-block-pikari-gutenberg-query-filter-sort__select wp-block-pikari-gutenberg-query-filter__select" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $sort_var ); ?>" form="<?php echo esc_attr( $form_id ); ?>" data-wp-on--change="pikari/gutenberg-query-filter::actions.change">
        <?php if ( null === $default_option ) : ?>
            <option value="" <?php selected( null === $requested ); ?>><?php echo esc_html( $empty_label ); ?></option>
        <?php endif; ?>
        <?php foreach ( SortOptions::all() as $option ) : ?>
            <?php
            $is_default  = null !== $default_option && $default_option['key'] === $option['key'];
            $value       = $is_default ? '' : $option['key'];
            $is_selected = null !== $requested ? $requested['key'] === $option['key'] : $is_default;
            ?>
            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $is_selected ); ?>>
            <?php echo esc_html( $option['label'] ); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <noscript>
        <button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="wp-block-pikari-gutenberg-query-filter__submit">
            <?php esc_html_e( 'Apply filters', 'pikari-gutenberg-query-filter' ); ?>
        </button>
    </noscript>
</div>
