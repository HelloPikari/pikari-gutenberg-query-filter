<?php
/**
 * Applies filter parameters to the main query, for inherited Query Loops.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Integrations;

use Pikari\GutenbergQueryFilter\Query\TaxonomySubquery;
use Pikari\GutenbergQueryFilter\Url\FilterState;
use Pikari\GutenbergQueryFilter\Url\QueryParams;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filters the main query of home, archive and search requests, since core
 * never applies query_loop_block_query_vars to a Query Loop that inherits
 * the main query (1.0 spec, section 4.4).
 *
 * The gate in filter_main_query() is the security-relevant part of this
 * class: without it, a stray ?query-post_type=post on any singular request
 * would add conditions to that page's own query, find nothing, and turn a
 * normal page into a 404 that a page cache could store.
 */
class MainQueryFilter {

    /**
     * The main query's post_type, orderby and order, recorded before any
     * set() call. Read by Tasks 3 and 4 to build option lists and defaults.
     *
     * @var array<string, mixed>
     */
    private static array $original = array();

    /**
     * Whether this request's main query had filters applied.
     *
     * @var bool
     */
    private static bool $applied = false;

    /**
     * Initialize the main query filter.
     */
    public function __construct() {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     */
    private function register_hooks(): void {
        add_action( 'pre_get_posts', array( $this, 'filter_main_query' ), 10 );
        add_filter( 'pre_handle_404', array( $this, 'skip_404_for_filtered_archives' ), 10, 2 );
    }

    /**
     * Apply this request's filter parameters to the main query.
     *
     * Spec section 4.4. Returns immediately unless every gate condition
     * holds, so a stray inherited-style parameter can never reach a
     * singular request, a 404, a feed, or the admin.
     *
     * @param \WP_Query $query The query being filtered.
     */
    public function filter_main_query( \WP_Query $query ): void {
        if ( ! $this->passes_gate( $query ) ) {
            return;
        }

        if ( ! $this->has_inherited_parameter() ) {
            return;
        }

        // Record before any set() call below: Tasks 3 and 4 build option
        // lists from the loop's own values, and a set() first would collapse
        // them to whatever the visitor already chose.
        self::$original = array(
            'post_type' => $query->get( 'post_type' ),
            'orderby'   => $query->get( 'orderby' ),
            'order'     => $query->get( 'order' ),
        );

        $params = new QueryParams( null, true );
        $state  = FilterState::for_loop( $params );

        if ( ! $state->has_filters() ) {
            return;
        }

        $this->apply_post_types( $query, $state );
        $this->apply_authors( $query, $state );
        $this->apply_sort( $query, $state );

        // Core prepends sticky posts on page 1 without applying tax, author
        // or search conditions (spec section 1, section 4.4).
        $query->set( 'ignore_sticky_posts', true );

        $this->apply_taxonomies( $query, $state );

        self::$applied = true;
    }

    /**
     * Whether the main query is eligible for filtering at all (spec section 4.4).
     *
     * REST requests never reach the main query, so there is no separate
     * check for them here.
     *
     * @param \WP_Query $query The query being filtered.
     * @return bool
     */
    private function passes_gate( \WP_Query $query ): bool {
        if ( ! $query->is_main_query() ) {
            return false;
        }

        if ( is_admin() ) {
            return false;
        }

        if ( $query->is_feed() ) {
            return false;
        }

        if ( $query->is_singular() ) {
            return false;
        }

        if ( $query->is_404() ) {
            return false;
        }

        return $query->is_home() || $query->is_archive() || $query->is_search();
    }

    /**
     * Whether any $_GET key looks like an inherited-loop filter parameter.
     *
     * `query-page` is core's own page key for a loop with no queryId, not a
     * filter, so it is explicitly excluded, along with a custom loop's
     * numbered keys (`query-3-post_type`). Checked before anything else
     * expensive runs (spec section 4.4).
     *
     * @return bool
     */
    private function has_inherited_parameter(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking key shapes; no value is read or used here.
        foreach ( array_keys( $_GET ) as $key ) {
            if ( preg_match( '/^query-(?!\d+-|page$)/', (string) $key ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set post_type from the filter state, unless the request is already a
     * post type archive (spec section 3.4).
     *
     * @param \WP_Query   $query The query being filtered.
     * @param FilterState $state Parsed and validated filter values.
     */
    private function apply_post_types( \WP_Query $query, FilterState $state ): void {
        if ( $query->is_post_type_archive() ) {
            return;
        }

        $post_types = $state->post_types();

        if ( empty( $post_types ) ) {
            return;
        }

        $query->set( 'post_type', 1 === count( $post_types ) ? $post_types[0] : $post_types );
    }

    /**
     * Set author__in from the filter state, intersected with the archive's
     * own author on an author archive (spec section 3.4, section 4.4).
     *
     * Core adds the archive's author var to author__in on plain permalinks
     * (class-wp-query.php:2398-2408), so the intersection is computed here
     * first. A filter that doesn't include the archive's author resolves to
     * array( 0 ), matching no posts.
     *
     * @param \WP_Query   $query The query being filtered.
     * @param FilterState $state Parsed and validated filter values.
     */
    private function apply_authors( \WP_Query $query, FilterState $state ): void {
        $author_ids = $state->author_ids();

        if ( null === $author_ids ) {
            return;
        }

        if ( $query->is_author() ) {
            $archive_author_id = $this->archive_author_id( $query );
            $author_ids        = array_values( array_intersect( $author_ids, array( $archive_author_id ) ) );

            if ( empty( $author_ids ) ) {
                $author_ids = array( 0 );
            }
        }

        $query->set( 'author__in', $author_ids );
    }

    /**
     * Resolve an author archive's own author ID.
     *
     * A plain permalink (?author=7) already has the numeric ID in the
     * author query var by pre_get_posts time. A pretty permalink
     * (/author/jane-doe/) only sets author_name: core doesn't resolve it to
     * a numeric ID until deep inside get_posts()'s WHERE-clause building
     * (class-wp-query.php), well after pre_get_posts has already run, so it
     * has to be resolved here instead.
     *
     * @param \WP_Query $query The query being filtered.
     * @return int The archive's author ID, or 0 when it can't be resolved.
     */
    private function archive_author_id( \WP_Query $query ): int {
        $author_id = absint( $query->get( 'author' ) );

        if ( $author_id > 0 ) {
            return $author_id;
        }

        $author_name = (string) $query->get( 'author_name' );

        if ( '' === $author_name ) {
            return 0;
        }

        $user = get_user_by( 'slug', $author_name );

        return $user ? absint( $user->ID ) : 0;
    }

    /**
     * Set orderby, order and meta_key from the resolved sort option.
     *
     * @param \WP_Query   $query The query being filtered.
     * @param FilterState $state Parsed and validated filter values.
     */
    private function apply_sort( \WP_Query $query, FilterState $state ): void {
        $sort = $state->sort();

        if ( null === $sort ) {
            return;
        }

        $query->set( 'orderby', $sort['orderby'] );
        $query->set( 'order', $sort['order'] );

        if ( ! empty( $sort['meta_key'] ) ) {
            $query->set( 'meta_key', $sort['meta_key'] );
        }
    }

    /**
     * Filter taxonomies without touching the tax_query query var (spec
     * section 4.4).
     *
     * Core reads tax_query to decide what the page *is*: its title, its
     * template and get_queried_object() all follow the first terms found per
     * taxonomy. Setting it here would hijack all three, and an unknown slug
     * would 404 a valid archive — the reason TaxonomySubquery exists at all.
     * Instead, a posts_where callback bound to this query object appends the
     * subquery fragment and removes itself once it has run.
     *
     * @param \WP_Query   $query The query being filtered.
     * @param FilterState $state Parsed and validated filter values.
     */
    private function apply_taxonomies( \WP_Query $query, FilterState $state ): void {
        $taxonomies = $state->taxonomies();

        if ( empty( $taxonomies ) ) {
            return;
        }

        $callback = null;
        $callback = function ( $where, $wp_query ) use ( $query, $taxonomies, &$callback ) {
            if ( $wp_query !== $query ) {
                return $where;
            }

            remove_filter( 'posts_where', $callback, 10 );

            return $where . TaxonomySubquery::where( $taxonomies );
        };

        add_filter( 'posts_where', $callback, 10, 2 );
    }

    /**
     * Keep a filtered date archive from 404ing when the filter empties it.
     *
     * Core already skips the 404 check for zero-result home, search, tax,
     * category, tag, post type and author archives whose queried object
     * resolves (class-wp.php:783-795). Date archives are not on that exempt
     * list, so a filter that empties one would otherwise turn a valid
     * archive into a 404. A page past the end of a filtered date archive
     * still 404s, which is why pagination is stripped on every filter
     * change (spec section 4.4).
     *
     * @param bool      $pre   Whether to short-circuit the 404 check.
     * @param \WP_Query $query The query being checked.
     * @return bool
     */
    public function skip_404_for_filtered_archives( $pre, \WP_Query $query ): bool {
        if ( self::applied() && $query->is_date() && ! $query->is_paged() ) {
            return true;
        }

        return $pre;
    }

    /**
     * Get a main-query value recorded before filtering.
     *
     * @param string $var `post_type`, `orderby` or `order`.
     * @return mixed The recorded value, or null when nothing was recorded.
     */
    public static function original( string $var ) {
        return self::$original[ $var ] ?? null;
    }

    /**
     * Whether this request's main query had filters applied.
     *
     * @return bool
     */
    public static function applied(): bool {
        return self::$applied;
    }

    /**
     * Reset recorded state. Exists for tests; production code never calls this.
     */
    public static function reset_for_tests(): void {
        self::$original = array();
        self::$applied  = false;
    }
}
