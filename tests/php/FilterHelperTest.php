<?php
/**
 * Tests for FilterHelper option normalization, classes, and label markup.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Pikari\Tests\TestCase;
use Pikari\GutenbergQueryFilter\Helpers\FilterHelper;
use Pikari\GutenbergQueryFilter\Integrations\MainQueryFilter;
use Brain\Monkey\Functions;
use Brain\Monkey\Filters;
use Mockery;
use ReflectionProperty;

class FilterHelperTest extends TestCase {

    /**
     * The $wp_query global's value before this test replaced it, restored
     * in tearDown().
     *
     * @var mixed
     */
    private $original_wp_query;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubEscapeFunctions();

        // Mirrors WordPress core's sanitize_html_class() with no fallback.
        Functions\when( 'sanitize_html_class' )->alias(
            function ( $classname ) {
                $sanitized = preg_replace( '|%[a-fA-F0-9][a-fA-F0-9]|', '', $classname );
                return preg_replace( '/[^A-Za-z0-9_-]/', '', $sanitized );
            }
        );

        global $wp_query;
        $this->original_wp_query = $wp_query;

        MainQueryFilter::reset_for_tests();
    }

    protected function tearDown(): void {
        global $wp_query;
        $wp_query = $this->original_wp_query;

        MainQueryFilter::reset_for_tests();

        parent::tearDown();
    }

    /**
     * Record a main-query post_type value the way MainQueryFilter would,
     * without running its full pre_get_posts pipeline. That pipeline is
     * MainQueryFilter's own responsibility and is covered by
     * MainQueryFilterTest; these tests only need the recorded value it
     * produces.
     *
     * @param mixed $value Value MainQueryFilter::original( 'post_type' ) should return.
     */
    private function record_original_post_type( $value ): void {
        $property = new ReflectionProperty( MainQueryFilter::class, 'original' );
        $property->setValue( null, array( 'post_type' => $value ) );
    }

    /**
     * Build a WP_Query stand-in stubbed with the is_*() methods and
     * get_queried_object() that get_filter_post_types() can call, plus the
     * raw `query` property used as a fallback when nothing was recorded.
     *
     * get() is deliberately left unstubbed: get_filter_post_types() must
     * never call it for an inherited loop's post types, and Mockery throws
     * if an unstubbed method is called.
     *
     * @param array $flags Overrides for the boolean is_*() methods.
     * @param array $query Value for the `query` property (raw request query vars).
     * @return \WP_Query
     */
    private function wp_query_stub( array $flags = array(), array $query = array() ): \WP_Query {
        $flags = array_merge(
            array(
                'is_search'   => false,
                'is_tax'      => false,
                'is_category' => false,
                'is_tag'      => false,
            ),
            $flags
        );

        $query_object = Mockery::mock( 'WP_Query' );

        foreach ( $flags as $method => $value ) {
            $query_object->shouldReceive( $method )->andReturn( $value );
        }

        $query_object->query = $query;

        return $query_object;
    }

    /**
     * Build a block instance for get_filter_post_types(), with the given
     * inherit flag and block-declared postType.
     *
     * @param bool   $inherit   Whether the loop inherits the main query.
     * @param string $post_type The block's own postType attribute.
     * @return object
     */
    private function post_type_filter_block( bool $inherit, string $post_type = 'post' ): object {
        return (object) array(
            'context' => array(
                'query' => array(
                    'inherit'  => $inherit,
                    'postType' => $post_type,
                ),
            ),
        );
    }

    /*
     * get_filter_post_types(), inherited loops (spec §4.5)
     */

    public function test_inherited_post_types_come_from_recorded_original_value(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );
        Functions\when( 'get_option' )->justReturn( true );

        $this->record_original_post_type( 'page' );

        global $wp_query;
        // No get() stub: get_filter_post_types() must not read the query
        // this way, only through MainQueryFilter::original(). The `query`
        // property is a decoy fallback value that must not win.
        $wp_query = $this->wp_query_stub( array(), array( 'post_type' => 'attachment' ) );

        $result = FilterHelper::get_filter_post_types( $this->post_type_filter_block( true, 'category' ) );

        $this->assertSame( array( 'page' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    public function test_inherited_post_types_replace_the_blocks_own_post_type_rather_than_merging(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );
        Functions\when( 'get_option' )->justReturn( true );

        $this->record_original_post_type( 'page' );

        global $wp_query;
        $wp_query = $this->wp_query_stub();

        // The block declares postType=post; the main query says page. Only
        // page must render (spec §4.5: "They replace the block context's postType").
        $result = FilterHelper::get_filter_post_types( $this->post_type_filter_block( true, 'post' ) );

        $this->assertSame( array( 'page' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    public function test_inherited_post_types_on_search_with_empty_value_are_public_non_search_excluded_types(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );
        Functions\when( 'get_option' )->justReturn( true );
        Functions\expect( 'get_post_types' )
            ->once()
            ->with( array( 'public' => true, 'exclude_from_search' => false ) )
            ->andReturn( array( 'post', 'page' ) );

        $this->record_original_post_type( '' );

        global $wp_query;
        $wp_query = $this->wp_query_stub( array( 'is_search' => true ) );

        $result = FilterHelper::get_filter_post_types( $this->post_type_filter_block( true ) );

        $this->assertSame( array( 'post', 'page' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    public function test_inherited_post_types_on_search_with_any_value_are_public_non_search_excluded_types(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );
        Functions\when( 'get_option' )->justReturn( true );
        Functions\expect( 'get_post_types' )
            ->once()
            ->with( array( 'public' => true, 'exclude_from_search' => false ) )
            ->andReturn( array( 'post', 'page' ) );

        $this->record_original_post_type( 'any' );

        global $wp_query;
        $wp_query = $this->wp_query_stub( array( 'is_search' => true ) );

        $result = FilterHelper::get_filter_post_types( $this->post_type_filter_block( true ) );

        $this->assertSame( array( 'post', 'page' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    public function test_inherited_post_types_on_taxonomy_archive_with_empty_value_are_the_taxonomys_viewable_object_types(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );
        Functions\when( 'get_option' )->justReturn( true );
        Functions\when( 'is_post_type_viewable' )->alias( fn( $post_type ) => 'movie' !== $post_type );
        Functions\when( 'get_taxonomy' )->justReturn( (object) array( 'object_type' => array( 'post', 'movie' ) ) );

        $this->record_original_post_type( '' );

        global $wp_query;
        $wp_query = $this->wp_query_stub( array( 'is_tax' => true ) );
        $wp_query->shouldReceive( 'get_queried_object' )->andReturn( (object) array( 'taxonomy' => 'genre' ) );

        $result = FilterHelper::get_filter_post_types( $this->post_type_filter_block( true ) );

        // Only 'post' survives: 'movie' is registered for the taxonomy but not viewable.
        $this->assertSame( array( 'post' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    public function test_inherited_post_types_anywhere_else_with_empty_value_default_to_post(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );
        Functions\when( 'get_option' )->justReturn( true );

        $this->record_original_post_type( '' );

        global $wp_query;
        $wp_query = $this->wp_query_stub();

        $result = FilterHelper::get_filter_post_types( $this->post_type_filter_block( true ) );

        $this->assertSame( array( 'post' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    public function test_inherited_post_types_drop_attachment_when_attachment_pages_are_disabled(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );
        Functions\when( 'get_option' )->justReturn( false );

        $this->record_original_post_type( array( 'post', 'attachment' ) );

        global $wp_query;
        $wp_query = $this->wp_query_stub();

        $result = FilterHelper::get_filter_post_types( $this->post_type_filter_block( true ) );

        $this->assertSame( array( 'post' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    public function test_inherited_post_types_keep_attachment_when_attachment_pages_are_enabled(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );
        Functions\when( 'get_option' )->justReturn( true );

        $this->record_original_post_type( array( 'post', 'attachment' ) );

        global $wp_query;
        $wp_query = $this->wp_query_stub();

        $result = FilterHelper::get_filter_post_types( $this->post_type_filter_block( true ) );

        $this->assertSame( array( 'post', 'attachment' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    public function test_inherited_post_types_fall_back_to_wp_query_when_nothing_was_recorded(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );
        Functions\when( 'get_option' )->justReturn( true );

        // MainQueryFilter never ran its recording logic on this request
        // (e.g. the first page load, with no filter parameter in the URL
        // yet), so original( 'post_type' ) is null and the raw request's
        // own post_type, a post type archive's for example, is used instead
        // (spec §4.5).
        global $wp_query;
        $wp_query = $this->wp_query_stub( array(), array( 'post_type' => 'movie' ) );

        $result = FilterHelper::get_filter_post_types( $this->post_type_filter_block( true ) );

        $this->assertSame( array( 'movie' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    public function test_custom_loop_post_types_are_unaffected_by_inherited_loop_changes(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );

        $block = (object) array(
            'context' => array(
                'query' => array(
                    'inherit'  => false,
                    'postType' => 'post,page',
                ),
            ),
        );

        $result = FilterHelper::get_filter_post_types( $block );

        $this->assertSame( array( 'post', 'page' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    /**
     * Advanced Query Loop's multiple_posts support (custom loops only) must
     * keep working exactly as it did before this task.
     */
    public function test_custom_loop_multiple_posts_support_is_unaffected(): void {
        Functions\when( 'get_post_type_object' )->alias( fn( $slug ) => (object) array( 'name' => $slug ) );

        $block = (object) array(
            'context' => array(
                'query' => array(
                    'inherit'        => false,
                    'postType'       => 'post',
                    'multiple_posts' => array( 'page', 'movie' ),
                ),
            ),
        );

        $result = FilterHelper::get_filter_post_types( $block );

        $this->assertSame( array( 'post', 'page', 'movie' ), array_map( fn( $t ) => $t->name, array_values( $result ) ) );
    }

    /*
     * get_all_option()
     */

    public function test_get_all_option_returns_empty_value_option_with_all_slug(): void {
        $this->assertSame(
            array(
                'value' => '',
                'label' => 'Everything',
                'slug'  => 'all',
                'item'  => null,
            ),
            FilterHelper::get_all_option( 'Everything' )
        );
    }

    /*
     * get_filter_options()
     */

    public function test_get_filter_options_normalizes_taxonomy_terms(): void {
        $term       = (object) array(
            'slug' => 'news',
            'name' => 'News',
        );
        $attributes = array(
            'filterType' => 'taxonomy',
            'taxonomy'   => 'category',
        );

        $this->assertSame(
            array(
                array(
                    'value' => 'news',
                    'label' => 'News',
                    'slug'  => 'news',
                    'item'  => $term,
                ),
            ),
            FilterHelper::get_filter_options( array( $term ), $attributes )
        );
    }

    public function test_get_filter_options_normalizes_post_types(): void {
        $post_type = (object) array(
            'name'   => 'page',
            'labels' => (object) array( 'name' => 'Pages' ),
        );

        $this->assertSame(
            array(
                array(
                    'value' => 'page',
                    'label' => 'Pages',
                    'slug'  => 'page',
                    'item'  => $post_type,
                ),
            ),
            FilterHelper::get_filter_options( array( $post_type ), array( 'filterType' => 'post-type' ) )
        );
    }

    /**
     * Change from 0.3.4, which used the numeric ID as the option value.
     * Author filter URLs now identify users by nicename (spec §3.2, §3.6).
     */
    public function test_get_filter_options_normalizes_authors_with_nicename_value_and_slug(): void {
        $author = (object) array(
            'ID'            => 7,
            'display_name'  => 'Jane Doe',
            'user_nicename' => 'jane-doe',
        );

        $this->assertSame(
            array(
                array(
                    'value' => 'jane-doe',
                    'label' => 'Jane Doe',
                    'slug'  => 'jane-doe',
                    'item'  => $author,
                ),
            ),
            FilterHelper::get_filter_options( array( $author ), array( 'filterType' => 'author' ) )
        );
    }

    public function test_get_filter_options_returns_empty_array_for_unknown_filter_type(): void {
        $this->assertSame(
            array(),
            FilterHelper::get_filter_options( array( (object) array( 'slug' => 'x' ) ), array( 'filterType' => 'nope' ) )
        );
    }

    public function test_get_filter_options_applies_options_filter(): void {
        $term       = (object) array(
            'slug' => 'news',
            'name' => 'News',
        );
        $attributes = array(
            'filterType' => 'taxonomy',
            'taxonomy'   => 'category',
        );
        $expected   = array(
            array(
                'value' => 'news',
                'label' => 'News',
                'slug'  => 'news',
                'item'  => $term,
            ),
        );
        $filtered   = array(
            array(
                'value' => 'featured',
                'label' => 'Featured',
            ),
        );

        Filters\expectApplied( 'pikari_gutenberg_query_filter_options' )
            ->once()
            ->with( $expected, $attributes )
            ->andReturn( $filtered );

        $this->assertSame( $filtered, FilterHelper::get_filter_options( array( $term ), $attributes ) );
    }

    /*
     * get_option_classes()
     */

    public function test_get_option_classes_adds_taxonomy_and_slug_class_for_checkbox(): void {
        $attributes = array(
            'filterType'  => 'taxonomy',
            'taxonomy'    => 'category',
            'displayType' => 'checkbox',
        );

        $this->assertSame(
            array( 'wp-block-pikari-gutenberg-query-filter__checkbox-item', 'category_news' ),
            FilterHelper::get_option_classes( array( 'value' => 'news', 'slug' => 'news' ), $attributes )
        );
    }

    public function test_get_option_classes_uses_post_type_key_for_radio(): void {
        $attributes = array(
            'filterType'  => 'post-type',
            'displayType' => 'radio',
        );

        $this->assertSame(
            array( 'wp-block-pikari-gutenberg-query-filter__radio-item', 'post-type_page' ),
            FilterHelper::get_option_classes( array( 'value' => 'page', 'slug' => 'page' ), $attributes )
        );
    }

    public function test_get_option_classes_uses_author_key_and_nicename(): void {
        $attributes = array(
            'filterType'  => 'author',
            'displayType' => 'checkbox',
        );

        $this->assertSame(
            array( 'wp-block-pikari-gutenberg-query-filter__checkbox-item', 'author_jane-doe' ),
            FilterHelper::get_option_classes( array( 'value' => '7', 'slug' => 'jane-doe' ), $attributes )
        );
    }

    public function test_get_option_classes_falls_back_to_value_when_slug_missing(): void {
        $attributes = array(
            'filterType'  => 'taxonomy',
            'taxonomy'    => 'category',
            'displayType' => 'radio',
        );

        $this->assertSame(
            array( 'wp-block-pikari-gutenberg-query-filter__radio-item', 'category_featured' ),
            FilterHelper::get_option_classes( array( 'value' => 'featured' ), $attributes )
        );
    }

    public function test_get_option_classes_omits_unique_class_when_slug_sanitizes_to_empty(): void {
        $attributes = array(
            'filterType'  => 'taxonomy',
            'taxonomy'    => 'category',
            'displayType' => 'checkbox',
        );

        $this->assertSame(
            array( 'wp-block-pikari-gutenberg-query-filter__checkbox-item' ),
            FilterHelper::get_option_classes( array( 'value' => '%e6%97%a5', 'slug' => '%e6%97%a5' ), $attributes )
        );
    }

    public function test_get_option_classes_applies_classes_filter(): void {
        $option     = array(
            'value' => 'news',
            'slug'  => 'news',
        );
        $attributes = array(
            'filterType'  => 'taxonomy',
            'taxonomy'    => 'category',
            'displayType' => 'checkbox',
        );

        Filters\expectApplied( 'pikari_gutenberg_query_filter_option_classes' )
            ->once()
            ->with(
                array( 'wp-block-pikari-gutenberg-query-filter__checkbox-item', 'category_news' ),
                $option,
                $attributes
            )
            ->andReturn( array( 'my-theme-option' ) );

        $this->assertSame( array( 'my-theme-option' ), FilterHelper::get_option_classes( $option, $attributes ) );
    }

    /*
     * get_option_label_html()
     */

    public function test_get_option_label_html_renders_default_text_span(): void {
        Functions\when( 'wp_kses_post' )->returnArg();

        $this->assertSame(
            '<span class="wp-block-pikari-gutenberg-query-filter__radio-text">News</span>',
            FilterHelper::get_option_label_html(
                array(
                    'value' => 'news',
                    'label' => 'News',
                ),
                array( 'displayType' => 'radio' )
            )
        );
    }

    public function test_get_option_label_html_skips_wp_kses_post_for_unfiltered_markup(): void {
        Functions\expect( 'wp_kses_post' )->never();

        $this->assertSame(
            '<span class="wp-block-pikari-gutenberg-query-filter__radio-text">News</span>',
            FilterHelper::get_option_label_html(
                array(
                    'value' => 'news',
                    'label' => 'News',
                ),
                array( 'displayType' => 'radio' )
            )
        );
    }

    public function test_get_option_label_html_escapes_label(): void {
        Functions\when( 'wp_kses_post' )->returnArg();

        $this->assertSame(
            '<span class="wp-block-pikari-gutenberg-query-filter__checkbox-text">A &amp; &lt;B&gt;</span>',
            FilterHelper::get_option_label_html(
                array(
                    'value' => 'a-b',
                    'label' => 'A & <B>',
                ),
                array( 'displayType' => 'checkbox' )
            )
        );
    }

    public function test_get_option_label_html_applies_label_filter(): void {
        Functions\when( 'wp_kses_post' )->returnArg();

        $option     = array(
            'value' => 'news',
            'label' => 'News',
        );
        $attributes = array( 'displayType' => 'checkbox' );

        Filters\expectApplied( 'pikari_gutenberg_query_filter_option_label' )
            ->once()
            ->with(
                '<span class="wp-block-pikari-gutenberg-query-filter__checkbox-text">News</span>',
                $option,
                $attributes
            )
            ->andReturn( '<strong>News</strong> <span class="count">(3)</span>' );

        $this->assertSame(
            '<strong>News</strong> <span class="count">(3)</span>',
            FilterHelper::get_option_label_html( $option, $attributes )
        );
    }

    public function test_get_option_label_html_passes_filtered_markup_through_wp_kses_post(): void {
        Filters\expectApplied( 'pikari_gutenberg_query_filter_option_label' )
            ->once()
            ->andReturn( '<strong>News</strong><script>alert(1)</script>' );

        Functions\expect( 'wp_kses_post' )
            ->once()
            ->with( '<strong>News</strong><script>alert(1)</script>' )
            ->andReturn( '<strong>News</strong>alert(1)' );

        $this->assertSame(
            '<strong>News</strong>alert(1)',
            FilterHelper::get_option_label_html(
                array(
                    'value' => 'news',
                    'label' => 'News',
                ),
                array( 'displayType' => 'checkbox' )
            )
        );
    }
}
