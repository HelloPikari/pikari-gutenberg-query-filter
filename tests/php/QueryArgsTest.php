<?php
/**
 * Tests for merging filter state into a custom loop's query arguments.
 *
 * Every rule pinned here matches the 1.0 spec, section 4.2.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Pikari\GutenbergQueryFilter\Query\QueryArgs;
use Pikari\GutenbergQueryFilter\Url\FilterState;
use Pikari\GutenbergQueryFilter\Url\QueryParams;
use Pikari\Tests\TestCase;

class QueryArgsTest extends TestCase {

    /**
     * Post types the stubs treat as viewable.
     */
    private const VIEWABLE_POST_TYPES = array( 'post', 'page', 'resource' );

    /**
     * Taxonomies the stubs treat as public and viewable.
     */
    private const VIEWABLE_TAXONOMIES = array( 'category', 'post_tag', 'language' );

    /**
     * Stubs shared with FilterStateTest, extracted here rather than shared
     * across test classes: QueryArgs is tested through the states
     * FilterState::from_array() builds.
     */
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
        Functions\when( 'sanitize_title_for_query' )->alias(
            function ( $value ) {
                return strtolower( (string) $value );
            }
        );
        Functions\when( 'is_post_type_viewable' )->alias(
            function ( $post_type ) {
                return in_array( $post_type, self::VIEWABLE_POST_TYPES, true );
            }
        );
        Functions\when( 'is_taxonomy_viewable' )->alias(
            function ( $taxonomy ) {
                return in_array( $taxonomy, self::VIEWABLE_TAXONOMIES, true );
            }
        );
        Functions\when( 'get_taxonomies' )->justReturn(
            array(
                'category' => 'category',
                'post_tag' => 'post_tag',
                'language' => 'language',
            )
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
        FilterState::reset_cache();
        parent::tearDown();
    }

    /**
     * Build a FilterState from raw $_GET-style values, for queryId 3.
     *
     * @param array $get URL parameters.
     * @return FilterState
     */
    private function state( array $get ): FilterState {
        return FilterState::from_array( $get, new QueryParams( 3 ) );
    }

    public function test_no_filters_leaves_the_arguments_untouched(): void {
        $args = array(
            'post_type'      => 'post',
            'posts_per_page' => 5,
        );

        $this->assertSame( $args, QueryArgs::apply( $args, $this->state( array() ) ) );
    }

    public function test_post_types_replace_the_loops_post_type(): void {
        $single = QueryArgs::apply(
            array( 'post_type' => 'post' ),
            $this->state( array( 'query-3-post_type' => 'page' ) )
        );
        $this->assertSame( 'page', $single['post_type'] );

        $multiple = QueryArgs::apply(
            array( 'post_type' => 'post' ),
            $this->state( array( 'query-3-post_type' => 'page,resource' ) )
        );
        $this->assertSame( array( 'page', 'resource' ), $multiple['post_type'] );
    }

    /**
     * A change from 0.3.4, which removed post__in and broke sticky "only"
     * (spec §3.6).
     */
    public function test_post_type_filter_keeps_the_loops_post__in(): void {
        $result = QueryArgs::apply(
            array( 'post__in' => array( 7, 9 ) ),
            $this->state( array( 'query-3-post_type' => 'page' ) )
        );

        $this->assertSame( array( 7, 9 ), $result['post__in'] );
    }

    public function test_taxonomy_filter_adds_an_in_clause_by_slug(): void {
        $result = QueryArgs::apply( array(), $this->state( array( 'query-3-category' => 'news,events' ) ) );

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

    public function test_two_taxonomies_are_joined_with_and(): void {
        $result = QueryArgs::apply(
            array(),
            $this->state(
                array(
                    'query-3-category' => 'news',
                    'query-3-post_tag' => 'featured',
                )
            )
        );

        $this->assertSame( 'AND', $result['tax_query']['relation'] );
        $this->assertSame( 'category', $result['tax_query'][0]['taxonomy'] );
        $this->assertSame( 'post_tag', $result['tax_query'][1]['taxonomy'] );
    }

    /**
     * A change from 0.3.4, which forced the existing relation to AND and
     * broke core's OR post-format tax_query (spec §3.6, §4.2).
     */
    public function test_an_existing_tax_query_is_nested_unchanged(): void {
        $existing = array(
            'relation' => 'OR',
            array(
                'taxonomy' => 'post_format',
                'field'    => 'slug',
                'terms'    => array( 'post-format-gallery' ),
                'operator' => 'IN',
            ),
        );

        $result = QueryArgs::apply(
            array( 'tax_query' => $existing ),
            $this->state( array( 'query-3-category' => 'news' ) )
        );

        $this->assertSame( 'AND', $result['tax_query']['relation'] );
        $this->assertSame( $existing, $result['tax_query'][0] );
        $this->assertSame( 'OR', $result['tax_query'][0]['relation'] );
        $this->assertSame( 'category', $result['tax_query'][1][0]['taxonomy'] );
    }

    public function test_authors_set_author__in(): void {
        Functions\when( 'get_users' )->justReturn(
            array(
                (object) array(
                    'ID'            => 5,
                    'user_nicename' => 'jane-doe',
                ),
            )
        );

        $result = QueryArgs::apply( array(), $this->state( array( 'query-3-author' => 'jane-doe' ) ) );

        $this->assertSame( array( 5 ), $result['author__in'] );
    }

    public function test_authors_that_resolved_to_nobody_return_no_posts(): void {
        $result = QueryArgs::apply( array(), $this->state( array( 'query-3-author' => 'nobody' ) ) );

        $this->assertSame( array( 0 ), $result['author__in'] );
    }

    public function test_search_sets_s(): void {
        $result = QueryArgs::apply( array(), $this->state( array( 'query-3-s' => 'mango' ) ) );

        $this->assertSame( 'mango', $result['s'] );
    }

    public function test_sort_sets_orderby_and_order(): void {
        $result = QueryArgs::apply( array(), $this->state( array( 'query-3-sort' => 'title-asc' ) ) );

        $this->assertSame( 'title', $result['orderby'] );
        $this->assertSame( 'ASC', $result['order'] );
    }

    public function test_a_meta_sort_also_sets_meta_key(): void {
        Filters\expectApplied( 'pikari_gutenberg_query_filter_sort_options' )->andReturnUsing(
            function ( array $options ) {
                $options[] = array(
                    'key'      => 'price-asc',
                    'label'    => 'Price',
                    'orderby'  => 'meta_value_num',
                    'order'    => 'asc',
                    'meta_key' => 'price',
                );

                return $options;
            }
        );

        $result = QueryArgs::apply( array(), $this->state( array( 'query-3-sort' => 'price-asc' ) ) );

        $this->assertSame( 'meta_value_num', $result['orderby'] );
        $this->assertSame( 'price', $result['meta_key'] );
    }

    /**
     * Core prepends sticky posts on page 1 without applying tax, author or
     * search conditions (spec §1, §3.6, §4.2).
     */
    public function test_any_filter_ignores_sticky_posts(): void {
        $result = QueryArgs::apply( array(), $this->state( array( 'query-3-category' => 'news' ) ) );

        $this->assertTrue( $result['ignore_sticky_posts'] );
    }

    public function test_a_loops_own_ignore_sticky_posts_is_kept(): void {
        $result = QueryArgs::apply(
            array( 'ignore_sticky_posts' => 0 ),
            $this->state( array( 'query-3-category' => 'news' ) )
        );

        $this->assertSame( 0, $result['ignore_sticky_posts'] );
    }

    public function test_apply_does_not_mutate_the_arguments_it_was_given(): void {
        $args     = array( 'post_type' => 'post' );
        $original = $args;

        QueryArgs::apply( $args, $this->state( array( 'query-3-post_type' => 'page' ) ) );

        $this->assertSame( $original, $args );
    }
}
