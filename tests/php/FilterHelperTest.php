<?php
/**
 * Tests for FilterHelper option normalization, classes, and label markup.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Pikari\Tests\TestCase;
use Pikari\GutenbergQueryFilter\Helpers\FilterHelper;
use Brain\Monkey\Functions;
use Brain\Monkey\Filters;

class FilterHelperTest extends TestCase {

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

    public function test_get_filter_options_normalizes_authors_with_string_id_value_and_nicename_slug(): void {
        $author = (object) array(
            'ID'            => 7,
            'display_name'  => 'Jane Doe',
            'user_nicename' => 'jane-doe',
        );

        $this->assertSame(
            array(
                array(
                    'value' => '7',
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
