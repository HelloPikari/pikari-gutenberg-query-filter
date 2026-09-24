<?php
/**
 * Tests for ResultCount: each Query Loop's total, recorded by form id.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Functions;
use Mockery;
use Pikari\GutenbergQueryFilter\Query\ResultCount;
use Pikari\Tests\TestCase;

class ResultCountTest extends TestCase {

    protected function tearDown(): void {
        ResultCount::reset();
        parent::tearDown();
    }

    /**
     * A WP_Query stand-in whose get() answers for the loop var and for
     * no_found_rows, carrying its own found_posts, the way the_posts sees it.
     *
     * @param mixed $form_id      Value of the loop var, or null when absent.
     * @param int   $found_posts  The query's found_posts total.
     * @param bool  $no_found_rows Value of the query's no_found_rows flag.
     * @return \WP_Query
     */
    private function query( $form_id, int $found_posts = 12, bool $no_found_rows = false ): \WP_Query {
        $query = Mockery::mock( 'WP_Query' );
        $query->shouldReceive( 'get' )
            ->with( ResultCount::QUERY_VAR )
            ->andReturn( $form_id ?? '' );
        $query->shouldReceive( 'get' )
            ->with( 'no_found_rows' )
            ->andReturn( $no_found_rows );
        $query->found_posts = $found_posts;

        return $query;
    }

    public function test_record_stores_the_total_against_the_form_id(): void {
        ResultCount::record( array( 'a', 'b' ), $this->query( 'pikari-gutenberg-query-filter-form-3', 12 ) );

        $this->assertSame( 12, ResultCount::for_form( 'pikari-gutenberg-query-filter-form-3' ) );
    }

    public function test_record_returns_the_posts_unchanged(): void {
        $posts = array( 'a', 'b' );

        $this->assertSame( $posts, ResultCount::record( $posts, $this->query( 'pikari-gutenberg-query-filter-form-3' ) ) );
    }

    public function test_zero_result_query_records_zero(): void {
        ResultCount::record( array(), $this->query( 'pikari-gutenberg-query-filter-form-3', 0 ) );

        $this->assertSame( 0, ResultCount::for_form( 'pikari-gutenberg-query-filter-form-3' ) );
    }

    public function test_record_ignores_a_no_found_rows_query(): void {
        ResultCount::record( array( 'a', 'b' ), $this->query( 'pikari-gutenberg-query-filter-form-3', 0, true ) );

        $this->assertNull( ResultCount::for_form( 'pikari-gutenberg-query-filter-form-3' ) );
    }

    public function test_record_ignores_a_query_without_the_loop_var(): void {
        ResultCount::record( array(), $this->query( null, 99 ) );

        $this->assertNull( ResultCount::for_form( '' ) );
    }

    public function test_record_ignores_something_that_is_not_a_query(): void {
        $posts = array( 'a' );

        $this->assertSame( $posts, ResultCount::record( $posts, null ) );
    }

    public function test_the_last_run_of_a_loop_wins(): void {
        ResultCount::record( array(), $this->query( 'pikari-gutenberg-query-filter-form-3', 12 ) );
        ResultCount::record( array(), $this->query( 'pikari-gutenberg-query-filter-form-3', 7 ) );

        $this->assertSame( 7, ResultCount::for_form( 'pikari-gutenberg-query-filter-form-3' ) );
    }

    public function test_two_loops_keep_separate_totals(): void {
        ResultCount::record( array(), $this->query( 'pikari-gutenberg-query-filter-form-3', 12 ) );
        ResultCount::record( array(), $this->query( 'pikari-gutenberg-query-filter-form-4', 4 ) );

        $this->assertSame( 12, ResultCount::for_form( 'pikari-gutenberg-query-filter-form-3' ) );
        $this->assertSame( 4, ResultCount::for_form( 'pikari-gutenberg-query-filter-form-4' ) );
    }

    public function test_for_form_is_null_for_an_unrecorded_loop(): void {
        $this->assertNull( ResultCount::for_form( 'pikari-gutenberg-query-filter-form-9' ) );
    }

    public function test_reset_forgets_every_total(): void {
        ResultCount::record( array(), $this->query( 'pikari-gutenberg-query-filter-form-3', 12 ) );
        ResultCount::reset();

        $this->assertNull( ResultCount::for_form( 'pikari-gutenberg-query-filter-form-3' ) );
    }

    public function test_message_for_no_results(): void {
        Functions\stubTranslationFunctions();

        $this->assertSame( 'No results found', ResultCount::message( 0 ) );
    }

    public function test_message_for_one_result_is_singular(): void {
        Functions\stubTranslationFunctions();
        Functions\when( 'number_format_i18n' )->alias( 'strval' );

        $this->assertSame( '1 result found', ResultCount::message( 1 ) );
    }

    public function test_message_for_several_results_is_plural_and_localized(): void {
        Functions\stubTranslationFunctions();
        Functions\when( 'number_format_i18n' )->justReturn( '1,234' );

        $this->assertSame( '1,234 results found', ResultCount::message( 1234 ) );
    }
}
