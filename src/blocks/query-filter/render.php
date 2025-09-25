<?php

use Pikari\GutenbergQueryFilter\Helpers\FilterHelper;
use Pikari\GutenbergQueryFilter\Helpers\AuthorHelper;

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

// Get configuration based on filter type
switch ( $filter_type ) {
    case 'post-type':
        $query_config = FilterHelper::get_post_type_filter_config( $block );
        $query_var    = $query_config['query_var'];
        $page_var     = $query_config['page_var'];

        $items = FilterHelper::get_filter_post_types( $block );
        if ( empty( $items ) ) {
            return;
        }

        $default_label = __( 'Content Type', 'pikari-gutenberg-query-filter' );
        break;

    case 'taxonomy':
        $taxonomy     = $attributes['taxonomy'];
        $query_config = FilterHelper::get_taxonomy_filter_config( $block, $taxonomy );
        $query_var    = $query_config['query_var'];
        $page_var     = $query_config['page_var'];

        $items = FilterHelper::get_taxonomy_filter_terms( $taxonomy );
        if ( false === $items ) {
            return;
        }

        $taxonomy_obj  = get_taxonomy( $taxonomy );
        $default_label = $taxonomy_obj ? $taxonomy_obj->label : __( 'Filter by', 'pikari-gutenberg-query-filter' );
        break;

    case 'author':
        $query_config = AuthorHelper::get_author_filter_config( $block );
        $query_var    = $query_config['query_var'];
        $page_var     = $query_config['page_var'];

        $items = AuthorHelper::get_filter_authors();

        if ( empty( $items ) ) {
            return;
        }

        $default_label = __( 'Author', 'pikari-gutenberg-query-filter' );
        break;

    default:
        return;
}

// Get current selection
$current_value = FilterHelper::get_current_filter_value( $query_var );

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
    <label class="wp-block-pikari-gutenberg-query-filter__label<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $id ); ?>">
        <?php echo esc_html( $label_text ); ?>
    </label>

    <?php if ( $display_type === 'select' ) : ?>
        <select class="wp-block-pikari-gutenberg-query-filter__select" id="<?php echo esc_attr( $id ); ?>" data-wp-on--change="actions.handleSelect">
            <option value=""><?php echo esc_html( $empty_label ); ?></option>
        <?php foreach ( $items as $item ) : ?>
            <?php
            switch ( $filter_type ) {
                case 'post-type':
                    $option_label = $item->labels->name;
                    $option_value = $item->name;
                    break;
                case 'taxonomy':
                    $option_label = $item->name;
                    $option_value = $item->slug;
                    break;
                case 'author':
                    $option_label = $item->display_name;
                    $option_value = (string) $item->ID;
                    break;
            }
            ?>
            <option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $current_value, $option_value ); ?>>
            <?php echo esc_html( $option_label ); ?>
            </option>
        <?php endforeach; ?>
        </select>

    <?php elseif ( $display_type === 'radio' ) : ?>
        <div class="wp-block-pikari-gutenberg-query-filter__radio-group<?php echo esc_attr( $layout_class ); ?>">
            <label class="wp-block-pikari-gutenberg-query-filter__radio-item">
                <input type="radio" name="<?php echo esc_attr( $id ); ?>" value="" <?php checked( empty( $current_value ) ); ?> data-wp-on--change="actions.handleSelect">
                <span class="wp-block-pikari-gutenberg-query-filter__radio-text"><?php echo esc_html( $empty_label ); ?></span>
            </label>
        <?php foreach ( $items as $item ) : ?>
            <?php
            switch ( $filter_type ) {
                case 'post-type':
                    $option_label = $item->labels->name;
                    $option_value = $item->name;
                    break;
                case 'taxonomy':
                    $option_label = $item->name;
                    $option_value = $item->slug;
                    break;
                case 'author':
                    $option_label = $item->display_name;
                    $option_value = (string) $item->ID;
                    break;
            }
            ?>
            <label class="wp-block-pikari-gutenberg-query-filter__radio-item">
                <input type="radio" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( $current_value, $option_value ); ?> data-wp-on--change="actions.handleSelect">
                <span class="wp-block-pikari-gutenberg-query-filter__radio-text"><?php echo esc_html( $option_label ); ?></span>
            </label>
        <?php endforeach; ?>
        </div>

    <?php elseif ( $display_type === 'checkbox' ) : ?>
        <div class="wp-block-pikari-gutenberg-query-filter__checkbox-group<?php echo esc_attr( $layout_class ); ?>">
        <?php
        $selected_values = ! empty( $current_value ) ? explode( ',', $current_value ) : array();
        foreach ( $items as $item ) :
            switch ( $filter_type ) {
                case 'post-type':
                    $option_label = $item->labels->name;
                    $option_value = $item->name;
                    break;
                case 'taxonomy':
                    $option_label = $item->name;
                    $option_value = $item->slug;
                    break;
                case 'author':
                    $option_label = $item->display_name;
                    $option_value = (string) $item->ID;
                    break;
            }

            $is_checked = in_array( $option_value, $selected_values, true );
            ?>
            <label class="wp-block-pikari-gutenberg-query-filter__checkbox-item">
                <input type="checkbox" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( $is_checked ); ?> data-wp-on--change="actions.updateFilters">
                <span class="wp-block-pikari-gutenberg-query-filter__checkbox-text"><?php echo esc_html( $option_label ); ?></span>
            </label>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>