<?php
/**
 * Tests for turning taxonomy filter values into a WHERE subquery fragment.
 *
 * Every rule pinned here matches the 1.0 spec, section 4.4.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Brain\Monkey\Functions;
use Mockery;
use Pikari\GutenbergQueryFilter\Query\TaxonomySubquery;
use Pikari\Tests\TestCase;

class TaxonomySubqueryTest extends TestCase {

    /**
     * The $wpdb global's value before this test replaced it, restored in
     * tearDown().
     *
     * @var mixed
     */
    private $original_wpdb;

    protected function setUp(): void {
        parent::setUp();

        global $wpdb;
        $this->original_wpdb = $wpdb;

        $wpdb                     = Mockery::mock( 'wpdb' );
        $wpdb->posts              = 'wp_posts';
        $wpdb->term_relationships = 'wp_term_relationships';

        // Most taxonomies in these tests are not hierarchical; individual
        // tests override this where they need child terms included.
        Functions\when( 'is_taxonomy_hierarchical' )->justReturn( false );
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;

        parent::tearDown();
    }

    /**
     * Build a minimal term object, as get_terms() would return.
     *
     * @param int        $term_id          Term ID.
     * @param int|string $term_taxonomy_id Term taxonomy ID.
     * @return object
     */
    private function term( int $term_id, $term_taxonomy_id ): object {
        return (object) array(
            'term_id'          => $term_id,
            'term_taxonomy_id' => $term_taxonomy_id,
        );
    }

    /**
     * Compare two SQL fragments with whitespace normalized, so exact
     * formatting isn't pinned.
     *
     * @param string $expected Expected SQL fragment.
     * @param string $actual   Actual SQL fragment.
     */
    private function assertSqlEquals( string $expected, string $actual ): void {
        $normalize = static function ( string $sql ): string {
            return trim( preg_replace( '/\s+/', ' ', $sql ) );
        };

        $this->assertSame( $normalize( $expected ), $normalize( $actual ) );
    }

    public function test_no_taxonomies_produce_no_sql(): void {
        $this->assertSame( '', TaxonomySubquery::where( array() ) );
    }

    public function test_one_taxonomy_becomes_an_id_in_subquery(): void {
        Functions\when( 'get_terms' )->justReturn(
            array(
                $this->term( 1, 11 ),
                $this->term( 2, 12 ),
            )
        );

        $result = TaxonomySubquery::where( array( 'category' => array( 'news', 'events' ) ) );

        $this->assertSqlEquals(
            'AND wp_posts.ID IN ( SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (11,12) )',
            $result
        );
        // The posts_where callback in MainQueryFilter relies on this fragment
        // starting with a space so it can append directly to core's WHERE
        // clause; assertSqlEquals() normalizes whitespace, so it can't catch
        // that contract dropping.
        $this->assertStringStartsWith( ' AND', $result );
    }

    public function test_two_taxonomies_produce_two_and_clauses(): void {
        Functions\when( 'get_terms' )->alias(
            function ( array $args ) {
                if ( 'category' === $args['taxonomy'] ) {
                    return array( $this->term( 1, 11 ) );
                }

                return array( $this->term( 2, 21 ) );
            }
        );

        $result = TaxonomySubquery::where(
            array(
                'category' => array( 'news' ),
                'post_tag' => array( 'featured' ),
            )
        );

        $this->assertSqlEquals(
            'AND wp_posts.ID IN ( SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (11) )'
            . ' AND wp_posts.ID IN ( SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (21) )',
            $result
        );
    }

    /**
     * get_term_children() returns term_ids, a different ID space than
     * term_taxonomy_id, so the stub here returns child term_ids (2, 3) that
     * are deliberately unlike their term_taxonomy_ids (12, 13). If the class
     * mixed the two ID spaces instead of re-resolving children through
     * get_terms(), this test would see (11,2,3) instead.
     */
    public function test_hierarchical_taxonomies_include_child_terms(): void {
        Functions\when( 'is_taxonomy_hierarchical' )->justReturn( true );
        Functions\when( 'get_terms' )->alias(
            function ( array $args ) {
                if ( isset( $args['slug'] ) ) {
                    return array( $this->term( 1, 11 ) );
                }

                return array(
                    $this->term( 2, 12 ),
                    $this->term( 3, 13 ),
                );
            }
        );
        Functions\when( 'get_term_children' )->justReturn( array( 2, 3 ) );

        $result = TaxonomySubquery::where( array( 'category' => array( 'news' ) ) );

        $this->assertSqlEquals(
            'AND wp_posts.ID IN ( SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (11,12,13) )',
            $result
        );
    }

    public function test_non_hierarchical_taxonomies_do_not_look_up_children(): void {
        Functions\when( 'get_terms' )->justReturn( array( $this->term( 1, 11 ) ) );
        Functions\expect( 'get_term_children' )->never();

        $result = TaxonomySubquery::where( array( 'post_tag' => array( 'featured' ) ) );

        $this->assertSqlEquals(
            'AND wp_posts.ID IN ( SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (11) )',
            $result
        );
    }

    public function test_slugs_that_match_no_term_match_no_posts(): void {
        Functions\when( 'get_terms' )->justReturn( array() );

        $result = TaxonomySubquery::where( array( 'category' => array( 'nonexistent' ) ) );

        $this->assertSqlEquals( 'AND 1=0', $result );
    }

    /**
     * Both term_taxonomy_id values carry injected SQL rather than a clean
     * numeric string. If either (int) cast in
     * TaxonomySubquery::resolve_term_taxonomy_ids() were removed, the
     * injected text would land straight in the fragment and this assertion
     * would fail — a plain '11' fixture can't tell the difference, since it
     * renders the same whether or not it's cast.
     */
    public function test_ids_are_cast_to_integers(): void {
        Functions\when( 'get_terms' )->justReturn(
            array(
                $this->term( 1, '11 OR 1=1' ),
                $this->term( 2, '12; DROP TABLE wp_posts' ),
            )
        );

        $result = TaxonomySubquery::where( array( 'category' => array( 'news', 'events' ) ) );

        $this->assertSqlEquals(
            'AND wp_posts.ID IN ( SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id IN (11,12) )',
            $result
        );
    }

    /**
     * A hierarchical taxonomy whose slug matches no term has no parent term
     * to look up children for, so it must still produce the no-posts
     * fragment rather than skip the taxonomy's condition entirely.
     */
    public function test_hierarchical_taxonomy_with_no_matching_term_matches_no_posts(): void {
        Functions\when( 'is_taxonomy_hierarchical' )->justReturn( true );
        Functions\when( 'get_terms' )->justReturn( array() );
        Functions\expect( 'get_term_children' )->never();

        $result = TaxonomySubquery::where( array( 'category' => array( 'nonexistent' ) ) );

        $this->assertSqlEquals( 'AND 1=0', $result );
    }
}
