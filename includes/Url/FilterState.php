<?php
/**
 * Parsed and validated filter parameters for one Query Loop.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Url;

use Pikari\GutenbergQueryFilter\Query\SortOptions;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parses and validates a request's filter parameters for one Query Loop.
 *
 * Every value rule here matches the 1.0 spec, section 3.2. This class never
 * touches WP_Query arguments; see Query\QueryArgs for the merge.
 */
class FilterState {

    /**
     * Per-prefix memo built by for_loop().
     *
     * Core builds a custom loop's query vars up to 6 times per page, so the
     * request is only parsed once per loop (spec §4.1).
     *
     * @var array<string, self>
     */
    private static array $cache = array();

    /**
     * Filtered post type names.
     *
     * @var string[]
     */
    private array $post_types = array();

    /**
     * Filtered term slugs, keyed by taxonomy name.
     *
     * @var array<string, string[]>
     */
    private array $taxonomies = array();

    /**
     * Resolved author IDs.
     *
     * Null when the parameter is absent, array( 0 ) when it is present but
     * resolved to nobody.
     *
     * @var int[]|null
     */
    private ?array $author_ids = null;

    /**
     * Sanitized search term, empty when absent.
     *
     * @var string
     */
    private string $search = '';

    /**
     * Sort option matched from the allowlist, null when absent or unknown.
     *
     * @var array|null
     */
    private ?array $sort = null;

    /**
     * Private constructor. Build through from_array() or for_loop().
     */
    private function __construct() {
    }

    /**
     * Parse and validate a Query Loop's filter parameters from a $_GET-shaped array.
     *
     * @param array       $get    URL parameters.
     * @param QueryParams $params Parameter names for this loop.
     * @return self
     */
    public static function from_array( array $get, QueryParams $params ): self {
        $state = new self();

        $state->post_types = self::resolve_post_types( $get, $params );
        $state->taxonomies = self::resolve_taxonomies( $get, $params );
        $state->author_ids = self::resolve_author_ids( $get, $params );
        $state->search     = self::resolve_search( $get, $params );
        $state->sort       = self::resolve_sort( $get, $params );

        return $state;
    }

    /**
     * Get the memoized filter state for a loop, reading $_GET once per prefix.
     *
     * @param QueryParams $params Parameter names for this loop.
     * @return self
     */
    public static function for_loop( QueryParams $params ): self {
        $prefix = $params->prefix();

        if ( ! isset( self::$cache[ $prefix ] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtering parameters don't require nonces; values are sanitized in from_array().
            self::$cache[ $prefix ] = self::from_array( $_GET, $params );
        }

        return self::$cache[ $prefix ];
    }

    /**
     * Clear the for_loop() memo. For tests.
     */
    public static function reset_cache(): void {
        self::$cache = array();
    }

    /**
     * Get the filtered post type names.
     *
     * @return string[]
     */
    public function post_types(): array {
        return $this->post_types;
    }

    /**
     * Get filtered term slugs, keyed by taxonomy name.
     *
     * @return array<string, string[]>
     */
    public function taxonomies(): array {
        return $this->taxonomies;
    }

    /**
     * Get resolved author IDs.
     *
     * @return int[]|null Null when the parameter is absent, array( 0 ) when
     *                     it is present but resolved to nobody.
     */
    public function author_ids(): ?array {
        return $this->author_ids;
    }

    /**
     * Get the sanitized search term.
     *
     * @return string Empty when absent.
     */
    public function search(): string {
        return $this->search;
    }

    /**
     * Get the matched sort option.
     *
     * @return array|null Null when absent or unknown.
     */
    public function sort(): ?array {
        return $this->sort;
    }

    /**
     * Whether any filter is set.
     *
     * @return bool
     */
    public function has_filters(): bool {
        return ! empty( $this->post_types )
        || ! empty( $this->taxonomies )
        || null !== $this->author_ids
        || '' !== $this->search
        || null !== $this->sort;
    }

    /**
     * Read a parameter's values.
     *
     * Accepts a comma-separated string or a key[] array (spec §3.2). Both
     * are trimmed, emptied of blank entries, deduplicated, and capped at 50.
     *
     * @param array  $get URL parameters.
     * @param string $key Parameter name.
     * @return string[]
     */
    private static function read_values( array $get, string $key ): array {
        if ( ! isset( $get[ $key ] ) ) {
            return array();
        }

        $raw = $get[ $key ];

        if ( is_array( $raw ) ) {
            $values = array_map(
                static function ( $value ) {
                    return sanitize_text_field( wp_unslash( (string) $value ) );
                },
                $raw
            );
        } else {
            $values = explode( ',', sanitize_text_field( wp_unslash( (string) $raw ) ) );
        }

        $values = array_map( 'trim', $values );
        $values = array_filter(
            $values,
            static function ( $value ) {
                return '' !== $value;
            }
        );
        $values = array_unique( $values );
        $values = array_slice( $values, 0, 50 );

        return array_values( $values );
    }

    /**
     * Resolve the post_type parameter (spec §3.2).
     *
     * @param array       $get    URL parameters.
     * @param QueryParams $params Parameter names for this loop.
     * @return string[]
     */
    private static function resolve_post_types( array $get, QueryParams $params ): array {
        $values = self::read_values( $get, $params->key( 'post_type' ) );

        $post_types = array_filter(
            $values,
            static function ( $post_type ) {
                if ( ! is_post_type_viewable( $post_type ) ) {
                    return false;
                }

                if ( 'attachment' === $post_type && ! get_option( 'wp_attachment_pages_enabled' ) ) {
                    return false;
                }

                return true;
            }
        );

        return array_values( $post_types );
    }

    /**
     * Resolve every viewable taxonomy's parameter (spec §3.2).
     *
     * @param array       $get    URL parameters.
     * @param QueryParams $params Parameter names for this loop.
     * @return array<string, string[]>
     */
    private static function resolve_taxonomies( array $get, QueryParams $params ): array {
        $taxonomies = array();

        foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $taxonomy ) {
            if ( ! is_taxonomy_viewable( $taxonomy ) ) {
                continue;
            }

            $values = self::read_values( $get, $params->key( $taxonomy ) );

            if ( empty( $values ) ) {
                continue;
            }

            $slugs = array_map( 'sanitize_title_for_query', $values );
            $slugs = array_values(
                array_filter(
                    $slugs,
                    static function ( $slug ) {
                        return '' !== (string) $slug;
                    }
                )
            );

            if ( empty( $slugs ) ) {
                continue;
            }

            $taxonomies[ $taxonomy ] = $slugs;
        }

        return $taxonomies;
    }

