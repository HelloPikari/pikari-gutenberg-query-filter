<?php
/**
 * Filter helper functions for query filter blocks.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Helpers;

use Pikari\GutenbergQueryFilter\Integrations\MainQueryFilter;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper class for query filter functionality.
 */
class FilterHelper {

    /**
     * Get post types for the filter.
     *
     * @param array $block Block instance.
     * @return array Array of WP_Post_Type objects.
     */
    public static function get_filter_post_types( $block ) {
        global $wp_query;

        if ( $block->context['query']['inherit'] ) {
            $post_types = self::get_inherited_post_types( $wp_query );
        } else {
            $post_types = array_map( 'trim', explode( ',', $block->context['query']['postType'] ?? 'post' ) );

            // Support for enhanced query block.
            if ( isset( $block->context['query']['multiple_posts'] ) && is_array( $block->context['query']['multiple_posts'] ) ) {
                $post_types = array_merge( $post_types, $block->context['query']['multiple_posts'] );
            }
        }

        $post_types = array_unique( $post_types );
        $post_types = array_map( 'get_post_type_object', $post_types );
        $post_types = array_filter( $post_types );

        return $post_types;
    }

    /**
     * Get the post types an inherited loop's filter offers (spec §4.5).
     *
     * These replace the block's own postType attribute rather than merging
     * with it: a Query block's postType is an editor convenience with no
     * bearing on what the main query it inherits actually returns.
     *
     * @param \WP_Query $wp_query The main query.
     * @return string[] Post type slugs.
     */
    private static function get_inherited_post_types( \WP_Query $wp_query ): array {
        $original = MainQueryFilter::original( 'post_type' );

        // Nothing was recorded when no inherited filter parameter was on
        // the request (e.g. the page's first, unfiltered load), so fall
        // back to the request's own raw post_type (spec §4.5).
        if ( null === $original ) {
            $original = $wp_query->query['post_type'] ?? '';
        }

        if ( is_array( $original ) && ! empty( $original ) ) {
            $post_types = $original;
        } elseif ( is_string( $original ) && '' !== $original && 'any' !== $original ) {
            $post_types = array( $original );
        } else {
            $post_types = self::get_default_inherited_post_types( $wp_query );
        }

        if ( ! get_option( 'wp_attachment_pages_enabled' ) ) {
            $post_types = array_diff( $post_types, array( 'attachment' ) );
        }

        return array_values( $post_types );
    }

    /**
     * Resolve the post types offered when the main query's post_type is
     * empty or `any` (spec §4.5).
     *
     * @param \WP_Query $wp_query The main query.
     * @return string[] Post type slugs.
     */
    private static function get_default_inherited_post_types( \WP_Query $wp_query ): array {
        if ( $wp_query->is_search() ) {
            return get_post_types(
                array(
                    'public'              => true,
                    'exclude_from_search' => false,
                )
            );
        }

        if ( $wp_query->is_tax() || $wp_query->is_category() || $wp_query->is_tag() ) {
            $queried_object  = $wp_query->get_queried_object();
            $taxonomy        = is_object( $queried_object ) ? ( $queried_object->taxonomy ?? '' ) : '';
            $taxonomy_object = $taxonomy ? get_taxonomy( $taxonomy ) : false;

            if ( ! $taxonomy_object ) {
                return array();
            }

            return array_values( array_filter( (array) $taxonomy_object->object_type, 'is_post_type_viewable' ) );
        }

        return array( 'post' );
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
                        'value' => $item->user_nicename,
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
