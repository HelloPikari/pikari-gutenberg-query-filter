<?php

use Pikari\GutenbergQueryFilter\Helpers\FilterHelper;
use Pikari\GutenbergQueryFilter\Helpers\AuthorHelper;
use Pikari\GutenbergQueryFilter\Url\QueryParams;

// Validate required attributes
$filter_type = $attributes['filterType'] ?? 'post-type';

// Early return for taxonomy filter without taxonomy set
if ( $filter_type === 'taxonomy' && empty( $attributes['taxonomy'] ) ) {
    return;
}

// Initialize variables
$id               = 'pikari-gutenberg-query-filter-' . wp_generate_uuid4();
$display_type     = $attributes['displayType'] ?? 'select';
$layout_direction = $attributes['layoutDirection'] ?? 'vertical';

$params   = QueryParams::from_block( $block );
$page_var = $params->page_key();

// Get configuration based on filter type
switch ( $filter_type ) {
    case 'post-type':
        $query_var = $params->key( 'post_type' );

        $items = FilterHelper::get_filter_post_types( $block );
        if ( empty( $items ) ) {
            return;
        }

        $default_label = __( 'Content Type', 'pikari-gutenberg-query-filter' );
        break;

    case 'taxonomy':
        $taxonomy  = $attributes['taxonomy'];
        $query_var = $params->key( $taxonomy );

        $items = FilterHelper::get_taxonomy_filter_terms( $taxonomy );
        if ( false === $items ) {
            return;
        }

        $taxonomy_obj  = get_taxonomy( $taxonomy );
        $default_label = $taxonomy_obj ? $taxonomy_obj->label : __( 'Filter by', 'pikari-gutenberg-query-filter' );
        break;

    case 'author':
        $query_var = $params->key( 'author' );

        $items = AuthorHelper::get_filter_authors();

        if ( empty( $items ) ) {
            return;
        }

        $default_label = __( 'Author', 'pikari-gutenberg-query-filter' );
        break;

    default:
        return;
}

// Normalize items into options. Filterable via pikari_gutenberg_query_filter_options.
$options = FilterHelper::get_filter_options( $items, $attributes );
if ( empty( $options ) ) {
    return;
}

// Get current selection.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Filtering parameters don't require nonces; sanitized below via sanitize_text_field() or sanitize_title_for_query(), depending on $filter_type.
$raw_value = isset( $_GET[ $query_var ] ) && is_scalar( $_GET[ $query_var ] ) ? wp_unslash( $_GET[ $query_var ] ) : '';

if ( 'author' === $filter_type ) {
    // Author option values are nicenames, which WordPress stores percent-
    // encoded for a name it can't transliterate (spec §3.2). sanitize_text_field()
    // strips those octets, so each comma-separated value is sanitized on its
    // own with sanitize_title_for_query() instead, matching
    // Url\FilterState::resolve_author_ids(). Running the whole string through
    // it at once would also strip the commas separating multiple values.
    $current_value = implode(
        ',',
        array_map( 'sanitize_title_for_query', explode( ',', $raw_value ) )
    );
} else {
    $current_value = sanitize_text_field( $raw_value );
}

// Prepare template variables
$label_text    = $attributes['label'] ?? $default_label;
$empty_label   = $attributes['emptyLabel'] ?: __( 'All', 'pikari-gutenberg-query-filter' );
$show_label    = $attributes['showLabel'] ?? true;
$label_class   = $show_label ? '' : ' screen-reader-text';
$layout_class  = $layout_direction === 'horizontal' ? ' has-layout-horizontal' : '';

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
        'queryVar' => $query_var,
        'pageVar' => $page_var,
        'filterType' => $filter_type,
        'taxonomy' => $filter_type === 'taxonomy' ? $taxonomy : '',
    )
);
?>
'>
    <?php if ( $display_type === 'select' ) : ?>
        <label class="wp-block-pikari-gutenberg-query-filter__label<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $id ); ?>">
        <?php echo esc_html( $label_text ); ?>
        </label>
        <select class="wp-block-pikari-gutenberg-query-filter__select" id="<?php echo esc_attr( $id ); ?>" data-wp-on--change="actions.handleSelect">
            <option value=""><?php echo esc_html( $empty_label ); ?></option>
        <?php foreach ( $options as $option ) : ?>
            <option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $current_value, $option['value'] ); ?>>
            <?php echo esc_html( $option['label'] ); ?>
            </option>
        <?php endforeach; ?>
        </select>

    <?php elseif ( $display_type === 'radio' ) : ?>
        <fieldset class="wp-block-pikari-gutenberg-query-filter__fieldset">
        <legend class="wp-block-pikari-gutenberg-query-filter__label<?php echo esc_attr( $label_class ); ?>"><?php echo esc_html( $label_text ); ?></legend>
        <div class="wp-block-pikari-gutenberg-query-filter__radio-group<?php echo esc_attr( $layout_class ); ?>">
        <?php foreach ( array_merge( array( FilterHelper::get_all_option( $empty_label ) ), $options ) as $option ) : ?>
            <label class="<?php echo esc_attr( implode( ' ', FilterHelper::get_option_classes( $option, $attributes ) ) ); ?>">
                <input type="radio" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>" <?php checked( $current_value, $option['value'] ); ?> data-wp-on--change="actions.handleSelect">
            <?php echo FilterHelper::get_option_label_html( $option, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized with wp_kses_post() in get_option_label_html(). ?>
            </label>
        <?php endforeach; ?>
        </div>
        </fieldset>

    <?php elseif ( $display_type === 'checkbox' ) : ?>
        <fieldset class="wp-block-pikari-gutenberg-query-filter__fieldset">
        <legend class="wp-block-pikari-gutenberg-query-filter__label<?php echo esc_attr( $label_class ); ?>"><?php echo esc_html( $label_text ); ?></legend>
        <div class="wp-block-pikari-gutenberg-query-filter__checkbox-group<?php echo esc_attr( $layout_class ); ?>">
        <?php
        $selected_values = ! empty( $current_value ) ? explode( ',', $current_value ) : array();
        foreach ( $options as $option ) :
            $is_checked = in_array( (string) $option['value'], $selected_values, true );
            ?>
            <label class="<?php echo esc_attr( implode( ' ', FilterHelper::get_option_classes( $option, $attributes ) ) ); ?>">
                <input type="checkbox" value="<?php echo esc_attr( $option['value'] ); ?>" <?php checked( $is_checked ); ?> data-wp-on--change="actions.updateFilters">
            <?php echo FilterHelper::get_option_label_html( $option, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized with wp_kses_post() in get_option_label_html(). ?>
            </label>
        <?php endforeach; ?>
        </div>
        </fieldset>
    <?php endif; ?>
</div>
