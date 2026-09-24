<?php
/**
 * Each Query Loop's total result count, for the result announcement.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Query;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Records custom loops' totals as core runs their queries.
 *
 * QueryLoopHandler tags each custom loop's query args with QUERY_VAR, set to
 * the loop's form id. Core builds the loop's WP_Query from those args, so the
 * the_posts filter can tell which loop a total belongs to. The loop form is
 * injected after the loop renders, by which time the total is known (spec C/D §3.2).
 *
 * Hooked to the_posts, not found_posts: WP_Query::set_found_posts() returns
 * before running the found_posts filter whenever $this->posts is an empty
 * array, so a zero-result loop would never record. A cache hit also sets
 * found_posts straight from the object cache without running that filter.
 * the_posts runs on both paths, after found_posts is final.
 */
class ResultCount {

    /**
     * Private query var carrying a custom loop's form id. Internal: not part of
     * the public contract.
     */
    public const QUERY_VAR = 'pikari_gutenberg_query_filter_loop';

    /**
     * Totals by form id, for this request.
     *
     * @var array<string, int>
     */
    private static array $counts = array();

    /**
     * Record a tagged query's total. Hooked to the_posts.
     *
     * Core runs a loop's query several times per page, every run reporting the
     * same total, so the last write wins. A no_found_rows query never computes
     * found_posts, so it's skipped rather than wrongly recorded as 0.
     *
     * @param mixed $posts The retrieved posts, unused beyond passing through.
     * @param mixed $query The WP_Query.
     * @return mixed The posts, unchanged.
     */
    public static function record( $posts, $query ) {
        if ( $query instanceof \WP_Query ) {
            if ( $query->get( 'no_found_rows' ) ) {
                return $posts;
            }

            $form_id = $query->get( self::QUERY_VAR );

            if ( is_string( $form_id ) && '' !== $form_id ) {
                self::$counts[ $form_id ] = (int) $query->found_posts;
            }
        }

        return $posts;
    }

    /**
     * A custom loop's total, if one was recorded this request.
     *
     * @param string $form_id The loop's form id.
     * @return int|null Total, or null when unknown.
     */
    public static function for_form( string $form_id ): ?int {
        return self::$counts[ $form_id ] ?? null;
    }

    /**
     * The sentence a screen reader hears.
     *
     * @param int $count Total results.
     * @return string Translated message.
     */
    public static function message( int $count ): string {
        if ( 0 === $count ) {
            return __( 'No results found', 'pikari-gutenberg-query-filter' );
        }

        return sprintf(
            /* translators: %s: number of results. */
            _n( '%s result found', '%s results found', $count, 'pikari-gutenberg-query-filter' ),
            number_format_i18n( $count )
        );
    }

    /**
     * Forget every total. For tests.
     */
    public static function reset(): void {
        self::$counts = array();
    }
}
