<?php
/**
 * URL parameter names for one Query Loop.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Url;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Owns every URL parameter name for one Query Loop.
 *
 * Custom loops namespace their parameters with the block's queryId
 * (`query-3-category`), falling back to `query-0-` when the loop has no
 * queryId. Inherited loops share the main query's own parameters
 * (`query-category`, and core's `s` and `paged`).
 */
class QueryParams {

    /**
     * The block's queryId, or null when the loop has none.
     *
     * @var int|null
     */
    private ?int $query_id;

    /**
     * Whether the loop inherits the main query.
     *
     * @var bool
     */
    private bool $inherit;

    /**
     * Constructor.
     *
     * @param int|null $query_id The block's queryId, or null when the loop has none.
     * @param bool     $inherit  Whether the loop inherits the main query.
     */
    public function __construct( ?int $query_id, bool $inherit = false ) {
        $this->query_id = null === $query_id ? null : absint( $query_id );
        $this->inherit  = $inherit;
    }

    /**
     * Build a QueryParams from a Query Loop block's context.
     *
     * @param \WP_Block $block Query Loop block instance.
     * @return self
     */
    public static function from_block( \WP_Block $block ): self {
        $query_id = $block->context['queryId'] ?? null;
        $inherit  = $block->context['query']['inherit'] ?? false;

        return new self( $query_id, $inherit );
    }

    /**
     * Get the prefix shared by every parameter name for this loop.
     *
     * @return string `query-3-`, `query-0-`, or `query-` when inherited.
     */
    public function prefix(): string {
        if ( $this->inherit ) {
            return 'query-';
        }

        return sprintf( 'query-%d-', $this->query_id ?? 0 );
    }

    /**
     * Get the URL parameter name for a filter.
     *
     * @param string $name `post_type`, `author`, `sort`, `s`, or a taxonomy name.
     * @return string The parameter name.
     */
    public function key( string $name ): string {
        if ( $this->inherit && 's' === $name ) {
            return 's';
        }

        return $this->prefix() . $name;
    }

    /**
     * Get the URL parameter name for the current page number.
     *
     * @return string `paged` when inherited, `query-page` when the loop has
     *                no queryId, otherwise `query-3-page`.
     */
    public function page_key(): string {
        if ( $this->inherit ) {
            return 'paged';
        }

        if ( null === $this->query_id ) {
            return 'query-page';
        }

        return $this->prefix() . 'page';
    }

    /**
     * Get the id of the loop's hidden filter form.
     *
     * Every control that filters this loop carries it as a `form` attribute,
     * and BlockFilters injects a form with this id when it finds one
     * (spec §5.2). Inherited loops share a single id, as they share a single
     * set of parameters.
     *
     * @return string `pikari-gutenberg-query-filter-form-3`, `…-form-0`, or
     *                `…-form-inherit`.
     */
    public function form_id(): string {
        if ( $this->inherit ) {
            return 'pikari-gutenberg-query-filter-form-inherit';
        }

        return sprintf( 'pikari-gutenberg-query-filter-form-%d', $this->query_id ?? 0 );
    }

    /**
     * Whether the loop inherits the main query.
     *
     * @return bool
     */
    public function is_inherit(): bool {
        return $this->inherit;
    }
}
