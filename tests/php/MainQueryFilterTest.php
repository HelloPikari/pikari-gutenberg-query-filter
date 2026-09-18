<?php
/**
 * Tests for filtering the main query, for inherited Query Loops.
 *
 * Every rule pinned here matches the 1.0 spec, sections 3.4 and 4.4.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Pikari\GutenbergQueryFilter\Integrations\MainQueryFilter;
use Pikari\GutenbergQueryFilter\Query\TaxonomySubquery;
use Pikari\GutenbergQueryFilter\Url\FilterState;
use Pikari\Tests\TestCase;

class MainQueryFilterTest extends TestCase {

    /**
     * The $wpdb global's value before this test replaced it, restored in
     * tearDown(). TaxonomySubquery::where() needs it for the posts_where
     * callback tests.
     *
     * @var mixed
     */
    private $original_wpdb;

    /**
     * Calls made to WP_Query::set() by the query built in main_query(),
     * captured instead of asserted through Mockery expectations so a test
     * can inspect every call at once.
     *
     * @var array<int, array{0: string, 1: mixed}>
     */
    private array $set_calls = array();

    protected function setUp(): void {
        parent::setUp();

        $_GET = array();
        $this->set_calls = array();
        MainQueryFilter::reset_for_tests();
        FilterState::reset_cache();

        global $wpdb;
        $this->original_wpdb = $wpdb;

        $wpdb                     = Mockery::mock( 'wpdb' );
        $wpdb->posts              = 'wp_posts';
        $wpdb->term_relationships = 'wp_term_relationships';

        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'absint' )->alias(
            function ( $value ) {
                return abs( (int) $value );
            }
        );
        Functions\when( 'is_post_type_viewable' )->justReturn( true );
        Functions\when( 'is_taxonomy_viewable' )->justReturn( true );
        Functions\when( 'is_taxonomy_hierarchical' )->justReturn( false );
        Functions\when( 'sanitize_title_for_query' )->alias(
            function ( $value ) {
                return strtolower( (string) $value );
            }
        );
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'get_current_blog_id' )->justReturn( 1 );
        Functions\when( 'get_users' )->justReturn( array() );
        Functions\when( 'get_user_by' )->justReturn( false );
        Functions\when( 'get_taxonomies' )->justReturn( array( 'category' => 'category' ) );
        Functions\when( 'get_terms' )->justReturn(
            array( (object) array( 'term_id' => 1, 'term_taxonomy_id' => 11 ) )
        );
        Functions\stubTranslationFunctions();
        Functions\when( 'sanitize_key' )->alias(
            function ( $key ) {
                return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
            }
        );
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;

        $_GET = array();
        FilterState::reset_cache();
        MainQueryFilter::reset_for_tests();

        parent::tearDown();
    }

    /**
     * Build a WP_Query mock stubbed with every method filter_main_query()
     * and skip_404_for_filtered_archives() can call, so a test only needs
     * to override the flags it cares about.
     *
     * @param array $flags      Overrides for the boolean is_*() methods.
     * @param array $query_vars Overrides for the values get() returns.
     * @return \WP_Query
     */
    private function main_query( array $flags = array(), array $query_vars = array() ): \WP_Query {
        $flags = array_merge(
            array(
                'is_main_query'        => true,
                'is_feed'              => false,
                'is_singular'          => false,
                'is_404'               => false,
                'is_home'              => true,
                'is_archive'           => false,
                'is_search'            => false,
                'is_post_type_archive' => false,
                'is_author'            => false,
                'is_date'              => false,
                'is_paged'             => false,
            ),
            $flags
        );

        $query_vars = array_merge(
            array(
                'post_type' => '',
                'orderby'   => '',
                'order'     => '',
                'author'    => 0,
            ),
            $query_vars
        );

        $query = Mockery::mock( 'WP_Query' );

        foreach ( $flags as $method => $value ) {
            $query->shouldReceive( $method )->andReturn( $value );
        }

        // get() reads from the same array set() writes to, so a test can
        // tell whether a value was recorded before or after it changed
        // (see test_the_unfiltered_post_type_is_recorded_before_filtering).
        $query->shouldReceive( 'get' )->andReturnUsing(
            function ( $var ) use ( &$query_vars ) {
                return $query_vars[ $var ] ?? '';
            }
        );

        $query->shouldReceive( 'set' )->andReturnUsing(
            function ( $var, $value ) use ( &$query_vars ) {
                $query_vars[ $var ] = $value;
                $this->set_calls[]  = array( $var, $value );
            }
        );

        return $query;
    }

    /**
     * Whether set() was called with this variable name, regardless of value.
     *
     * @param string $var Query var name.
     * @return bool
     */
    private function was_set( string $var ): bool {
        foreach ( $this->set_calls as $call ) {
            if ( $var === $call[0] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The value set() was called with for this variable name.
     *
     * @param string $var Query var name.
     * @return mixed
     */
    private function value_set( string $var ) {
        foreach ( $this->set_calls as $call ) {
            if ( $var === $call[0] ) {
                return $call[1];
            }
        }

        return null;
    }

    /**
     * Run filter_main_query() while capturing the posts_where closure it
     * registers, so a test can invoke it directly.
     *
     * Brain\Monkey's apply_filters() does not dispatch to real add_filter()
     * callbacks (it only tracks registration for has_filter()/expectAdded()),
     * so the callback itself has to be captured and called by hand.
     *
     * @param \WP_Query $query The query filter_main_query() is run against.
     * @return callable The registered posts_where callback.
     */
    private function capture_posts_where_callback( \WP_Query $query ): callable {
        $captured = null;

        Filters\expectAdded( 'posts_where' )->once()->whenHappen(
            function ( $callback ) use ( &$captured ) {
                $captured = $callback;
            }
        );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertIsCallable( $captured, 'filter_main_query() did not register a posts_where callback.' );

        return $captured;
    }

    /*
     * Hook registration.
     */

    public function test_it_registers_pre_get_posts_at_priority_10(): void {
        Actions\expectAdded( 'pre_get_posts' )
            ->once()
            ->with( Mockery::type( 'array' ), 10 );

        new MainQueryFilter();
        $this->addToAssertionCount( 1 );
    }

    /**
     * The 2-arg signature matters: without it, $query is never passed and
     * the \WP_Query type hint on skip_404_for_filtered_archives() fatals.
     */
    public function test_it_registers_pre_handle_404_with_2_args(): void {
        Filters\expectAdded( 'pre_handle_404' )
            ->once()
            ->with( Mockery::type( 'array' ), 10, 2 );

        new MainQueryFilter();
        $this->addToAssertionCount( 1 );
    }

    /*
     * The gate (spec section 4.4).
     */

    public function test_it_does_not_run_on_a_secondary_query(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query( array( 'is_main_query' => false ) );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array(), $this->set_calls );
    }

    public function test_it_does_not_run_in_the_admin(): void {
        Functions\when( 'is_admin' )->justReturn( true );

        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array(), $this->set_calls );
    }

    public function test_it_does_not_run_on_a_feed(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query( array( 'is_feed' => true ) );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array(), $this->set_calls );
    }

    public function test_it_does_not_run_on_a_singular_request(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query( array( 'is_home' => false, 'is_singular' => true ) );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array(), $this->set_calls );
    }

    public function test_it_does_not_run_on_a_404(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query( array( 'is_home' => false, 'is_404' => true ) );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array(), $this->set_calls );
    }

    public function test_it_does_not_run_without_an_inherited_style_parameter(): void {
        $_GET  = array();
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array(), $this->set_calls );
    }

    /**
     * query-page is core's own page key for a loop with no queryId, not a
     * filter parameter, so it must not trigger the gate on its own.
     */
    public function test_query_page_alone_does_not_trigger_it(): void {
        $_GET  = array( 'query-page' => '2' );
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array(), $this->set_calls );
    }

    /*
     * The gate lets these request types through.
     */

    public function test_it_runs_on_a_home_request(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query( array( 'is_home' => true ) );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertTrue( $this->was_set( 'ignore_sticky_posts' ) );
        $this->assertNotFalse( has_filter( 'posts_where' ) );
    }

    public function test_it_runs_on_an_archive_request(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query( array( 'is_home' => false, 'is_archive' => true ) );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertTrue( $this->was_set( 'ignore_sticky_posts' ) );
        $this->assertNotFalse( has_filter( 'posts_where' ) );
    }

    public function test_it_runs_on_a_search_request(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query( array( 'is_home' => false, 'is_search' => true ) );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertTrue( $this->was_set( 'ignore_sticky_posts' ) );
        $this->assertNotFalse( has_filter( 'posts_where' ) );
    }

    /*
     * What it sets.
     */

    public function test_post_types_are_set(): void {
        $_GET  = array( 'query-post_type' => 'page' );
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( 'page', $this->value_set( 'post_type' ) );
    }

    /**
     * Spec section 3.4: on a post type archive, query-post_type is ignored,
     * but a taxonomy filter still applies.
     */
    public function test_post_types_are_ignored_on_a_post_type_archive(): void {
        $_GET  = array(
            'query-post_type' => 'page',
            'query-category'  => 'news',
        );
        $query = $this->main_query( array( 'is_post_type_archive' => true ) );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertFalse( $this->was_set( 'post_type' ) );
        $this->assertNotFalse( has_filter( 'posts_where' ) );
    }

    public function test_authors_are_set(): void {
        Functions\when( 'get_users' )->justReturn(
            array( (object) array( 'ID' => 9, 'user_nicename' => 'sam' ) )
        );

        $_GET  = array( 'query-author' => 'sam' );
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array( 9 ), $this->value_set( 'author__in' ) );
    }

    /**
     * Spec section 3.4: on an author archive, the author filter is
     * intersected with the archive's own author.
     *
     * A pretty permalink (/author/jane-doe/) only sets author_name; core
     * doesn't resolve it to a numeric author ID until deep inside
     * get_posts()'s WHERE-clause building, well after pre_get_posts has
     * already run (class-wp-query.php), so the archive's own author has to
     * be resolved from author_name here instead of from the author var.
     */
    public function test_author_archives_intersect(): void {
        Functions\when( 'get_users' )->justReturn(
            array( (object) array( 'ID' => 9, 'user_nicename' => 'sam' ) )
        );
        Functions\when( 'get_user_by' )->justReturn(
            (object) array( 'ID' => 7, 'user_nicename' => 'jane-doe' )
        );

        $_GET  = array( 'query-author' => 'sam' );
        $query = $this->main_query(
            array( 'is_author' => true ),
            array( 'author_name' => 'jane-doe' )
        );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array( 0 ), $this->value_set( 'author__in' ) );
    }

    public function test_author_archives_intersect_when_the_archive_author_is_named(): void {
        Functions\when( 'get_users' )->justReturn(
            array(
                (object) array( 'ID' => 7, 'user_nicename' => 'jane-doe' ),
                (object) array( 'ID' => 9, 'user_nicename' => 'sam' ),
            )
        );
        Functions\when( 'get_user_by' )->justReturn(
            (object) array( 'ID' => 7, 'user_nicename' => 'jane-doe' )
        );

        $_GET  = array( 'query-author' => 'jane-doe,sam' );
        $query = $this->main_query(
            array( 'is_author' => true ),
            array( 'author_name' => 'jane-doe' )
        );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array( 7 ), $this->value_set( 'author__in' ) );
    }

    /**
     * A plain permalink (?author=7) sets the numeric author var directly, so
     * no author_name lookup is needed. Naming the archive's own author (7)
     * in the filter is what distinguishes "the numeric path resolved 7" from
     * "it fell through to 0 either way": if the code wrongly used
     * author_name (which is empty here) instead, get_user_by()'s stubbed
     * default of false would resolve to 0, and the result would be
     * array( 0 ), not array( 7 ).
     */
    public function test_author_archives_intersect_on_a_plain_permalink(): void {
        Functions\when( 'get_users' )->justReturn(
            array(
                (object) array( 'ID' => 7, 'user_nicename' => 'jane-doe' ),
                (object) array( 'ID' => 9, 'user_nicename' => 'sam' ),
            )
        );

        $_GET  = array( 'query-author' => 'jane-doe,sam' );
        $query = $this->main_query(
            array( 'is_author' => true ),
            array( 'author' => 7 )
        );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( array( 7 ), $this->value_set( 'author__in' ) );
    }

    public function test_sort_sets_orderby_and_order(): void {
        $_GET  = array( 'query-sort' => 'title-asc' );
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( 'title', $this->value_set( 'orderby' ) );
        $this->assertSame( 'ASC', $this->value_set( 'order' ) );
    }

    public function test_a_meta_sort_also_sets_meta_key(): void {
        Functions\when( 'apply_filters' )->alias(
            function ( $hook, $options ) {
                if ( 'pikari_gutenberg_query_filter_sort_options' === $hook ) {
                    $options[] = array(
                        'key'      => 'featured-desc',
                        'label'    => 'Featured',
                        'orderby'  => 'meta_value',
                        'order'    => 'DESC',
                        'meta_key' => 'featured',
                    );
                }

                return $options;
            }
        );

        $_GET  = array( 'query-sort' => 'featured-desc' );
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( 'meta_value', $this->value_set( 'orderby' ) );
        $this->assertSame( 'featured', $this->value_set( 'meta_key' ) );
    }

    public function test_search_is_left_to_core(): void {
        $_GET  = array(
            'query-category' => 'news',
            's'              => 'mango',
        );
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertFalse( $this->was_set( 's' ) );
    }

    public function test_any_filter_ignores_sticky_posts(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertTrue( $this->value_set( 'ignore_sticky_posts' ) );
    }

    /**
     * The rule that keeps the archive's own identity (spec section 4.4):
     * core reads tax_query to decide the page's title, template and
     * get_queried_object(), so a filter must never touch it.
     */
    public function test_taxonomies_do_not_touch_the_tax_query_var(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertFalse( $this->was_set( 'tax_query' ) );
    }

    public function test_a_taxonomy_filter_adds_a_posts_where_callback(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query();

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertNotFalse( has_filter( 'posts_where' ) );
    }

    /*
     * The posts_where callback.
     */

    public function test_the_where_callback_only_acts_on_its_own_query(): void {
        $_GET    = array( 'query-category' => 'news' );
        $query   = $this->main_query();
        $where   = $this->capture_posts_where_callback( $query );

        $other_query = Mockery::mock( 'WP_Query' );
        $result      = $where( 'WHERE 1=1', $other_query );

        $this->assertSame( 'WHERE 1=1', $result );
        $this->assertNotFalse( has_filter( 'posts_where' ), 'The callback must not remove itself for another query.' );
    }

    public function test_the_where_callback_appends_the_subquery(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query();
        $where = $this->capture_posts_where_callback( $query );

        $result   = $where( 'WHERE 1=1', $query );
        $expected = TaxonomySubquery::where( array( 'category' => array( 'news' ) ) );

        $this->assertStringStartsWith( 'WHERE 1=1', $result );
        $this->assertStringEndsWith( $expected, $result );
    }

    public function test_the_where_callback_removes_itself_after_running(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query();
        $where = $this->capture_posts_where_callback( $query );

        $where( 'WHERE 1=1', $query );

        $this->assertFalse( has_filter( 'posts_where' ) );
    }

    /*
     * Recording and 404s.
     */

    public function test_the_unfiltered_post_type_is_recorded_before_filtering(): void {
        $_GET  = array( 'query-post_type' => 'page' );
        $query = $this->main_query( array(), array( 'post_type' => 'post' ) );

        ( new MainQueryFilter() )->filter_main_query( $query );

        // The recorded value is what get() returned before set() ran, not
        // what the filter changed it to.
        $this->assertSame( 'post', MainQueryFilter::original( 'post_type' ) );
        $this->assertSame( 'page', $this->value_set( 'post_type' ) );
    }

    public function test_orderby_and_order_are_recorded(): void {
        $_GET  = array( 'query-sort' => 'title-asc' );
        $query = $this->main_query( array(), array( 'orderby' => 'date', 'order' => 'DESC' ) );

        ( new MainQueryFilter() )->filter_main_query( $query );

        $this->assertSame( 'date', MainQueryFilter::original( 'orderby' ) );
        $this->assertSame( 'DESC', MainQueryFilter::original( 'order' ) );
    }

    public function test_a_filtered_date_archive_does_not_404(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query( array( 'is_home' => false, 'is_archive' => true, 'is_date' => true, 'is_paged' => false ) );

        $filter = new MainQueryFilter();
        $filter->filter_main_query( $query );

        $this->assertTrue( $filter->skip_404_for_filtered_archives( false, $query ) );
    }

    public function test_an_unfiltered_request_is_left_to_core(): void {
        $query = $this->main_query( array( 'is_date' => true, 'is_paged' => false ) );

        $filter = new MainQueryFilter();

        $this->assertTrue( $filter->skip_404_for_filtered_archives( true, $query ) );
        $this->assertFalse( $filter->skip_404_for_filtered_archives( false, $query ) );
    }

    public function test_a_paged_date_archive_still_404s(): void {
        $_GET  = array( 'query-category' => 'news' );
        $query = $this->main_query( array( 'is_home' => false, 'is_archive' => true, 'is_date' => true, 'is_paged' => true ) );

        $filter = new MainQueryFilter();
        $filter->filter_main_query( $query );

        $this->assertFalse( $filter->skip_404_for_filtered_archives( false, $query ) );
    }
}
