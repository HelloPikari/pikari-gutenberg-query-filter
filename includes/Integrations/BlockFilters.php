<?php
/**
 * Core Block Filters
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Integrations;

use Pikari\GutenbergQueryFilter\Url\QueryParams;
use Pikari\GutenbergQueryFilter\Url\LoopForm;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles modifications to WordPress core blocks.
 */
class BlockFilters {

    /**
     * Unique IDs reserved for each Query block's inner blocks. See reserve_unique_ids().
     */
    private const UNIQUE_ID_RESERVE = 1000;

    /**
     * Parsed block key holding the unique ID counters recorded by reserve_unique_ids().
     */
    private const UNIQUE_ID_START_KEY = 'pikariGutenbergQueryFilterUniqueIdStart';

    /**
     * Initialize the block filters.
     */
    public function __construct() {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     */
    private function register_hooks(): void {
        // Modify core block metadata during registration.
        // Try both possible filter names to ensure we catch it
        add_filter( 'block_type_metadata', array( $this, 'modify_block_metadata' ), 10, 2);
        add_filter( 'block_type_metadata_settings', array( $this, 'modify_block_metadata' ), 10, 2 );

        // Version this plugin's block assets with the plugin release.
        add_filter( 'block_type_metadata', array( $this, 'set_plugin_block_version' ) );

        // Modify search block rendering to add query context.
        add_filter( 'render_block_core/search', array( $this, 'render_block_search' ), 20, 3 );

        // Modify query block rendering to add data attributes.
        add_filter( 'render_block_core/query', array( $this, 'render_block_query' ), 20, 3 );

        // Keep unique IDs after a Query block stable across filtered results.
        add_filter( 'render_block_data', array( $this, 'reserve_unique_ids' ) );
    }

    /**
     * Modify block metadata to add custom functionality.
     *
     * @param array $settings_or_metadata Settings array or metadata array (depends on filter).
     * @param array $metadata Block metadata (only for block_type_metadata_settings filter).
     * @return array Modified settings or metadata.
     */
    public function modify_block_metadata( $settings_or_metadata, $metadata = null ): array {
        // Determine which filter called us and extract the correct data
        if ( $metadata === null ) {
            // Called by 'block_type_metadata' - only metadata passed
            $metadata = $settings_or_metadata;
            $settings = $metadata; // In this case, we modify and return the metadata directly
            $is_settings_filter = false;
        } else {
            // Called by 'block_type_metadata_settings' - settings and metadata passed
            $settings = $settings_or_metadata;
            $is_settings_filter = true;
        }

        // Add context to both search and query blocks.
        if ( isset( $metadata['name'] ) && ( 'core/search' === $metadata['name'] || 'core/query' === $metadata['name'] ) ) {

            // Handle Search block - add usesContext
            if ( 'core/search' === $metadata['name'] ) {
                // Ensure usesContext array exists.
                if ( ! isset( $metadata['usesContext'] ) ) {
                    $metadata['usesContext'] = array();
                }

                // Add queryId and query to usesContext if not already present.
                if ( ! in_array( 'queryId', $metadata['usesContext'], true ) ) {
                    $metadata['usesContext'][] = 'queryId';
                }
                if ( ! in_array( 'query', $metadata['usesContext'], true ) ) {
                    $metadata['usesContext'][] = 'query';
                }
            }

            // Handle Query block - add providesContext
            if ( 'core/query' === $metadata['name'] ) {
                // Ensure providesContext array exists.
                if ( ! isset( $metadata['providesContext'] ) ) {
                    $metadata['providesContext'] = array();
                }

                // Add context provision mapping.
                $metadata['providesContext']['queryId'] = 'queryId';
                $metadata['providesContext']['query'] = 'query';

            }

            // Apply changes to the appropriate structure based on filter type
            if ( $is_settings_filter ) {
                // Search block - apply usesContext
                if ( 'core/search' === $metadata['name'] ) {
                    if ( ! isset( $settings['uses_context'] ) ) {
                        $settings['uses_context'] = array();
                    }

                    if ( ! in_array( 'queryId', $settings['uses_context'], true ) ) {
                        $settings['uses_context'][] = 'queryId';
                    }
                    if ( ! in_array( 'query', $settings['uses_context'], true ) ) {
                        $settings['uses_context'][] = 'query';
                    }
                }

                // Query block - apply providesContext
                if ( 'core/query' === $metadata['name'] ) {
                    if ( ! isset( $settings['provides_context'] ) ) {
                        $settings['provides_context'] = array();
                    }

                    $settings['provides_context']['queryId'] = 'queryId';
                    $settings['provides_context']['query'] = 'query';

                }
            } else {
                // Search block - apply usesContext
                if ( 'core/search' === $metadata['name'] ) {
                    if ( ! isset( $settings['usesContext'] ) ) {
                        $settings['usesContext'] = array();
                    }

                    if ( ! in_array( 'queryId', $settings['usesContext'], true ) ) {
                        $settings['usesContext'][] = 'queryId';
                    }
                    if ( ! in_array( 'query', $settings['usesContext'], true ) ) {
                        $settings['usesContext'][] = 'query';
                    }
                }

                // Query block - apply providesContext
                if ( 'core/query' === $metadata['name'] ) {
                    if ( ! isset( $settings['providesContext'] ) ) {
                        $settings['providesContext'] = array();
                    }

                    $settings['providesContext']['queryId'] = 'queryId';
                    $settings['providesContext']['query'] = 'query';

                }
            }
        }

        return $settings;
    }


    /**
     * Set this plugin's block versions to the plugin version.
     *
     * Core appends block.json's version to block stylesheet URLs, so a fixed
     * version would keep serving cached CSS after an update.
     *
     * @param array $metadata Block metadata.
     * @return array Metadata, with the plugin version for this plugin's blocks.
     */
    public function set_plugin_block_version( array $metadata ): array {
        if ( str_starts_with( $metadata['name'] ?? '', 'pikari-gutenberg-query-filter/' ) ) {
            $metadata['version'] = PIKARI_GUTENBERG_QUERY_FILTER_VERSION;
        }

        return $metadata;
    }

    /**
     * Render the core/search block with query context.
     *
     * @param string    $block_content The block content.
     * @param array     $block         The full block, including name and attributes.
     * @param \WP_Block $instance      The block instance.
     * @return string The block content.
     */
    public function render_block_search( string $block_content, array $block, \WP_Block $instance ): string {
        // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required by WordPress filter signature.
        unset( $block );
        // Check if we have query context from a Query Loop block.
        $context = $instance->context ?? array();

        // If no query context, this search block is not in a Query Loop.
        if ( ! isset( $context['query'] ) ) {
            return $block_content;
        }

        // Enqueue our interactivity script to ensure the store is available.
        wp_enqueue_script_module( 'pikari-gutenberg-query-filter-query-filter-view-script-module' );

        // Determine the search query variable based on query context.
        $params    = QueryParams::from_block( $instance );
        $query_var = $params->key( 's' );
        $page_var  = $params->page_key();

        // An inherited loop paginates through a trailing path segment on
        // pretty permalinks (/category/news/page/2/), not just the `paged`
        // query var, so view.js needs the rewrite's own pagination base to
        // strip it on a filter change (spec §3.3). $wp_rewrite isn't always
        // available (some CLI contexts), so fall back to core's own default.
        global $wp_rewrite;
        $pagination_base = ( $wp_rewrite instanceof \WP_Rewrite ) ? $wp_rewrite->pagination_base : 'page';

        // Build the form action URL, removing pagination.
        $current_page = get_query_var( 'paged', 1 );
        $action       = str_replace( '/page/' . $current_page, '', add_query_arg( array( $query_var => '' ) ) );

        // Get and sanitize the current search value.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search forms don't require nonces for GET requests.
        $value = isset( $_GET[ $query_var ] ) ? sanitize_text_field( wp_unslash( $_GET[ $query_var ] ) ) : '';

        // Set interactivity state for the search value.
        wp_interactivity_state(
            'pikari/gutenberg-query-filter',
            array(
                'searchValue' => $value,
            )
        );

        // Modify the search form HTML to add interactivity.
        $processor = new \WP_HTML_Tag_Processor( $block_content );

        // Update the form element.
        if ( $processor->next_tag( array( 'tag_name' => 'form' ) ) ) {
            $processor->set_attribute( 'action', $action );
            $processor->set_attribute( 'data-wp-interactive', 'pikari/gutenberg-query-filter' );
            $processor->set_attribute( 'data-wp-on--submit', 'actions.search' );
            $context_data = wp_json_encode(
                array(
                    'searchValue'    => $value,
                    'queryVar'       => $query_var,
                    'pageVar'        => $page_var,
                    'paginationBase' => $pagination_base,
                )
            );

            // Only set context if JSON encoding succeeded.
            if ( false !== $context_data ) {
                $processor->set_attribute( 'data-wp-context', $context_data );
            }
        }

        // Update the input element.
        if ( $processor->next_tag(
            array(
                'tag_name'   => 'input',
                'class_name' => 'wp-block-search__input',
            )
        ) ) {
            $processor->set_attribute( 'name', $query_var );
            $processor->set_attribute( 'value', $value );
            $processor->set_attribute( 'data-wp-bind--value', 'context.searchValue' );
            $processor->set_attribute( 'data-wp-on--input', 'actions.search' );
        }

        return $processor->get_updated_html();
    }

    /**
     * Add data attributes to the query block to describe the block query.
     *
     * @param string $block_content Default query content.
     * @param array  $block         Parsed block.
     * @return string Modified block content.
     */
    public function render_block_query( $block_content, $block ): string {

        $processor = new \WP_HTML_Tag_Processor( $block_content );
        $processor->next_tag();

        // Always allow region updates on interactivity, use standard core region naming.
        $query_id = absint( $block['attrs']['queryId'] ?? 0 );
        $processor->set_attribute( 'data-wp-router-region', 'query-' . $query_id );

        // The router only updates regions on interactive elements. Enhanced pagination
        // already marks the Query block as core/query; otherwise use this plugin's store.
        if ( null === $processor->get_attribute( 'data-wp-interactive' ) ) {
            $processor->set_attribute( 'data-wp-interactive', 'pikari/gutenberg-query-filter' );
        }

        // Re-enable script-injected styles the router disables on any navigation,
        // including enhanced pagination, which bypasses this plugin's actions.
        $processor->set_attribute(
            'data-wp-watch---pikari-gutenberg-query-filter',
            'pikari/gutenberg-query-filter::callbacks.restoreInjectedStyles'
        );

        if ( isset( $block[ self::UNIQUE_ID_START_KEY ] ) ) {
            $start = $block[ self::UNIQUE_ID_START_KEY ];
            self::advance_unique_id( 'wp_unique_id', '', $start['id'] + self::UNIQUE_ID_RESERVE );
            self::advance_unique_id( 'wp_unique_prefixed_id', 'wp-elements-', $start['elements'] + self::UNIQUE_ID_RESERVE );
        }

        return self::inject_loop_form( $processor->get_updated_html(), $block );
    }

    /**
     * Add the loop's hidden filter form as the last child of its wrapper.
     *
     * Runs after the whole loop has rendered, so every control has emitted
     * its `form` attribute and blocks hidden after rendering, fragment caches
     * and render order are all irrelevant (spec §5.2).
     *
     * @param string $html  Rendered Query block.
     * @param array  $block Parsed block.
     * @return string HTML, with the form appended when a control claims it.
     */
    private static function inject_loop_form( string $html, array $block ): string {
        // The Query block's own context is what it receives, not what it
        // provides, so the queryId comes from its attributes.
        $params = new QueryParams(
            isset( $block['attrs']['queryId'] ) ? (int) $block['attrs']['queryId'] : null,
            ! empty( $block['attrs']['query']['inherit'] )
        );

        $targets = self::form_targets( $html, $params->form_id() );
        if ( ! $targets['found'] ) {
            return $html;
        }

        global $wp_rewrite;
        $pagination_base = ( $wp_rewrite instanceof \WP_Rewrite ) ? $wp_rewrite->pagination_base : 'page';

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Path only; escaped with esc_url() in LoopForm::render().
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

        $form = LoopForm::render(
            $params,
            LoopForm::action( $request_uri, $params->is_inherit(), $pagination_base ),
            LoopForm::hidden_inputs(
                LoopForm::query_string(),
                array_merge( $targets['names'], LoopForm::reset_names( $params ) )
            ),
            $pagination_base
        );

        // render_block_core/query receives only this block's HTML, so the
        // wrapper is its first tag and its close is the last matching one.
        $tag = strtolower( self::wrapper_tag( $html ) );
        if ( '' === $tag ) {
            return $html;
        }

        $close    = '</' . $tag . '>';
        $position = strripos( $html, $close );
        if ( false === $position ) {
            return $html;
        }

        return substr( $html, 0, $position ) . $form . substr( $html, $position );
    }

    /**
     * The wrapper's tag name, which core lets a theme set to div, main, section or aside.
     *
     * @param string $html Rendered Query block.
     * @return string Lowercase tag name, or '' when there is no tag.
     */
    private static function wrapper_tag( string $html ): string {
        $processor = new \WP_HTML_Tag_Processor( $html );

        return $processor->next_tag() ? (string) $processor->get_tag() : '';
    }

    /**
     * Find the controls that claim a form id, and the base names they own.
     *
     * The comparison is by attribute value, never by substring, so
     * `…-form-3` cannot claim `…-form-30`'s controls.
     *
     * @param string $html    Rendered Query block.
     * @param string $form_id The loop's form id.
     * @return array{found: bool, names: string[]} Whether any control claimed it, and their base names.
     */
    private static function form_targets( string $html, string $form_id ): array {
        $found     = false;
        $names     = array();
        $processor = new \WP_HTML_Tag_Processor( $html );

        while ( $processor->next_tag() ) {
            if ( $form_id !== $processor->get_attribute( 'form' ) ) {
                continue;
            }

            $found = true;

            $name = $processor->get_attribute( 'name' );
            if ( ! is_string( $name ) || '' === $name ) {
                continue;
            }

            $bracket = strpos( $name, '[' );
            $names[] = false === $bracket ? $name : substr( $name, 0, $bracket );
        }

        return array(
            'found' => $found,
            'names' => array_values( array_unique( $names ) ),
        );
    }

    /**
     * Record core's unique ID counters as a Query block starts rendering.
     *
     * Block style variations (`is-style-{name}--{n}`) and element styles
     * (`wp-elements-{n}`) number their classes with render-order counters. On
     * navigation the router swaps in the Query block's new markup and the new
     * page's stylesheets, but keeps the markup after the Query block. If the new
     * results used a different number of IDs, those classes stop matching the CSS.
     * render_block_query() advances both counters to a fixed distance from this
     * start, so blocks after the Query block get the same IDs whatever it rendered.
     *
     * @param array $parsed_block Parsed block.
     * @return array Parsed block, with the counters recorded for Query blocks.
     */
    public function reserve_unique_ids( array $parsed_block ): array {
        if ( 'core/query' !== ( $parsed_block['blockName'] ?? '' ) ) {
            return $parsed_block;
        }

        $parsed_block[ self::UNIQUE_ID_START_KEY ] = array(
            'id'       => self::next_unique_id( 'wp_unique_id', '' ),
            'elements' => self::next_unique_id( 'wp_unique_prefixed_id', 'wp-elements-' ),
        );

        return $parsed_block;
    }

    /**
     * Take the next number from a core unique ID counter.
     *
     * @param callable $generator `wp_unique_id` or `wp_unique_prefixed_id`.
     * @param string   $prefix    Counter prefix.
     * @return int The number, without its prefix.
     */
    private static function next_unique_id( callable $generator, string $prefix ): int {
        return (int) substr( $generator( $prefix ), strlen( $prefix ) );
    }

    /**
     * Advance a core unique ID counter to the target.
     *
     * A Query block that used more than UNIQUE_ID_RESERVE IDs is already past the
     * target, so the counter moves by one and later IDs are not stabilized.
     *
     * @param callable $generator `wp_unique_id` or `wp_unique_prefixed_id`.
     * @param string   $prefix    Counter prefix.
     * @param int      $target    Number the counter should reach.
     */
    private static function advance_unique_id( callable $generator, string $prefix, int $target ): void {
        do {
            $id = self::next_unique_id( $generator, $prefix );
        } while ( $id < $target );
    }
}
