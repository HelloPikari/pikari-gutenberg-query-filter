<?php
/**
 * Author helper functions for query author filter blocks.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Helpers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper class for query author filter functionality.
 */
class AuthorHelper extends AbstractQueryHelper {

    /**
     * Cache key prefix for author data.
     */
    const CACHE_KEY = 'pikari_query_filter_authors';

    /**
     * Cache expiry time in seconds (1 hour).
     */
    const CACHE_EXPIRY = 3600;

    /**
     * Get author filter configuration.
     *
     * @param array $block Block instance.
     * @return array {
     *     Query configuration array.
     *     @type string $query_var Query variable name.
     *     @type string $page_var  Page variable name.
     *     @type string $base_url  Base URL without query parameters.
     * }
     */
    public static function get_author_filter_config( $block ) {
        return static::get_query_config( $block, 'author' );
    }

    /**
     * Get authors for filter with caching.
     *
     * @param array $args Arguments for get_users().
     * @return array Array of WP_User objects.
     */
    public static function get_filter_authors( $args = array() ) {
        // Set default arguments
        $defaults = array(
            'can'                   => 'authors',
            'orderby'               => 'display_name',
            'order'                 => 'ASC',
            'has_published_posts'   => true,
            'number'                => 100,
        );
        $args = wp_parse_args( $args, $defaults );

        // Create cache key based on args
        $cache_key = self::CACHE_KEY . '_' . md5( serialize( $args ) );

        // Try to get from cache first
        $authors = get_transient( $cache_key );

        if ( false === $authors ) {
            // Cache miss - fetch from database
            $authors = get_users( $args );

            // Filter out authors with no published posts if specified
            if ( $args['has_published_posts'] ) {
                $authors = array_filter( $authors, array( self::class, 'author_has_published_posts' ) );
            }

            // Cache the results
            set_transient( $cache_key, $authors, self::CACHE_EXPIRY );
        }

        return $authors;
    }

    /**
     * Check if author has published posts.
     *
     * @param WP_User $author Author user object.
     * @return bool True if author has published posts.
     */
    private static function author_has_published_posts( $author ) {
        $post_count = count_user_posts( $author->ID, 'post', true );
        return $post_count > 0;
    }

    /**
     * Invalidate all author cache.
     */
    public static function invalidate_author_cache() {
        global $wpdb;

        // Delete all transients that start with our cache key
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_KEY . '_%'
            )
        );

        // Also delete timeout transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . self::CACHE_KEY . '_%'
            )
        );
    }

    /**
     * Invalidate specific author cache entry.
     *
     * @param array $args Arguments used to generate the cache key.
     */
    public static function invalidate_specific_author_cache( $args = array() ) {
        $defaults = array(
            'who'                   => 'authors',
            'orderby'               => 'display_name',
            'order'                 => 'ASC',
            'has_published_posts'   => true,
            'number'                => 100,
        );
        $args = wp_parse_args( $args, $defaults );

        $cache_key = self::CACHE_KEY . '_' . md5( serialize( $args ) );
        delete_transient( $cache_key );
    }

    /**
     * Get authors with post counts (for future features).
     *
     * @param array $args Arguments for get_users().
     * @return array Array of user objects with post_count property.
     */
    public static function get_authors_with_post_count( $args = array() ) {
        $authors = self::get_filter_authors( $args );

        foreach ( $authors as $author ) {
            $author->post_count = count_user_posts( $author->ID, 'post', true );
        }

        return $authors;
    }
}
