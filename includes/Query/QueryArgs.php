<?php
/**
 * Merges a Query Loop's filter state into its WP_Query arguments.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Query;

use Pikari\GutenbergQueryFilter\Url\FilterState;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure merge of a custom loop's FilterState into its WP_Query arguments.
 *
 * Every rule here matches the 1.0 spec, section 4.2. No WordPress calls
 * belong here beyond what FilterState has already resolved.
 */
class QueryArgs {

    /**
     * Merge filter state into a custom loop's query arguments.
     *
     * @param array       $args  Query arguments core built for the loop.
     * @param FilterState $state Parsed and validated filter values.
     * @return array Modified query arguments. The given array is left untouched.
     */
    public static function apply( array $args, FilterState $state ): array {
        $args = self::apply_post_types( $args, $state );
        $args = self::apply_taxonomies( $args, $state );
        $args = self::apply_authors( $args, $state );
        $args = self::apply_search( $args, $state );
        $args = self::apply_sort( $args, $state );
        $args = self::apply_sticky_posts( $args, $state );

        return $args;
    }

    /**
     * Override post_type. post__in is left alone, so a loop like sticky
     * "only" keeps working (spec §3.6, §4.2).
     *
     * @param array       $args  Query arguments.
     * @param FilterState $state Filter state.
     * @return array
     */
    private static function apply_post_types( array $args, FilterState $state ): array {
        $post_types = $state->post_types();

        if ( empty( $post_types ) ) {
            return $args;
        }

        $args['post_type'] = 1 === count( $post_types ) ? $post_types[0] : $post_types;

        return $args;
    }

    /**
     * Add one IN clause per filtered taxonomy, joined with AND when there is
     * more than one. An existing tax_query is nested unchanged, keeping its
     * own relation (spec §3.6, §4.2).
     *
     * @param array       $args  Query arguments.
     * @param FilterState $state Filter state.
     * @return array
     */
    private static function apply_taxonomies( array $args, FilterState $state ): array {
        $taxonomies = $state->taxonomies();

        if ( empty( $taxonomies ) ) {
            return $args;
        }

        $filters = array();

        foreach ( $taxonomies as $taxonomy => $slugs ) {
            $filters[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $slugs,
                'operator' => 'IN',
            );
        }

        if ( count( $filters ) > 1 ) {
            $filters['relation'] = 'AND';
        }

        if ( ! empty( $args['tax_query'] ) ) {
            $args['tax_query'] = array(
                'relation' => 'AND',
                $args['tax_query'],
                $filters,
            );
        } else {
            $args['tax_query'] = $filters;
        }

        return $args;
    }

    /**
     * Set author__in from resolved author IDs.
     *
     * @param array       $args  Query arguments.
     * @param FilterState $state Filter state.
     * @return array
     */
    private static function apply_authors( array $args, FilterState $state ): array {
        $author_ids = $state->author_ids();

        if ( null === $author_ids ) {
            return $args;
        }

        $args['author__in'] = $author_ids;

        return $args;
    }

    /**
     * Set s from the sanitized search term.
     *
     * @param array       $args  Query arguments.
     * @param FilterState $state Filter state.
     * @return array
     */
    private static function apply_search( array $args, FilterState $state ): array {
        $search = $state->search();

        if ( '' === $search ) {
            return $args;
        }

        $args['s'] = $search;

        return $args;
    }

    /**
     * Set orderby, order, and meta_key from the resolved sort option.
     *
     * @param array       $args  Query arguments.
     * @param FilterState $state Filter state.
     * @return array
     */
    private static function apply_sort( array $args, FilterState $state ): array {
        $sort = $state->sort();

        if ( null === $sort ) {
            return $args;
        }

        $args['orderby'] = $sort['orderby'];
        $args['order']   = $sort['order'];

        if ( ! empty( $sort['meta_key'] ) ) {
            $args['meta_key'] = $sort['meta_key'];
        }

        return $args;
    }

    /**
     * Ignore sticky posts whenever a filter applies, unless the loop has
     * already decided. Core prepends sticky posts on page 1 without
     * applying tax, author or search conditions (spec §1, §3.6, §4.2).
     *
     * @param array       $args  Query arguments.
     * @param FilterState $state Filter state.
     * @return array
     */
    private static function apply_sticky_posts( array $args, FilterState $state ): array {
        if ( ! $state->has_filters() ) {
            return $args;
        }

        if ( array_key_exists( 'ignore_sticky_posts', $args ) ) {
            return $args;
        }

        $args['ignore_sticky_posts'] = true;

        return $args;
    }
}
