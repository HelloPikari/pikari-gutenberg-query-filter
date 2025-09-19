<?php
/**
 * Sort helper functions for query sort blocks.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Helpers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper class for query sort functionality.
 */
class SortHelper extends AbstractQueryHelper {

    /**
     * Get query configuration for sort filter.
     *
     * @param array $block Block instance.
     * @return array {
     *     Query configuration array.
     *     @type string $orderby_var Query variable name for orderby.
     *     @type string $order_var   Query variable name for order.
     *     @type string $page_var    Page variable name.
     *     @type string $base_url    Base URL without query parameters.
     * }
     */
    public static function get_sort_config( $block ) {
        return static::get_sort_query_config( $block );
    }

    /**
     * Get current sort values from query parameters.
     *
     * @param string $orderby_var Query variable name for orderby.
     * @param string $order_var   Query variable name for order.
     * @return array {
     *     Current sort values.
     *     @type string $orderby Current orderby value.
     *     @type string $order   Current order value.
     * }
     */
    public static function get_current_sort_value( $orderby_var, $order_var ) {
        return array(
            'orderby' => isset( $_GET[ $orderby_var ] ) ? sanitize_text_field( wp_unslash( $_GET[ $orderby_var ] ) ) : '',
            'order'   => isset( $_GET[ $order_var ] ) ? sanitize_text_field( wp_unslash( $_GET[ $order_var ] ) ) : '',
        );
    }
}
