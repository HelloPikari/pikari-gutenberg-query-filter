<?php
/**
 * Query Loop Handler for modifying Query Loop block queries.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Core;

use Pikari\GutenbergQueryFilter\Query\QueryArgs;
use Pikari\GutenbergQueryFilter\Query\ResultCount;
use Pikari\GutenbergQueryFilter\Url\FilterState;
use Pikari\GutenbergQueryFilter\Url\QueryParams;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles Query Loop block query modifications based on URL parameters.
 *
 * A thin adapter over Url\QueryParams, Url\FilterState and Query\QueryArgs;
 * see the 1.0 spec, section 4.1.
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

        // Record each tagged loop's total for the result announcement. Hooked
        // to the_posts, not found_posts: found_posts is skipped for a
        // zero-result query and for a query-cache hit (see ResultCount).
        add_filter( 'the_posts', array( ResultCount::class, 'record' ), 10, 2 );
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
        unset( $page );

        $params = QueryParams::from_block( $block );

        if ( $params->is_inherit() ) {
            // Core never applies this filter to inherited loops; B2 handles them
            // through pre_get_posts (spec §4.4).
            return $query_args;
        }

        $query_args = QueryArgs::apply( $query_args, FilterState::for_loop( $params ) );

        // Tag the query, so ResultCount can tell which loop a total is for.
        $query_args[ ResultCount::QUERY_VAR ] = $params->form_id();

        return $query_args;
    }
}
