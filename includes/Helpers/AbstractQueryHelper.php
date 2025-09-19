<?php
/**
 * Abstract base class for query helper functionality.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Helpers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Abstract base class providing common query helper functionality.
 */
abstract class AbstractQueryHelper {


    /**
     * Get query variables for a given block context.
     *
     * @param array  $block       Block instance.
     * @param string $filter_type Type of filter (for query variable naming).
     * @return array {
     *     Query configuration array.
     *     @type string $query_var Query variable name.
     *     @type string $page_var  Page variable name.
     * }
     */
    protected static function get_query_config( $block, $filter_type ) {
        if ( $block->context['query']['inherit'] ) {
            $query_var = sprintf( 'query-%s', $filter_type );
            $page_var  = 'page';
        } else {
            $query_id  = $block->context['queryId'] ?? 0;
            $query_var = sprintf( 'query-%d-%s', $query_id, $filter_type );
            $page_var  = isset( $block->context['queryId'] ) ? 'query-' . $block->context['queryId'] . '-page' : 'query-page';
        }

        return array(
            'query_var' => $query_var,
            'page_var'  => $page_var,
        );
    }


    /**
     * Get query config for taxonomy filter with custom taxonomy parameter.
     *
     * @param array  $block    Block instance.
     * @param string $taxonomy Taxonomy name.
     * @return array Query configuration array.
     */
    protected static function get_taxonomy_query_config( $block, $taxonomy ) {
        if ( empty( $block->context['query']['inherit'] ) ) {
            $query_id  = $block->context['queryId'] ?? 0;
            $query_var = sprintf( 'query-%d-%s', $query_id, $taxonomy );
            $page_var  = isset( $block->context['queryId'] ) ? 'query-' . $block->context['queryId'] . '-page' : 'query-page';
        } else {
            $query_var = sprintf( 'query-%s', $taxonomy );
            $page_var  = 'page';
        }

        return array(
            'query_var' => $query_var,
            'page_var'  => $page_var,
        );
    }

    /**
     * Get query config for sort functionality with orderby and order parameters.
     *
     * @param array $block Block instance.
     * @return array Query configuration array.
     */
    protected static function get_sort_query_config( $block ) {
        if ( $block->context['query']['inherit'] ) {
            $orderby_var = 'query-orderby';
            $order_var   = 'query-order';
            $page_var    = 'page';
        } else {
            $query_id    = $block->context['queryId'] ?? 0;
            $orderby_var = sprintf( 'query-%d-orderby', $query_id );
            $order_var   = sprintf( 'query-%d-order', $query_id );
            $page_var    = isset( $block->context['queryId'] ) ? 'query-' . $block->context['queryId'] . '-page' : 'query-page';
        }

        return array(
            'orderby_var' => $orderby_var,
            'order_var'   => $order_var,
            'page_var'    => $page_var,
        );
    }
}
