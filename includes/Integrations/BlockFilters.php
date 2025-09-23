<?php
/**
 * Core Block Filters
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Integrations;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles modifications to WordPress core blocks.
 */
class BlockFilters {

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

        // Modify search block rendering to add query context.
        add_filter( 'render_block_core/search', array( $this, 'render_block_search' ), 20, 3 );

        // Modify query block rendering to add data attributes.
        add_filter( 'render_block_core/query', array( $this, 'render_block_query' ), 20, 3 );
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
        $query_id = $context['queryId'] ?? null;
        $query = $context['query'] ?? null;

        // If no query context, this search block is not in a Query Loop.
        if ( empty( $query_id ) || empty( $query ) ) {
            return $block_content;
        }

        // Validate and sanitize query_id.
        $query_id = absint( $query_id );
        if ( 0 === $query_id && ! ( $query['inherit'] ?? false ) ) {
            return $block_content;
        }

        // Enqueue our interactivity script to ensure the store is available.
        wp_enqueue_script_module( 'pikari-gutenberg-query-filter-query-filter-view-script-module' );

        // Determine the search query variable based on query context.
        $inherit = $query['inherit'] ?? false;
        if ( $inherit ) {
            // Inherited queries use the main 's' parameter.
            $query_var = 's';
            $page_var = 'paged';
        } else {
            // Non-inherited queries use query-specific parameters.
            $query_var = sprintf( 'query-%d-s', $query_id );
            $page_var = sprintf( 'query-%d-page', $query_id );
        }

        // Build the form action URL, removing pagination.
        $current_page = get_query_var( 'paged', 1 );
        $action       = str_replace( '/page/' . $current_page, '', add_query_arg( array( $query_var => '' ) ) );

        // Get and sanitize the current search value.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search forms don't require nonces for GET requests.
        $value = isset( $_GET[ $query_var ] ) ? sanitize_text_field( wp_unslash( $_GET[ $query_var ] ) ) : '';

        // Set interactivity state for the search value.
        wp_interactivity_state(
            'pikari-gutenberg-query-filter',
            array(
                'searchValue' => $value,
            )
        );

        // Modify the search form HTML to add interactivity.
        $processor = new \WP_HTML_Tag_Processor( $block_content );

        // Update the form element.
        if ( $processor->next_tag( array( 'tag_name' => 'form' ) ) ) {
            $processor->set_attribute( 'action', $action );
            $processor->set_attribute( 'data-wp-interactive', 'pikari-gutenberg-query-filter' );
            $processor->set_attribute( 'data-wp-on--submit', 'actions.search' );
            $context_data = wp_json_encode(
                array(
                    'searchValue' => $value,
                    'queryVar'    => $query_var,
                    'pageVar'     => $page_var,
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

        return $processor->get_updated_html();
    }
}
