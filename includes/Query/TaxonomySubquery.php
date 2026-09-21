<?php
/**
 * Turns taxonomy filter values into a WHERE subquery fragment.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Query;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds a WHERE fragment that filters posts by taxonomy term, without
 * touching the tax_query query var.
 *
 * On an archive page, handing filter terms to the main query's tax_query
 * makes WordPress think the page *is* that term — wrong title, wrong
 * template, and an unknown slug turns a valid archive into a 404. Generating
 * SQL with a fresh WP_Tax_Query doesn't work either: its first IN clause
 * joins the term-relationships table with no alias, and the archive's own
 * clause already did, so MySQL errors. A subquery avoids both: it adds no
 * join, so the archive's queried object never changes and there is no
 * duplicate alias to collide with.
 */
class TaxonomySubquery {

    /**
     * Build the WHERE fragment for a set of taxonomy filters.
     *
     * Multiple taxonomies are ANDed: a post must match every one of them.
     * A taxonomy whose slugs resolve to no term must match no posts, so the
     * whole fragment becomes "AND 1=0" rather than silently dropping that
     * taxonomy's condition.
     *
     * @param array<string, string[]> $taxonomies Term slugs keyed by taxonomy
     *                                             name, as FilterState::taxonomies() returns.
     * @return string SQL to append to a WHERE clause, starting with a space,
     *                or '' when there is nothing to add.
     */
    public static function where( array $taxonomies ): string {
        if ( empty( $taxonomies ) ) {
            return '';
        }

        global $wpdb;

        $fragments = array();

        foreach ( $taxonomies as $taxonomy => $slugs ) {
            $term_taxonomy_ids = self::resolve_term_taxonomy_ids( $taxonomy, $slugs );

            if ( empty( $term_taxonomy_ids ) ) {
                return ' AND 1=0';
            }

            // $term_taxonomy_ids are cast to int in resolve_term_taxonomy_ids(),
            // so wpdb->prepare() isn't needed here.
            $fragments[] = sprintf(
                ' AND %s.ID IN ( SELECT object_id FROM %s WHERE term_taxonomy_id IN (%s) )',
                $wpdb->posts,
                $wpdb->term_relationships,
                implode( ',', $term_taxonomy_ids )
            );
        }

        return implode( '', $fragments );
    }

    /**
     * Resolve one taxonomy's slugs to term_taxonomy_id values.
     *
     * For a hierarchical taxonomy, each matched term's children are included
     * too, matching core's include_children default. get_term_children()
     * returns term_ids, a different ID space than term_taxonomy_id, so the
     * children are re-resolved through get_terms() rather than mixed
     * straight into the term_taxonomy_id list.
     *
     * @param string   $taxonomy Taxonomy name.
     * @param string[] $slugs    Term slugs.
     * @return int[] De-duplicated term_taxonomy_ids. Empty when no slug matched a term.
     */
    private static function resolve_term_taxonomy_ids( string $taxonomy, array $slugs ): array {
        $terms = get_terms(
            array(
                'taxonomy'   => $taxonomy,
                'slug'       => $slugs,
                'hide_empty' => false,
            )
        );

        // get_terms() returns a WP_Error for an invalid taxonomy; treat that
        // the same as no matching terms rather than iterating its properties.
        $terms = is_array( $terms ) ? $terms : array();

        $ids            = array();
        $child_term_ids = array();

        foreach ( $terms as $term ) {
            $ids[] = (int) $term->term_taxonomy_id;

            if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
                continue;
            }

            $children = get_term_children( $term->term_id, $taxonomy );

            foreach ( is_array( $children ) ? $children : array() as $child_term_id ) {
                $child_term_ids[] = (int) $child_term_id;
            }
        }

        if ( ! empty( $child_term_ids ) ) {
            $child_terms = get_terms(
                array(
                    'taxonomy'   => $taxonomy,
                    'include'    => array_values( array_unique( $child_term_ids ) ),
                    'hide_empty' => false,
                )
            );

            foreach ( is_array( $child_terms ) ? $child_terms : array() as $child_term ) {
                $ids[] = (int) $child_term->term_taxonomy_id;
            }
        }

        return array_values( array_unique( $ids ) );
    }
}
