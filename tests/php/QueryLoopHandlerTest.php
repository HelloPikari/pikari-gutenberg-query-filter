<?php
/**
 * Characterization tests for QueryLoopHandler (0.3.4 behaviour).
 *
 * These pin how URL parameters become Query Loop query arguments before the
 * 1.0 rewrite. Tests describing a known bug say so; B1 changes only the
 * behaviours listed in the 1.0 spec, section 3.6.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Pikari\GutenbergQueryFilter\Core\QueryLoopHandler;
use Pikari\Tests\TestCase;

class QueryLoopHandlerTest extends TestCase {

    /**
     * Post types the stubs treat as registered and viewable.
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
        Functions\when( 'post_type_exists' )->alias(
            function ( $post_type ) {
                return in_array( $post_type, array_merge( self::VIEWABLE_POST_TYPES, array( 'wp_block' ) ), true );
            }
        );
        Functions\when( 'is_post_type_viewable' )->alias(
            function ( $post_type ) {
                return in_array( $post_type, self::VIEWABLE_POST_TYPES, true );
            }
        );
        Functions\when( 'get_taxonomies' )->justReturn(
            array(
                'category' => 'category',
                'post_tag' => 'post_tag',
            )
        );
        Functions\when( 'taxonomy_exists' )->justReturn( true );
    }

    protected function tearDown(): void {
        $_GET = array();
        parent::tearDown();
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
     * @param array $get        URL parameters.
     * @param array $query_args Query arguments core built for the loop.
     * @return array Filtered query arguments.
     */
    private function filter( array $get, array $query_args = array() ): array {
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
     * Known behaviour B1 changes: a post type filter should keep the loop's
     * post__in, for example sticky "only" (spec §3.6).
     */
    public function test_post_type_filter_removes_post__in(): void {
        $result = $this->filter( array( 'query-3-post_type' => 'page' ), array( 'post__in' => array( 7, 9 ) ) );

        $this->assertArrayNotHasKey( 'post__in', $result );
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
        $query_args = array( 'post_type' => 'post' );

        $this->assertSame( $query_args, $this->filter( array( 'query-3-language' => 'fr' ), $query_args ) );
    }

    /**
     * Known bug B1 fixes: the loop's own relation is overwritten with AND,
     * so an OR tax_query (such as core's post format filter) stops matching
     * (spec §1, §3.6, §4.2).
     */
    public function test_existing_tax_query_is_nested_with_its_relation_forced_to_and(): void {
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
        $this->assertSame( 'AND', $result['tax_query'][0]['relation'] );
        $this->assertSame( 'post_format', $result['tax_query'][0][0]['taxonomy'] );
        $this->assertSame( 'category', $result['tax_query'][1][0]['taxonomy'] );
    }

    /*
     * Authors
     */

    public function test_author_parameter_sets_author__in_to_positive_integer_ids(): void {
        $result = $this->filter( array( 'query-3-author' => '7,abc,0,12' ) );

        $this->assertSame( array( 7, 12 ), array_values( $result['author__in'] ) );
    }

    /**
     * Known behaviour B1 changes: unresolvable authors return no results
     * instead of being ignored (spec §3.2, §3.6).
     */
    public function test_author_parameter_without_valid_ids_is_ignored(): void {
        $this->assertArrayNotHasKey( 'author__in', $this->filter( array( 'query-3-author' => 'jane-doe' ) ) );
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
     * Known behaviour B1 changes: any orderby value reaches WP_Query. 1.0 reads a
     * single sort key from an allowlist instead (spec §3.2, §3.6).
     */
    public function test_orderby_passes_any_value_through(): void {
        $this->assertSame( 'rand', $this->filter( array( 'query-3-orderby' => 'rand' ) )['orderby'] );
    }

    public function test_order_is_uppercased(): void {
        $this->assertSame( 'ASC', $this->filter( array( 'query-3-order' => 'asc' ) )['order'] );
    }

    public function test_order_other_than_asc_or_desc_is_ignored(): void {
        $this->assertArrayNotHasKey( 'order', $this->filter( array( 'query-3-order' => 'sideways' ) ) );
    }

    /*
     * Parameter prefixes
     */

    public function test_loop_without_query_id_reads_query_0_parameters(): void {
        $_GET = array( 'query-0-category' => 'news' );

        $result = ( new QueryLoopHandler() )->modify_query( array(), $this->block( array( 'inherit' => false ), null ), 1 );

        $this->assertSame( 'category', $result['tax_query'][0]['taxonomy'] );
    }

    /**
     * Core never calls this filter for inherited loops (spec §1), so this
     * branch only runs when another plugin applies the filter itself.
     */
    public function test_inherited_loop_reads_unnumbered_parameters_and_core_search(): void {
        $_GET = array(
            'query-category' => 'news',
            's'              => 'mango',
        );

        $result = ( new QueryLoopHandler() )->modify_query( array(), $this->block( array( 'inherit' => true ) ), 1 );

        $this->assertSame( 'category', $result['tax_query'][0]['taxonomy'] );
        $this->assertSame( 'mango', $result['s'] );
    }
}
