<?php
/**
 * Characterization tests for QueryLoopHandler, the thin adapter over the
 * Url\QueryParams, Url\FilterState and Query\QueryArgs classes (1.0 contract).
 *
 * Detailed value parsing and query-merging rules now live in FilterStateTest
 * and QueryArgsTest; this file pins only the adapter's own behaviour: hook
 * registration, prefix resolution, the inherited-loop early return, and one
 * example of each filter type reaching the query arguments. Tests whose
 * expected behaviour changed from 0.3.4 carry a docblock naming the spec
 * clause that now governs them
 * (docs/superpowers/specs/2026-09-16-query-filter-1.0-design.md, section 3.6).
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Pikari\GutenbergQueryFilter\Core\QueryLoopHandler;
use Pikari\GutenbergQueryFilter\Url\FilterState;
use Pikari\Tests\TestCase;

class QueryLoopHandlerTest extends TestCase {

    /**
     * Post types the stubs treat as viewable.
     */
    private const VIEWABLE_POST_TYPES = array( 'post', 'page', 'resource' );

    protected function setUp(): void {
        parent::setUp();

        $_GET = array();

        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'absint' )->alias(
            function ( $value ) {
                return abs( (int) $value );
            }
        );
        Functions\when( 'is_post_type_viewable' )->alias(
            function ( $post_type ) {
                return in_array( $post_type, self::VIEWABLE_POST_TYPES, true );
            }
        );
        Functions\when( 'is_taxonomy_viewable' )->justReturn( true );
        Functions\when( 'sanitize_title_for_query' )->alias(
            function ( $value ) {
                return strtolower( (string) $value );
            }
        );
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'get_current_blog_id' )->justReturn( 1 );
        Functions\when( 'get_users' )->justReturn( array() );
        Functions\stubTranslationFunctions();
        Functions\when( 'sanitize_key' )->alias(
            function ( $key ) {
                return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
            }
        );
    }

    protected function tearDown(): void {
        $_GET = array();
        // FilterState::for_loop() memoizes per prefix; without this, the next
        // test in this file would read this test's $_GET (see task-5-brief).
        FilterState::reset_cache();
        parent::tearDown();
    }

    /**
     * Stub get_taxonomies() to return the public taxonomies most tests need.
     *
     * Kept out of setUp() so test_parameters_for_non_public_taxonomies_are_ignored
     * can register its own Functions\expect() instead: Brain\Monkey's expect()
     * does not override a when() stub already registered for the same function.
     */
    private function stub_public_taxonomies(): void {
        Functions\when( 'get_taxonomies' )->justReturn(
            array(
                'category' => 'category',
                'post_tag' => 'post_tag',
            )
        );
    }

    /**
     * Build a Query Loop inner block with the given query context.
     *
     * @param array    $query    The `query` block context.
     * @param int|null $query_id The `queryId` block context, or null to leave it unset.
     * @return \WP_Block Block mock.
     */
    private function block( array $query = array( 'inherit' => false ), ?int $query_id = 3 ): \WP_Block {
        $context = array( 'query' => $query );

        if ( null !== $query_id ) {
            $context['queryId'] = $query_id;
        }

        // Assign once: Mockery creates the property on first write.
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = $context;

        return $block;
    }

    /**
     * Run the handler for a custom loop with the given URL parameters.
     *
     * @param array $get              URL parameters.
     * @param array $query_args       Query arguments core built for the loop.
     * @param bool  $stub_taxonomies  Whether to stub get_taxonomies() with the
     *                                default public list. False when a test
     *                                registers its own Functions\expect().
     * @return array Filtered query arguments.
     */
    private function filter( array $get, array $query_args = array(), bool $stub_taxonomies = true ): array {
        if ( $stub_taxonomies ) {
            $this->stub_public_taxonomies();
        }

        $_GET = $get;

        return ( new QueryLoopHandler() )->modify_query( $query_args, $this->block(), 1 );
    }

    /*
     * Hook registration
     */

    public function test_constructor_filters_query_loop_query_vars_at_priority_19_with_3_args(): void {
        \Brain\Monkey\Filters\expectAdded( 'query_loop_block_query_vars' )
            ->once()
            ->with( Mockery::type( 'array' ), 19, 3 );

        new QueryLoopHandler();
        $this->addToAssertionCount( 1 );
    }

    /*
     * No parameters
     */

    public function test_query_args_are_unchanged_without_parameters(): void {
        $query_args = array(
            'post_type'      => 'post',
            'posts_per_page' => 5,
        );

        $this->assertSame( $query_args, $this->filter( array(), $query_args ) );
    }

    public function test_parameters_for_another_query_id_are_ignored(): void {
        $query_args = array( 'post_type' => 'post' );

        $this->assertSame(
            $query_args,
            $this->filter( array( 'query-30-category' => 'news' ), $query_args )
        );
    }

    /*
     * Post types
     */

    public function test_single_post_type_replaces_post_type_as_a_string(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page' ), array( 'post_type' => 'post' ) );

        $this->assertSame( 'page', $result['post_type'] );
    }

    public function test_multiple_post_types_keep_only_viewable_ones_as_an_array(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page, wp_block,resource,nope' ) );

        $this->assertSame( array( 'page', 'resource' ), $result['post_type'] );
    }

    public function test_post_type_filter_is_ignored_when_no_type_is_valid(): void {
        $query_args = array( 'post_type' => 'post' );

        $this->assertSame( $query_args, $this->filter( array( 'query-3-post_type' => 'wp_block,nope' ), $query_args ) );
    }

    public function test_post_type_filter_ignores_sticky_posts_when_the_loop_has_not_decided(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page' ) );

        $this->assertTrue( $result['ignore_sticky_posts'] );
    }

    public function test_post_type_filter_keeps_the_loops_ignore_sticky_posts(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page' ), array( 'ignore_sticky_posts' => 0 ) );

        $this->assertSame( 0, $result['ignore_sticky_posts'] );
    }

    /**
     * Change from 0.3.4, which removed post__in and broke a loop like sticky
     * "only" (spec section 3.6).
     */
    public function test_post_type_filter_keeps_post__in(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page' ), array( 'post__in' => array( 7, 9 ) ) );

        $this->assertSame( array( 7, 9 ), $result['post__in'] );
    }

    /*
     * Taxonomies
     */

    public function test_taxonomy_parameter_adds_an_in_clause_by_slug(): void {
        $result = $this->filter( array( 'query-3-category' => 'news,events' ) );

        $this->assertSame(
            array(
                array(
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => array( 'news', 'events' ),
                    'operator' => 'IN',
                ),
            ),
            $result['tax_query']
        );
    }

    public function test_empty_term_slugs_are_dropped(): void {
        $result = $this->filter( array( 'query-3-category' => 'news,, events' ) );

        $this->assertSame( array( 'news', 'events' ), array_values( $result['tax_query'][0]['terms'] ) );
    }

    public function test_two_taxonomies_are_joined_with_and(): void {
        $result = $this->filter(
            array(
                'query-3-category' => 'news',
                'query-3-post_tag' => 'featured',
            )
        );

        $this->assertSame( 'AND', $result['tax_query']['relation'] );
        $this->assertSame( 'category', $result['tax_query'][0]['taxonomy'] );
        $this->assertSame( 'post_tag', $result['tax_query'][1]['taxonomy'] );
    }

    public function test_parameters_for_non_public_taxonomies_are_ignored(): void {
        Functions\expect( 'get_taxonomies' )
            ->once()
            ->with( array( 'public' => true ), 'names' )
            ->andReturn( array( 'category' => 'category', 'post_tag' => 'post_tag' ) );

        $query_args = array( 'post_type' => 'post' );

        $this->assertSame( $query_args, $this->filter( array( 'query-3-language' => 'fr' ), $query_args, false ) );
    }

    /**
     * Change from 0.3.4, in which only a post type filter cleared sticky
     * posts. Now any filter does, because core prepends sticky posts on
     * page 1 without applying tax, author or search conditions
     * (spec section 1, section 3.6, section 4.2).
     */
    public function test_any_filter_ignores_sticky_posts(): void {
        $result = $this->filter( array( 'query-3-category' => 'news' ) );

        $this->assertTrue( $result['ignore_sticky_posts'] );
    }

    /**
     * Change from 0.3.4, which forced the loop's existing tax_query relation
     * to AND and broke core's OR post-format tax_query
     * (spec section 3.6, section 4.2).
     */
    public function test_existing_tax_query_is_nested_with_its_own_relation(): void {
        $existing = array(
            'relation' => 'OR',
            array(
                'taxonomy' => 'post_format',
                'field'    => 'slug',
                'terms'    => array( 'post-format-gallery' ),
                'operator' => 'IN',
            ),
        );

        $result = $this->filter( array( 'query-3-category' => 'news' ), array( 'tax_query' => $existing ) );

        $this->assertSame( 'AND', $result['tax_query']['relation'] );
        $this->assertSame( $existing, $result['tax_query'][0] );
        $this->assertSame( 'category', $result['tax_query'][1][0]['taxonomy'] );
    }

    /*
     * Authors
     */

    /**
     * Change from 0.3.4: authors resolve by user nicename instead of a raw
     * numeric ID (spec section 3.1, section 3.2, section 3.6). The nobody-
     * resolves-to-no-results case is pinned in FilterStateTest and
     * QueryArgsTest, not repeated here.
     */
    public function test_author_parameter_resolves_nicenames_to_author__in(): void {
        Functions\when( 'get_users' )->justReturn(
            array(
                (object) array(
                    'ID'            => 5,
                    'user_nicename' => 'jane-doe',
                ),
            )
        );

        $result = $this->filter( array( 'query-3-author' => 'jane-doe' ) );

        $this->assertSame( array( 5 ), $result['author__in'] );
    }

    /*
     * Search
     */

    public function test_search_parameter_sets_s(): void {
        $this->assertSame( 'mango', $this->filter( array( 'query-3-s' => 'mango' ) )['s'] );
    }

    public function test_empty_search_parameter_is_ignored(): void {
        $this->assertArrayNotHasKey( 's', $this->filter( array( 'query-3-s' => '' ) ) );
    }

    /*
     * Sorting
     */

    /**
     * Change from 0.3.4: query-{id}-orderby and query-{id}-order are no
     * longer read. A single query-{id}-sort key, looked up in an allowlist,
     * replaces them (spec section 3.1, section 3.2, section 3.6).
     */
    public function test_old_orderby_and_order_parameters_are_ignored(): void {
        $query_args = array( 'orderby' => 'date' );

        $result = $this->filter(
            array(
                'query-3-orderby' => 'rand',
                'query-3-order'   => 'asc',
            ),
            $query_args
        );

        $this->assertSame( $query_args, $result );
    }

    /**
     * Change from 0.3.4: sort is now a single allowlisted key
     * (spec section 3.1, section 3.2, section 3.6).
     */
    public function test_sort_parameter_sets_orderby_and_order(): void {
        $result = $this->filter( array( 'query-3-sort' => 'title-asc' ) );

        $this->assertSame( 'title', $result['orderby'] );
        $this->assertSame( 'ASC', $result['order'] );
    }

    /*
     * Parameter prefixes
     */

    public function test_loop_without_query_id_reads_query_0_parameters(): void {
        $this->stub_public_taxonomies();
        $_GET = array( 'query-0-category' => 'news' );

        $result = ( new QueryLoopHandler() )->modify_query( array(), $this->block( array( 'inherit' => false ), null ), 1 );

        $this->assertSame( 'category', $result['tax_query'][0]['taxonomy'] );
    }

    /**
     * Change from 0.3.4: core never applies this filter to inherited loops
     * (`wp-includes/blocks/post-template.php`, spec section 1), so
     * modify_query() now returns early for them instead of parsing
     * unnumbered parameters itself. B2 handles inherited loops through
     * pre_get_posts (spec section 4.4).
     */
    public function test_inherited_loop_returns_the_query_args_unchanged(): void {
        $_GET = array(
            'query-category' => 'news',
            's'              => 'mango',
        );

        $query_args = array( 'post_type' => 'post' );

        $result = ( new QueryLoopHandler() )->modify_query( $query_args, $this->block( array( 'inherit' => true ) ), 1 );

        $this->assertSame( $query_args, $result );
    }
}
