<?php
/**
 * Tests for the URL parameter names of one Query Loop.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Mockery;
use Pikari\GutenbergQueryFilter\Url\QueryParams;
use Pikari\Tests\TestCase;

class QueryParamsTest extends TestCase {

    public function test_custom_loop_keys_carry_the_query_id(): void {
        $params = new QueryParams( 3 );

        $this->assertSame( 'query-3-', $params->prefix() );
        $this->assertSame( 'query-3-post_type', $params->key( 'post_type' ) );
        $this->assertSame( 'query-3-category', $params->key( 'category' ) );
        $this->assertSame( 'query-3-author', $params->key( 'author' ) );
        $this->assertSame( 'query-3-sort', $params->key( 'sort' ) );
        $this->assertSame( 'query-3-s', $params->key( 's' ) );
        $this->assertSame( 'query-3-page', $params->page_key() );
    }

    /**
     * Core uses `query-page` for a loop with no queryId (post-template.php:50),
     * while the plugin's own keys fall back to 0 (spec §3.1).
     */
    public function test_loop_without_query_id_uses_zero_but_cores_page_key(): void {
        $params = new QueryParams( null );

        $this->assertSame( 'query-0-', $params->prefix() );
        $this->assertSame( 'query-0-category', $params->key( 'category' ) );
        $this->assertSame( 'query-page', $params->page_key() );
    }

    public function test_inherited_loop_keys_have_no_query_id(): void {
        $params = new QueryParams( null, true );

        $this->assertTrue( $params->is_inherit() );
        $this->assertSame( 'query-', $params->prefix() );
        $this->assertSame( 'query-category', $params->key( 'category' ) );
        $this->assertSame( 'query-sort', $params->key( 'sort' ) );
        $this->assertSame( 'paged', $params->page_key() );
    }

    /**
     * Inherited loops search with core's own parameter (spec §3.1).
     */
    public function test_inherited_loop_search_uses_cores_parameter(): void {
        $this->assertSame( 's', ( new QueryParams( null, true ) )->key( 's' ) );
    }

    public function test_from_block_reads_the_query_context(): void {
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = array(
            'queryId' => 7,
            'query'   => array( 'inherit' => false ),
        );

        $this->assertSame( 'query-7-', QueryParams::from_block( $block )->prefix() );
    }

    public function test_from_block_treats_a_missing_query_id_as_none(): void {
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = array( 'query' => array( 'inherit' => false ) );

        $this->assertSame( 'query-page', QueryParams::from_block( $block )->page_key() );
    }

    /**
     * An explicit `queryId: 0` is not the same as a missing one: core's own
     * page key depends on this distinction (post-template.php:50), and a
     * future `?:` in place of `?? null` would silently break the first loop
     * on a page.
     */
    public function test_from_block_with_an_explicit_query_id_of_zero_uses_query_0(): void {
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = array(
            'queryId' => 0,
            'query'   => array( 'inherit' => false ),
        );

        $params = QueryParams::from_block( $block );

        $this->assertSame( 'query-0-', $params->prefix() );
        $this->assertSame( 'query-0-page', $params->page_key() );
    }

    public function test_from_block_reads_inherit(): void {
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = array( 'query' => array( 'inherit' => true ) );

        $this->assertTrue( QueryParams::from_block( $block )->is_inherit() );
    }

    public function test_from_block_without_inherit_in_context_treats_the_loop_as_custom(): void {
        $block          = Mockery::mock( 'WP_Block' );
        $block->context = array( 'query' => array() );

        $this->assertFalse( QueryParams::from_block( $block )->is_inherit() );
    }

    public function test_form_id_uses_the_query_id(): void {
        $this->assertSame(
            'pikari-gutenberg-query-filter-form-3',
            ( new QueryParams( 3 ) )->form_id()
        );
    }

    public function test_form_id_falls_back_to_zero_without_a_query_id(): void {
        $this->assertSame(
            'pikari-gutenberg-query-filter-form-0',
            ( new QueryParams( null ) )->form_id()
        );
    }

    public function test_form_id_is_shared_by_every_inherited_loop(): void {
        $this->assertSame(
            'pikari-gutenberg-query-filter-form-inherit',
            ( new QueryParams( null, true ) )->form_id()
        );
    }

    public function test_form_id_ignores_the_query_id_when_inheriting(): void {
        // An inherited loop can still carry a queryId in block context; its
        // parameters and its form are the main query's either way (spec §3.1).
        $this->assertSame(
            'pikari-gutenberg-query-filter-form-inherit',
            ( new QueryParams( 7, true ) )->form_id()
        );
    }

    public function test_form_id_does_not_collide_between_loop_3_and_loop_30(): void {
        // BlockFilters matches the form attribute exactly for this reason
        // (spec §5.2); the IDs themselves must differ as plain strings too.
        $this->assertNotSame(
            ( new QueryParams( 3 ) )->form_id(),
            ( new QueryParams( 30 ) )->form_id()
        );
    }
}
