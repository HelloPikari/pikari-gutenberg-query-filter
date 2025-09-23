<?php
/**
 * Query Loop Handler for modifying Query Loop block queries.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles Query Loop block query modifications based on URL parameters.
 */
class QueryLoopHandler {

    /**
     * Initialize the query loop handler.
     */
    public function __construct() {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     */
    private function register_hooks(): void {
        // Modify Query Loop block queries based on URL parameters.
        add_filter( 'query_loop_block_query_vars', array( $this, 'modify_query' ), 19, 3 );
    }

    /**
     * Modify query based on URL parameters.
     *
     * @param array    $query_args The query arguments.
     * @param WP_Block $block      The block instance.
     * @param int      $page       The current page number.
     * @return array Modified query arguments.
     */
    public function modify_query( array $query_args, \WP_Block $block, int $page ): array {
        // Get query configuration.
        $inherit_query = $block->context['query']['inherit'] ?? false;
        $query_id      = $inherit_query ? null : ( $block->context['queryId'] ?? 0 );

        // Parse URL parameters for this query.
        $parameters = $this->parse_query_parameters( $query_id, $inherit_query );

        // Apply post type filtering.
        if ( ! empty( $parameters['post_type'] ) ) {
            $query_args['post_type'] = $parameters['post_type'];

            // When filtering by custom post type, ensure sticky posts don't interfere.
            // Sticky posts can cause WordPress to add extra posts of different types.
            if ( ! isset( $query_args['ignore_sticky_posts'] ) ) {
                $query_args['ignore_sticky_posts'] = true;
            }

            // Clear any post__in that might be limiting to specific posts.
            if ( isset( $query_args['post__in'] ) ) {
                unset( $query_args['post__in'] );
            }
        }

        // Apply taxonomy filtering.
        if ( ! empty( $parameters['tax_query'] ) ) {
            $existing_tax_query = $query_args['tax_query'] ?? array();

            // If there's an existing tax_query, merge properly with AND relation.
            if ( ! empty( $existing_tax_query ) ) {
                // Ensure both queries use AND relation for cumulative filtering.
                $existing_tax_query['relation'] = 'AND';
                $parameters['tax_query']['relation'] = 'AND';

                // Merge the two tax queries.
                $query_args['tax_query'] = array(
                    'relation' => 'AND',
                    $existing_tax_query,
                    $parameters['tax_query'],
                );
            } else {
                // No existing tax_query, just use the new one.
                $query_args['tax_query'] = $parameters['tax_query'];
            }
        }

        // Apply author filtering.
        if ( ! empty( $parameters['author__in'] ) ) {
            $query_args['author__in'] = $parameters['author__in'];
        }

        // Apply search filtering.
        if ( ! empty( $parameters['s'] ) ) {
            $query_args['s'] = $parameters['s'];
        }

        // Apply sorting.
        if ( ! empty( $parameters['orderby'] ) ) {
            $query_args['orderby'] = $parameters['orderby'];
        }
        if ( ! empty( $parameters['order'] ) ) {
            $query_args['order'] = $parameters['order'];
        }

        // Apply default sorting if no sort parameters are present.
        if ( $inherit_query ) {
            $orderby_param = 'query-orderby';
            $order_param   = 'query-order';
        } else {
            // Validate query_id is numeric for sprintf safety.
            $query_id = absint( $query_id );
            $orderby_param = sprintf( 'query-%d-orderby', $query_id );
            $order_param   = sprintf( 'query-%d-order', $query_id );
        }
        $has_sort_params = isset( $_GET[ $orderby_param ] ) || isset( $_GET[ $order_param ] );

        if ( ! $has_sort_params ) {
            $query_args['orderby'] = 'date';
            $query_args['order']   = 'DESC';
        }

        return $query_args;
    }

    /**
     * Parse URL parameters for a specific query.
     *
     * @param int|null $query_id      The query ID (null for inherited queries).
     * @param bool     $inherit_query Whether the query inherits from template.
     * @return array Parsed parameters array.
     */
    private function parse_query_parameters( $query_id, $inherit_query ): array {
        $parameters = array();

        // Generate parameter prefixes based on query type.
        if ( $inherit_query ) {
            $prefix = 'query-';
        } else {
            // Validate query_id is numeric for sprintf safety.
            $query_id = absint( $query_id );
            $prefix = sprintf( 'query-%d-', $query_id );
        }

        // Parse post type parameter.
        $post_type_param = $prefix . 'post_type';
        if ( isset( $_GET[ $post_type_param ] ) ) {
            $post_types = explode( ',', sanitize_text_field( wp_unslash( $_GET[ $post_type_param ] ) ) );
            $post_types = array_filter( array_map( 'trim', $post_types ) );

            // Validate that post types exist and are viewable.
            $valid_post_types = array();
            foreach ( $post_types as $post_type ) {
                if ( post_type_exists( $post_type ) && is_post_type_viewable( $post_type ) ) {
                    $valid_post_types[] = $post_type;
                }
            }

            if ( ! empty( $valid_post_types ) ) {
                $parameters['post_type'] = count( $valid_post_types ) === 1 ? $valid_post_types[0] : $valid_post_types;
            }
        }

        // Parse taxonomy parameters.
        $tax_query = $this->parse_taxonomy_parameters( $prefix );
        if ( ! empty( $tax_query ) ) {
            $parameters['tax_query'] = $tax_query;
        }

        // Parse author parameter.
        $author_param = $prefix . 'author';
        if ( isset( $_GET[ $author_param ] ) ) {
            $authors = explode( ',', sanitize_text_field( wp_unslash( $_GET[ $author_param ] ) ) );
            $authors = array_filter( array_map( 'intval', $authors ) );
            if ( ! empty( $authors ) ) {
                $parameters['author__in'] = $authors;
            }
        }

        // Parse search parameter.
        if ( $inherit_query ) {
            $search_param = 's';
        } else {
            // Validate query_id is numeric for sprintf safety.
            $query_id = absint( $query_id );
            $search_param = sprintf( 'query-%d-s', $query_id );
        }
        if ( isset( $_GET[ $search_param ] ) ) {
            $search = sanitize_text_field( wp_unslash( $_GET[ $search_param ] ) );
            if ( ! empty( $search ) ) {
                $parameters['s'] = $search;
            }
        }

        // Parse sort parameters.
        $orderby_param = $prefix . 'orderby';
        $order_param   = $prefix . 'order';
        if ( isset( $_GET[ $orderby_param ] ) ) {
            $orderby = sanitize_text_field( wp_unslash( $_GET[ $orderby_param ] ) );
            if ( ! empty( $orderby ) ) {
                $parameters['orderby'] = $orderby;
            }
        }
        if ( isset( $_GET[ $order_param ] ) ) {
            $order = strtoupper( sanitize_text_field( wp_unslash( $_GET[ $order_param ] ) ) );
            if ( in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
                $parameters['order'] = $order;
            }
        }

        return $parameters;
    }

    /**
     * Parse taxonomy-related URL parameters.
     *
     * @param string $prefix The parameter prefix (e.g., 'query-1-' or 'query-').
     * @return array Array of taxonomy query clauses.
     */
    private function parse_taxonomy_parameters( $prefix ): array {
        $tax_query = array();

        // Get all taxonomies to check for parameters.
        $taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

        foreach ( $taxonomies as $taxonomy ) {
            $param_name = $prefix . $taxonomy;
            if ( isset( $_GET[ $param_name ] ) ) {
                $term_slugs = explode( ',', sanitize_text_field( wp_unslash( $_GET[ $param_name ] ) ) );
                $term_slugs = array_filter( array_map( 'trim', $term_slugs ) );

                if ( ! empty( $term_slugs ) ) {
                    // Validate taxonomy exists before adding to query.
                    if ( taxonomy_exists( $taxonomy ) ) {
                        $tax_query[] = array(
                            'taxonomy' => $taxonomy,
                            'field'    => 'slug',
                            'terms'    => $term_slugs,
                            'operator' => 'IN',
                        );
                    }
                }
            }
        }

        // Set relation to AND if multiple taxonomies are filtered.
        // This creates cumulative filtering where posts must match all selected taxonomies.
        if ( count( $tax_query ) > 1 ) {
            $tax_query['relation'] = 'AND';
        }

        return $tax_query;
    }
}
