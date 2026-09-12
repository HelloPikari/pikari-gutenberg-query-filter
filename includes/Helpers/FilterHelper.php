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
class FilterHelper extends AbstractQueryHelper {

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
    public static function get_post_type_filter_config( $block ) {
        return static::get_query_config( $block, 'post_type' );
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
        return static::get_taxonomy_query_config( $block, $taxonomy );
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

    /**
     * Normalize filter items into render-ready options.
     *
     * The "All" choice is not included; see get_all_option().
     *
     * @param array $items      WP_Post_Type, WP_Term, or WP_User objects, depending on the filter type.
     * @param array $attributes Block attributes.
     * @return array[] {
     *     List of options.
     *
     *     @type string                            $value Value written to the URL query variable.
     *     @type string                            $label Human-readable label.
     *     @type string                            $slug  Slug used to build the option's unique class.
     *     @type \WP_Post_Type|\WP_Term|\WP_User $item  Source object.
     * }
     */
    public static function get_filter_options( array $items, array $attributes ): array {
        $filter_type = $attributes['filterType'] ?? 'post-type';
        $options     = array();

        foreach ( $items as $item ) {
            switch ( $filter_type ) {
                case 'post-type':
                    $options[] = array(
                        'value' => $item->name,
                        'label' => $item->labels->name,
                        'slug'  => $item->name,
                        'item'  => $item,
                    );
                    break;
                case 'taxonomy':
                    $options[] = array(
                        'value' => $item->slug,
                        'label' => $item->name,
                        'slug'  => $item->slug,
                        'item'  => $item,
                    );
                    break;
                case 'author':
                    $options[] = array(
                        'value' => (string) $item->ID,
                        'label' => $item->display_name,
                        'slug'  => $item->user_nicename,
                        'item'  => $item,
                    );
                    break;
            }
        }

        /**
         * Filters the options rendered by a Query Filter block.
         *
         * Return an empty array to hide the block.
         *
         * @param array[] $options    Options with value, label, slug, and item keys.
         * @param array   $attributes Block attributes.
         */
        return apply_filters( 'pikari_gutenberg_query_filter_options', $options, $attributes );
    }

    /**
     * Get the "All" choice that clears the filter in radio groups.
     *
     * It is not part of the filterable options list.
     *
     * @param string $label Label for the choice, from the block's Empty Choice Label.
     * @return array Option with an empty value and the `all` slug.
     */
    public static function get_all_option( string $label ): array {
        return array(
            'value' => '',
            'label' => $label,
            'slug'  => 'all',
            'item'  => null,
        );
    }

    /**
     * Get the classes for a radio or checkbox option's <label>.
     *
     * Adds a unique `{key}_{slug}` class, where key is the taxonomy name for
     * taxonomy filters and the filter type (`post-type`, `author`) otherwise.
     *
     * @param array $option     Option from get_filter_options().
     * @param array $attributes Block attributes.
     * @return string[] Class names.
     */
    public static function get_option_classes( array $option, array $attributes ): array {
        $display_type = sanitize_html_class( $attributes['displayType'] ?? 'checkbox' );
        $classes      = array( 'wp-block-pikari-gutenberg-query-filter__' . $display_type . '-item' );

        $slug = sanitize_html_class( (string) ( $option['slug'] ?? $option['value'] ?? '' ) );
        if ( '' !== $slug ) {
            $filter_type = $attributes['filterType'] ?? 'post-type';
            $key         = 'taxonomy' === $filter_type ? $attributes['taxonomy'] : $filter_type;
            $classes[]   = sanitize_html_class( $key ) . '_' . $slug;
        }

        /**
         * Filters the classes on a radio or checkbox option's <label>.
         *
         * @param string[] $classes    Class names.
         * @param array    $option     Option with value, label, slug, and item keys.
         * @param array    $attributes Block attributes.
         */
        return apply_filters( 'pikari_gutenberg_query_filter_option_classes', $classes, $option, $attributes );
    }

    /**
     * Get the markup rendered inside a radio or checkbox <label>, after the <input>.
     *
     * @param array $option     Option from get_filter_options().
     * @param array $attributes Block attributes.
     * @return string Markup; filtered markup is sanitized with wp_kses_post().
     */
    public static function get_option_label_html( array $option, array $attributes ): string {
        $default_html = sprintf(
            '<span class="wp-block-pikari-gutenberg-query-filter__%s-text">%s</span>',
            sanitize_html_class( $attributes['displayType'] ?? 'checkbox' ),
            esc_html( $option['label'] ?? '' )
        );

        /**
         * Filters the markup rendered inside a radio or checkbox <label>, after the <input>.
         *
         * @param string $html       Default markup: a text span containing the escaped label.
         * @param array  $option     Option with value, label, slug, and item keys.
         * @param array  $attributes Block attributes.
         */
        $html = apply_filters( 'pikari_gutenberg_query_filter_option_label', $default_html, $option, $attributes );

        // The default markup is escaped already; only markup a filter changed needs sanitizing.
        return $html === $default_html ? $html : wp_kses_post( $html );
    }
}
