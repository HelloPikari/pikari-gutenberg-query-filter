<?php
/**
 * Filter helper functions for query filter blocks.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Helpers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper class for query filter functionality.
 */
class FilterHelper {

    /**
     * Get query variables and URLs for the post type filter.
     *
     * @param array $block Block instance.
     * @return array {
     *     Query configuration array.
     *     @type string $query_var Query variable name.
     *     @type string $page_var  Page variable name.
     *     @type string $base_url  Base URL without query parameters.
     * }
     */
    public static function get_filter_query_config( $block ) {
        if ( $block->context['query']['inherit'] ) {
            $query_var = 'query-post_type';
            $page_var  = 'page';
            $base_url  = str_replace( '/page/' . get_query_var( 'paged' ), '', remove_query_arg( array( $query_var, $page_var ) ) );
        } else {
            $query_id  = $block->context['queryId'] ?? 0;
            $query_var = sprintf( 'query-%d-post_type', $query_id );
            $page_var  = isset( $block->context['queryId'] ) ? 'query-' . $block->context['queryId'] . '-page' : 'query-page';
            $base_url  = remove_query_arg( array( $query_var, $page_var ) );
        }

        return array(
            'query_var' => $query_var,
            'page_var'  => $page_var,
            'base_url'  => $base_url,
        );
    }

    /**
     * Get query configuration for taxonomy filter.
     *
     * @param array  $block      Block instance.
     * @param string $taxonomy   Taxonomy name.
     * @return array {
     *     Query configuration array.
     *     @type string $query_var Query variable name.
     *     @type string $page_var  Page variable name.
     *     @type string $base_url  Base URL without query parameters.
     * }
     */
    public static function get_taxonomy_filter_config( $block, $taxonomy ) {
        if ( empty( $block->context['query']['inherit'] ) ) {
            $query_id  = $block->context['queryId'] ?? 0;
            $query_var = sprintf( 'query-%d-%s', $query_id, $taxonomy );
            $page_var  = isset( $block->context['queryId'] ) ? 'query-' . $block->context['queryId'] . '-page' : 'query-page';
            $base_url  = remove_query_arg( array( $query_var, $page_var ) );
        } else {
            $query_var = sprintf( 'query-%s', $taxonomy );
            $page_var  = 'page';
            $base_url  = str_replace( '/page/' . get_query_var( 'paged' ), '', remove_query_arg( array( $query_var, $page_var ) ) );
        }

        return array(
            'query_var' => $query_var,
            'page_var'  => $page_var,
            'base_url'  => $base_url,
        );
    }

    /**
     * Build URL for filter option.
     *
     * @param string $base_url  Base URL.
     * @param string $query_var Query variable name.
     * @param string $page_var  Page variable name.
     * @param string $value     Filter value.
     * @return string Filter URL.
     */
    public static function build_filter_url( $base_url, $query_var, $page_var, $value = '' ) {
        if ( empty( $value ) ) {
            return $base_url;
        }

        return add_query_arg(
            array(
                $query_var => $value,
                $page_var  => false,
            ),
            $base_url
        );
    }

    /**
     * Get current selected value from query parameters.
     *
     * @param string $query_var Query variable name.
     * @return string Sanitized current value.
     */
    public static function get_current_filter_value( $query_var ) {
        return isset( $_GET[ $query_var ] ) ? sanitize_text_field( wp_unslash( $_GET[ $query_var ] ) ) : '';
    }

    /**
     * Get post types for the filter.
     *
     * @param array $block Block instance.
     * @return array Array of WP_Post_Type objects.
     */
    public static function get_filter_post_types( $block ) {
        global $wp_query;

        $post_types = array_map( 'trim', explode( ',', $block->context['query']['postType'] ?? 'post' ) );

        // Support for enhanced query block.
        if ( isset( $block->context['query']['multiple_posts'] ) && is_array( $block->context['query']['multiple_posts'] ) ) {
            $post_types = array_merge( $post_types, $block->context['query']['multiple_posts'] );
        }

        // Fill in inherited query types.
        if ( $block->context['query']['inherit'] ) {
            if ( $wp_query->get( 'query-filter-post_type' ) === 'any' ) {
                $inherited_post_types = get_post_types(
                    array(
                        'public'              => true,
                        'exclude_from_search' => false,
                    )
                );
            } else {
                $inherited_post_types = (array) $wp_query->get( 'query-filter-post_type' );
            }

            $post_types = array_merge( $post_types, $inherited_post_types );

            if ( ! get_option( 'wp_attachment_pages_enabled' ) ) {
                $post_types = array_diff( $post_types, array( 'attachment' ) );
            }
        }

        $post_types = array_unique( $post_types );
        $post_types = array_map( 'get_post_type_object', $post_types );
        $post_types = array_filter( $post_types );

        return $post_types;
    }

    /**
     * Get terms for taxonomy filter.
     *
     * @param string $taxonomy Taxonomy name.
     * @return array|false Array of WP_Term objects or false on error.
     */
    public static function get_taxonomy_filter_terms( $taxonomy ) {
        $terms = get_terms(
            array(
                'hide_empty' => true,
                'taxonomy'   => $taxonomy,
                'number'     => 100,
            )
        );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return false;
        }

        return $terms;
    }
}