    /**
     * Resolve the author parameter to user IDs (spec §3.2).
     *
     * At most two get_users() calls: nicename__in for every value, then
     * include for the all-digit values that didn't match a nicename. A
     * nicename match wins over an ID match sharing the same digits.
     *
     * @param array       $get    URL parameters.
     * @param QueryParams $params Parameter names for this loop.
     * @return int[]|null Null when absent, array( 0 ) when nothing resolved.
     */
    private static function resolve_author_ids( array $get, QueryParams $params ): ?array {
        $values = self::read_values( $get, $params->key( 'author' ) );

        if ( empty( $values ) ) {
            return null;
        }

        $blog_id = get_current_blog_id();

        $by_nicename      = array();
        $nicename_matches = get_users(
            array(
                'blog_id'      => $blog_id,
                'nicename__in' => $values,
                'fields'       => array( 'ID', 'user_nicename' ),
                'number'       => 50,
            )
        );

        foreach ( $nicename_matches as $user ) {
            $by_nicename[ (string) $user->user_nicename ] = (int) $user->ID;
        }

        $unmatched_digits = array_values(
            array_filter(
                $values,
                static function ( $value ) use ( $by_nicename ) {
                    return ! isset( $by_nicename[ $value ] ) && ctype_digit( (string) $value );
                }
            )
        );

        $by_id = array();

        if ( ! empty( $unmatched_digits ) ) {
            $id_matches = get_users(
                array(
                    'blog_id' => $blog_id,
                    'include' => array_map( 'absint', $unmatched_digits ),
                    'fields'  => 'ID',
                    'number'  => 50,
                )
            );

            foreach ( $id_matches as $id ) {
                $by_id[ (string) absint( $id ) ] = (int) $id;
            }
        }

        $author_ids = array();

        foreach ( $values as $value ) {
            if ( isset( $by_nicename[ $value ] ) ) {
                $author_ids[] = $by_nicename[ $value ];
            } elseif ( ctype_digit( (string) $value ) && isset( $by_id[ (string) absint( $value ) ] ) ) {
                $author_ids[] = $by_id[ (string) absint( $value ) ];
            }
        }

        $author_ids = array_values( array_unique( $author_ids ) );

        return empty( $author_ids ) ? array( 0 ) : $author_ids;
    }

    /**
     * Resolve the search parameter (spec §3.2).
     *
     * @param array       $get    URL parameters.
     * @param QueryParams $params Parameter names for this loop.
     * @return string Empty when absent.
     */
    private static function resolve_search( array $get, QueryParams $params ): string {
        $key = $params->key( 's' );

        if ( ! isset( $get[ $key ] ) ) {
            return '';
        }

        return sanitize_text_field( wp_unslash( $get[ $key ] ) );
    }

    /**
     * Resolve the sort parameter against the allowlist (spec §3.2).
     *
     * @param array       $get    URL parameters.
     * @param QueryParams $params Parameter names for this loop.
     * @return array|null Null when absent or unknown.
     */
    private static function resolve_sort( array $get, QueryParams $params ): ?array {
        $key = $params->key( 'sort' );
        $raw = isset( $get[ $key ] ) ? (string) $get[ $key ] : '';

        return SortOptions::find( $raw );
    }
}
