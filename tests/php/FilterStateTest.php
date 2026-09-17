<?php
/**
 * Tests for parsing and validating one Query Loop's URL filter parameters.
 *
 * Every value rule pinned here matches the 1.0 spec, section 3.2.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Functions;
use Mockery;
use Pikari\GutenbergQueryFilter\Url\FilterState;
use Pikari\GutenbergQueryFilter\Url\QueryParams;
use Pikari\Tests\TestCase;

class FilterStateTest extends TestCase {

    /**
     * Post types the stubs treat as viewable.
     */
    private const VIEWABLE_POST_TYPES = array( 'post', 'page', 'resource' );

    /**
     * Taxonomies the stubs treat as public and viewable.
     */
    private const VIEWABLE_TAXONOMIES = array( 'category', 'post_tag', 'language' );

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
        $this->stub_taxonomies();
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'get_current_blog_id' )->justReturn( 1 );
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
     * Stub get_taxonomies() with the public taxonomy list a test needs.
     *
     * Called from setUp() with the default list; a test that needs a
     * different list calls this again to override it. Calling when() twice
     * for the same function in one test replaces the earlier stub.
     *
     * @param array|null $taxonomies Taxonomy list, in get_taxonomies( …, 'names' ) shape.
     */
    private function stub_taxonomies( ?array $taxonomies = null ): void {
        if ( null === $taxonomies ) {
            $taxonomies = array(
                'category' => 'category',
                'post_tag' => 'post_tag',
                'language' => 'language',
            );
        }

        Functions\when( 'get_taxonomies' )->justReturn( $taxonomies );
    }

    /*
     * No parameters
     */

    public function test_no_parameters_means_no_filters(): void {
        $state = FilterState::from_array( array(), new QueryParams( 3 ) );

        $this->assertFalse( $state->has_filters() );
        $this->assertSame( array(), $state->post_types() );
        $this->assertSame( array(), $state->taxonomies() );
        $this->assertNull( $state->author_ids() );
        $this->assertSame( '', $state->search() );
        $this->assertNull( $state->sort() );
    }

    /*
     * Post types
     */

    public function test_post_types_keep_only_viewable_ones(): void {
        $state = FilterState::from_array( array( 'query-3-post_type' => 'page, resource,nope' ), new QueryParams( 3 ) );

        $this->assertSame( array( 'page', 'resource' ), $state->post_types() );
    }

    public function test_attachment_is_rejected_unless_attachment_pages_are_enabled(): void {
        Functions\when( 'is_post_type_viewable' )->alias(
            function ( $post_type ) {
                return 'attachment' === $post_type;
            }
        );

        $get = array( 'query-3-post_type' => 'attachment' );

        $this->assertSame( array(), FilterState::from_array( $get, new QueryParams( 3 ) )->post_types() );

        Functions\when( 'get_option' )->justReturn( true );

        $this->assertSame( array( 'attachment' ), FilterState::from_array( $get, new QueryParams( 3 ) )->post_types() );
    }

    /*
     * Taxonomies
     */

    public function test_taxonomy_values_are_slugs(): void {
        $state = FilterState::from_array( array( 'query-3-category' => 'News, Events' ), new QueryParams( 3 ) );

        $this->assertSame( array( 'category' => array( 'news', 'events' ) ), $state->taxonomies() );
    }

    public function test_non_viewable_taxonomies_are_ignored(): void {
        $this->stub_taxonomies(
            array(
                'category' => 'category',
                'secret'   => 'secret',
            )
        );

        $state = FilterState::from_array( array( 'query-3-secret' => 'x' ), new QueryParams( 3 ) );

        $this->assertSame( array(), $state->taxonomies() );
    }

    /**
     * The no-JS `key[]` form from spec §3.2.
     */
    public function test_array_values_are_read_like_comma_lists(): void {
        $comma = FilterState::from_array( array( 'query-3-category' => 'news,events' ), new QueryParams( 3 ) );
        $array = FilterState::from_array( array( 'query-3-category' => array( 'news', 'events' ) ), new QueryParams( 3 ) );

        $this->assertSame( $comma->taxonomies(), $array->taxonomies() );
    }

    public function test_values_are_deduplicated_and_capped_at_50(): void {
        $slugs  = array_map(
            static function ( $i ) {
                return 'term-' . $i;
            },
            range( 1, 60 )
        );
        $values = implode( ',', array_merge( $slugs, array( 'term-1' ) ) );

        $state = FilterState::from_array( array( 'query-3-category' => $values ), new QueryParams( 3 ) );

        $this->assertCount( 50, $state->taxonomies()['category'] );
        $this->assertSame( array_unique( $state->taxonomies()['category'] ), $state->taxonomies()['category'] );
    }

    public function test_empty_values_are_ignored(): void {
        $state = FilterState::from_array(
            array(
                'query-3-category' => '',
                'query-3-s'        => '',
            ),
            new QueryParams( 3 )
        );

        $this->assertSame( array(), $state->taxonomies() );
        $this->assertSame( '', $state->search() );
    }

    /*
     * Authors
     */

    public function test_authors_resolve_by_nicename(): void {
        Functions\expect( 'get_users' )
            ->once()
            ->with(
                array(
                    'blog_id'      => 1,
                    'nicename__in' => array( 'jane-doe', 'sam-lee' ),
                    'fields'       => array( 'ID', 'user_nicename' ),
                    'number'       => 50,
                )
            )
            ->andReturn(
                array(
                    (object) array(
                        'ID'            => 5,
                        'user_nicename' => 'jane-doe',
                    ),
                    (object) array(
                        'ID'            => 9,
                        'user_nicename' => 'sam-lee',
                    ),
                )
            );

        $state = FilterState::from_array( array( 'query-3-author' => 'jane-doe,sam-lee' ), new QueryParams( 3 ) );

        $this->assertSame( array( 5, 9 ), $state->author_ids() );
    }

    public function test_numeric_author_values_resolve_by_id_when_no_nicename_matches(): void {
        Functions\expect( 'get_users' )
            ->once()
            ->with(
                array(
                    'blog_id'      => 1,
                    'nicename__in' => array( '7' ),
                    'fields'       => array( 'ID', 'user_nicename' ),
                    'number'       => 50,
                )
            )
            ->andReturn( array() );

        Functions\expect( 'get_users' )
            ->once()
            ->with(
                array(
                    'blog_id' => 1,
                    'include' => array( 7 ),
                    'fields'  => 'ID',
                    'number'  => 50,
                )
            )
            ->andReturn( array( 7 ) );

        $state = FilterState::from_array( array( 'query-3-author' => '7' ), new QueryParams( 3 ) );

        $this->assertSame( array( 7 ), $state->author_ids() );
    }

    /**
     * The include lookup is never called here: the nicename call already
     * resolved '12', so it never joins the digit branch.
     */
    public function test_a_numeric_nicename_wins_over_the_same_id(): void {
        Functions\expect( 'get_users' )
            ->once()
            ->with(
                array(
                    'blog_id'      => 1,
                    'nicename__in' => array( '12' ),
                    'fields'       => array( 'ID', 'user_nicename' ),
                    'number'       => 50,
                )
            )
            ->andReturn(
                array(
                    (object) array(
                        'ID'            => 99,
                        'user_nicename' => '12',
                    ),
                )
            );

        $state = FilterState::from_array( array( 'query-3-author' => '12' ), new QueryParams( 3 ) );

        $this->assertSame( array( 99 ), $state->author_ids() );
    }

    /**
     * A change from 0.3.4, which ignored an author parameter that resolved
     * to nobody. Spec §3.2 treats it like an unknown taxonomy slug: no
     * results, rather than showing everything.
     */
    public function test_authors_that_resolve_to_nobody_return_no_results(): void {
        Functions\when( 'get_users' )->justReturn( array() );

        $state = FilterState::from_array( array( 'query-3-author' => 'nobody' ), new QueryParams( 3 ) );

        $this->assertSame( array( 0 ), $state->author_ids() );
        $this->assertTrue( $state->has_filters() );
    }

    public function test_author_lookups_are_limited_to_this_site(): void {
        Functions\expect( 'get_users' )
            ->once()
            ->with(
                Mockery::on(
                    function ( $args ) {
                        return isset( $args['blog_id'] ) && 1 === $args['blog_id'];
                    }
                )
            )
            ->andReturn( array() );

        FilterState::from_array( array( 'query-3-author' => 'jane-doe' ), new QueryParams( 3 ) );

        $this->addToAssertionCount( 1 );
    }

    /*
     * Search
     */

    public function test_search_is_sanitized(): void {
        $state = FilterState::from_array( array( 'query-3-s' => 'mango' ), new QueryParams( 3 ) );

        $this->assertSame( 'mango', $state->search() );
    }

    /*
     * Sorting
     */

    public function test_sort_resolves_through_the_allowlist(): void {
        $state = FilterState::from_array( array( 'query-3-sort' => 'title-asc' ), new QueryParams( 3 ) );

        $this->assertSame( 'title', $state->sort()['orderby'] );
        $this->assertSame( 'ASC', $state->sort()['order'] );
    }

    /**
     * This is what stops orderby=rand reaching WP_Query (spec §3.2, §3.6).
     */
    public function test_unknown_sort_keys_are_ignored(): void {
        $state = FilterState::from_array( array( 'query-3-sort' => 'rand' ), new QueryParams( 3 ) );

        $this->assertNull( $state->sort() );
    }

    /*
     * Parameter prefixes
     */

    public function test_parameters_for_another_loop_are_ignored(): void {
        $state = FilterState::from_array( array( 'query-30-category' => 'news' ), new QueryParams( 3 ) );

        $this->assertSame( array(), $state->taxonomies() );
    }

    public function test_inherited_loop_reads_unnumbered_keys_and_core_search(): void {
        $state = FilterState::from_array(
            array(
                'query-category' => 'news',
                's'              => 'mango',
            ),
            new QueryParams( null, true )
        );

        $this->assertSame( array( 'category' => array( 'news' ) ), $state->taxonomies() );
        $this->assertSame( 'mango', $state->search() );
    }

    /*
     * for_loop() memoization
     */

    public function test_for_loop_reads_the_request_once_per_prefix(): void {
        $_GET = array( 'query-3-category' => 'news' );

        $params = new QueryParams( 3 );
        $first  = FilterState::for_loop( $params );

        $_GET = array( 'query-3-category' => 'events' );

        $second = FilterState::for_loop( $params );

        $this->assertSame( $first, $second );
        $this->assertSame( array( 'category' => array( 'news' ) ), $second->taxonomies() );
    }

    public function test_for_loop_caches_per_prefix(): void {
        $_GET = array(
            'query-3-category' => 'news',
            'query-4-category' => 'events',
        );

        $state_3 = FilterState::for_loop( new QueryParams( 3 ) );
        $state_4 = FilterState::for_loop( new QueryParams( 4 ) );

        $this->assertSame( array( 'category' => array( 'news' ) ), $state_3->taxonomies() );
        $this->assertSame( array( 'category' => array( 'events' ) ), $state_4->taxonomies() );
    }
}
