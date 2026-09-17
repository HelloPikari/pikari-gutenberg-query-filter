<?php
/**
 * The filterable sort option list, which also validates sort requests.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Query;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The sort list that fills every Sort block and validates the sort URL parameter.
 *
 * The same list is used to render options and to accept requests, so an
 * option can never render and then be rejected on the query side.
 */
class SortOptions {

    /**
     * Get the full, normalized sort option list.
     *
     * @return array[] {
     *     List of options.
     *
     *     @type string $key      Sanitized key used in the URL and by find()/match().
     *     @type string $label    Human-readable label.
     *     @type string $orderby  WP_Query orderby value.
     *     @type string $order    `ASC` or `DESC`.
     *     @type string $meta_key Meta key, present only for meta_value/meta_value_num options.
     * }
     */
    public static function all(): array {
        $options = array(
            array(
                'key'     => 'date-desc',
                'label'   => __( 'Date (Newest First)', 'pikari-gutenberg-query-filter' ),
                'orderby' => 'date',
                'order'   => 'DESC',
            ),
            array(
                'key'     => 'date-asc',
                'label'   => __( 'Date (Oldest First)', 'pikari-gutenberg-query-filter' ),
                'orderby' => 'date',
                'order'   => 'ASC',
            ),
            array(
                'key'     => 'title-asc',
                'label'   => __( 'Title (A-Z)', 'pikari-gutenberg-query-filter' ),
                'orderby' => 'title',
                'order'   => 'ASC',
            ),
            array(
                'key'     => 'title-desc',
                'label'   => __( 'Title (Z-A)', 'pikari-gutenberg-query-filter' ),
                'orderby' => 'title',
                'order'   => 'DESC',
            ),
        );

        /**
         * Filters the sort options offered by Sort blocks and accepted in the sort URL parameter.
         *
         * The same list renders every Sort block and validates every request, so
         * no block attributes are passed on purpose: the list must be identical
         * everywhere, or an option could render and then be rejected on the
         * query side.
         *
         * @param array[] $options {
         *     List of options.
         *
         *     @type string $key      Key used in the URL.
         *     @type string $label    Human-readable label.
         *     @type string $orderby  WP_Query orderby value.
         *     @type string $order    `ASC` or `DESC`.
         *     @type string $meta_key Meta key, required when orderby is meta_value or meta_value_num.
         * }
         */
        $options = apply_filters( 'pikari_gutenberg_query_filter_sort_options', $options );

        $options = array_map( array( self::class, 'normalize' ), $options );
        $options = array_filter( $options, array( self::class, 'is_valid' ) );

        return array_values( $options );
    }

    /**
     * Normalize a sort option.
     *
     * @param array $option Option to normalize.
     * @return array Normalized option.
     */
    private static function normalize( array $option ): array {
        if ( isset( $option['key'] ) ) {
            $option['key'] = sanitize_key( $option['key'] );
        }

        $order           = isset( $option['order'] ) ? strtoupper( (string) $option['order'] ) : 'DESC';
        $option['order'] = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

        return $option;
    }

    /**
     * Whether a normalized option has everything it needs to be usable.
     *
     * @param array $option Normalized option.
     * @return bool
     */
    private static function is_valid( array $option ): bool {
        if ( empty( $option['key'] ) || empty( $option['label'] ) || empty( $option['orderby'] ) ) {
            return false;
        }

        if ( in_array( $option['orderby'], array( 'meta_value', 'meta_value_num' ), true ) && empty( $option['meta_key'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Find an option by its key.
     *
     * @param string $key Sort key from the URL.
     * @return array|null The option, or null when no option matches.
     */
    public static function find( string $key ): ?array {
        if ( '' === $key ) {
            return null;
        }

        foreach ( self::all() as $option ) {
            if ( $option['key'] === $key ) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Find the option matching a loop's own orderby and order.
     *
     * @param string $orderby WP_Query orderby value.
     * @param string $order   Order, matched case-insensitively.
     * @return array|null The option, or null when no option matches.
     */
    public static function match( string $orderby, string $order ): ?array {
        if ( '' === $orderby ) {
            return null;
        }

        foreach ( self::all() as $option ) {
            if ( 0 === strcasecmp( $option['orderby'], $orderby ) && 0 === strcasecmp( $option['order'], $order ) ) {
                return $option;
            }
        }

        return null;
    }
}
