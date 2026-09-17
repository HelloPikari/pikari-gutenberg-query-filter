<?php
/**
 * Tests for the sort option list, which is also the request allowlist.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Pikari\GutenbergQueryFilter\Query\SortOptions;
use Pikari\Tests\TestCase;

class SortOptionsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        Functions\stubTranslationFunctions();
        Functions\when( 'sanitize_key' )->alias(
            function ( $key ) {
                return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
            }
        );
    }

    public function test_default_options_are_the_four_date_and_title_orders(): void {
        $this->assertSame(
            array( 'date-desc', 'date-asc', 'title-asc', 'title-desc' ),
            array_column( SortOptions::all(), 'key' )
        );
    }

    public function test_default_options_carry_orderby_and_uppercase_order(): void {
        $option = SortOptions::find( 'title-asc' );

        $this->assertSame( 'title', $option['orderby'] );
        $this->assertSame( 'ASC', $option['order'] );
        $this->assertNotSame( '', $option['label'] );
    }

    public function test_options_are_filterable(): void {
        Filters\expectApplied( 'pikari_gutenberg_query_filter_sort_options' )->once();

        SortOptions::all();
        $this->addToAssertionCount( 1 );
    }

    public function test_a_filtered_option_becomes_findable(): void {
        Filters\expectApplied( 'pikari_gutenberg_query_filter_sort_options' )->andReturnUsing(
            function ( array $options ) {
                $options[] = array(
                    'key'     => 'menu-order',
                    'label'   => 'Menu order',
                    'orderby' => 'menu_order',
                    'order'   => 'asc',
                );

                return $options;
            }
        );

        $this->assertSame( 'menu_order', SortOptions::find( 'menu-order' )['orderby'] );
        $this->assertSame( 'ASC', SortOptions::find( 'menu-order' )['order'] );
    }

    /**
     * @dataProvider provide_invalid_options
     *
     * @param array $option Option a filter added.
     */
    public function test_invalid_options_are_dropped( array $option ): void {
        Filters\expectApplied( 'pikari_gutenberg_query_filter_sort_options' )->andReturnUsing(
            function ( array $options ) use ( $option ) {
                $options[] = $option;

                return $options;
            }
        );

        $this->assertCount( 4, SortOptions::all() );
    }

    /**
     * @return array<string, array{array}>
     */
    public static function provide_invalid_options(): array {
        return array(
            'no key'                      => array( array( 'label' => 'x', 'orderby' => 'date' ) ),
            'no label'                    => array( array( 'key' => 'x', 'orderby' => 'date' ) ),
            'no orderby'                  => array( array( 'key' => 'x', 'label' => 'x' ) ),
            'meta_value without meta_key' => array( array( 'key' => 'x', 'label' => 'x', 'orderby' => 'meta_value' ) ),
            'meta_value_num, no key'      => array( array( 'key' => 'x', 'label' => 'x', 'orderby' => 'meta_value_num' ) ),
        );
    }

    public function test_a_meta_option_with_a_meta_key_survives(): void {
        Filters\expectApplied( 'pikari_gutenberg_query_filter_sort_options' )->andReturnUsing(
            function ( array $options ) {
                $options[] = array(
                    'key'      => 'price',
                    'label'    => 'Price',
                    'orderby'  => 'meta_value_num',
                    'order'    => 'asc',
                    'meta_key' => 'price',
                );

                return $options;
            }
        );

        $this->assertSame( 'price', SortOptions::find( 'price' )['meta_key'] );
    }

    public function test_unknown_keys_are_not_found(): void {
        $this->assertNull( SortOptions::find( 'rand' ) );
        $this->assertNull( SortOptions::find( '' ) );
    }

    public function test_match_finds_the_option_for_a_loops_own_order(): void {
        $this->assertSame( 'date-desc', SortOptions::match( 'date', 'desc' )['key'] );
        $this->assertSame( 'title-asc', SortOptions::match( 'title', 'ASC' )['key'] );
    }

    public function test_match_returns_null_when_nothing_matches(): void {
        $this->assertNull( SortOptions::match( 'relevance', 'DESC' ) );
        $this->assertNull( SortOptions::match( '', '' ) );
    }
}
