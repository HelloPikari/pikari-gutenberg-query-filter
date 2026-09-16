<?php
/**
 * Tests for BlockFilters core block integrations.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Pikari\Tests\TestCase;
use Pikari\GutenbergQueryFilter\Integrations\BlockFilters;
use Brain\Monkey\Functions;

class BlockFiltersTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        if ( ! self::load_wp_html_api() ) {
            $this->markTestSkipped(
                'WP_HTML_Tag_Processor unavailable — set WP_CORE_DIR to a WordPress checkout, ' .
                'or start wp-env so one is cached in ~/.wp-env/*/WordPress.'
            );
        }

        // WordPress functions the real WP_HTML_Tag_Processor and BlockFilters call.
        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();
        Functions\when( '_doing_it_wrong' )->justReturn( null );
        Functions\when( 'wp_kses_uri_attributes' )->justReturn( array( 'href', 'src' ) );
        Functions\when( 'absint' )->alias(
            function ( $value ) {
                return abs( (int) $value );
            }
        );
    }

    /*
     * render_block_query()
     */

    public function test_render_block_query_marks_router_region_as_interactive(): void {
        $html = ( new BlockFilters() )->render_block_query(
            '<div class="wp-block-query"><p>Posts</p></div>',
            array( 'attrs' => array( 'queryId' => 3 ) )
        );

        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag();

        $this->assertSame( 'query-3', $processor->get_attribute( 'data-wp-router-region' ) );
        $this->assertSame( 'pikari/gutenberg-query-filter', $processor->get_attribute( 'data-wp-interactive' ) );
    }

    public function test_render_block_query_keeps_core_query_namespace_from_enhanced_pagination(): void {
        $html = ( new BlockFilters() )->render_block_query(
            '<div data-wp-interactive="core/query" data-wp-router-region="query-3" class="wp-block-query"></div>',
            array(
                'attrs' => array(
                    'queryId'            => 3,
                    'enhancedPagination' => true,
                ),
            )
        );

        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag();

        $this->assertSame( 'core/query', $processor->get_attribute( 'data-wp-interactive' ) );
        $this->assertSame( 'query-3', $processor->get_attribute( 'data-wp-router-region' ) );
    }

    public function test_render_block_query_watches_router_navigation_to_restore_injected_styles(): void {
        $html = ( new BlockFilters() )->render_block_query(
            '<div data-wp-interactive="core/query" data-wp-router-region="query-3" class="wp-block-query"></div>',
            array(
                'attrs' => array(
                    'queryId'            => 3,
                    'enhancedPagination' => true,
                ),
            )
        );

        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag();

        $this->assertSame(
            'pikari/gutenberg-query-filter::callbacks.restoreInjectedStyles',
            $processor->get_attribute( 'data-wp-watch---pikari-gutenberg-query-filter' )
        );
    }

    /*
     * Plugin block versions
     */

    public function test_constructor_registers_plugin_block_version(): void {
        $filters = new BlockFilters();

        $this->assertNotFalse( has_filter( 'block_type_metadata', array( $filters, 'set_plugin_block_version' ) ) );
    }

    /**
     * Core versions block stylesheets with block.json's version, so it must follow releases.
     *
     * @dataProvider provide_plugin_block_names
     *
     * @param string $block_name Plugin block name.
     */
    public function test_set_plugin_block_version_uses_plugin_version( string $block_name ): void {
        if ( ! defined( 'PIKARI_GUTENBERG_QUERY_FILTER_VERSION' ) ) {
            define( 'PIKARI_GUTENBERG_QUERY_FILTER_VERSION', '9.8.7' );
        }

        $metadata = ( new BlockFilters() )->set_plugin_block_version(
            array(
                'name'    => $block_name,
                'version' => '0.1.0',
            )
        );

        $this->assertSame( PIKARI_GUTENBERG_QUERY_FILTER_VERSION, $metadata['version'] );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provide_plugin_block_names(): array {
        return array(
            'query filter' => array( 'pikari-gutenberg-query-filter/query-filter' ),
            'sort'         => array( 'pikari-gutenberg-query-filter/sort' ),
        );
    }

    public function test_set_plugin_block_version_leaves_other_blocks_alone(): void {
        $metadata = array(
            'name'    => 'core/query',
            'version' => '1.2.3',
        );

        $this->assertSame( $metadata, ( new BlockFilters() )->set_plugin_block_version( $metadata ) );
    }

    /*
     * Unique ID reservation
     */

    public function test_constructor_registers_unique_id_reservation(): void {
        $filters = new BlockFilters();

        $this->assertNotFalse( has_filter( 'render_block_data', array( $filters, 'reserve_unique_ids' ) ) );
    }

    /**
     * Blocks after a router region must get the same unique IDs whatever the
     * region rendered, or their classes stop matching the navigated page's CSS.
     *
     * @dataProvider provide_ids_used_inside_query
     *
     * @param int $ids_used_inside_query IDs consumed by the query's inner blocks.
     */
    public function test_ids_after_query_do_not_depend_on_its_results( int $ids_used_inside_query ): void {
        $this->stub_unique_id_counters();
        $filters = new BlockFilters();

        $block = $filters->reserve_unique_ids(
            array(
                'blockName' => 'core/query',
                'attrs'     => array( 'queryId' => 3 ),
            )
        );

        for ( $i = 0; $i < $ids_used_inside_query; $i++ ) {
            wp_unique_id( 'is-style-eyebrow--' );
            wp_unique_prefixed_id( 'wp-elements-' );
        }

        $filters->render_block_query( '<div class="wp-block-query"></div>', $block );

        $this->assertSame( 'is-style-eyebrow--1002', wp_unique_id( 'is-style-eyebrow--' ) );
        $this->assertSame( 'wp-elements-1002', wp_unique_prefixed_id( 'wp-elements-' ) );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provide_ids_used_inside_query(): array {
        return array(
            'one result'  => array( 1 ),
            'six results' => array( 6 ),
        );
    }

    public function test_reserve_unique_ids_ignores_other_blocks(): void {
        $this->stub_unique_id_counters();
        $block = array(
            'blockName' => 'core/group',
            'attrs'     => array(),
        );

        $this->assertSame( $block, ( new BlockFilters() )->reserve_unique_ids( $block ) );
        $this->assertSame( '1', wp_unique_id() );
    }

    /**
     * Replace core's static unique ID counters with per-test ones.
     */
    private function stub_unique_id_counters(): void {
        $id_counter        = 0;
        $prefixed_counters = array();

        Functions\when( 'wp_unique_id' )->alias(
            function ( $prefix = '' ) use ( &$id_counter ) {
                return $prefix . ++$id_counter;
            }
        );
        Functions\when( 'wp_unique_prefixed_id' )->alias(
            function ( $prefix = '' ) use ( &$prefixed_counters ) {
                $prefixed_counters[ $prefix ] = ( $prefixed_counters[ $prefix ] ?? 0 ) + 1;

                return $prefix . $prefixed_counters[ $prefix ];
            }
        );
    }

    /**
     * Load the real WP_HTML_Tag_Processor from a WordPress core checkout.
     *
     * @return bool Whether the class is available.
     */
    private static function load_wp_html_api(): bool {
        if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
            return true;
        }

        $core_dir = self::find_wp_core_dir();
        if ( null === $core_dir ) {
            return false;
        }

        // Same order as wp-settings.php. compat-utf8.php only exists in newer cores.
        $files = array(
            '/wp-includes/compat-utf8.php',
            '/wp-includes/class-wp-token-map.php',
            '/wp-includes/utf8.php',
            '/wp-includes/html-api/html5-named-character-references.php',
            '/wp-includes/html-api/class-wp-html-attribute-token.php',
            '/wp-includes/html-api/class-wp-html-span.php',
            '/wp-includes/html-api/class-wp-html-doctype-info.php',
            '/wp-includes/html-api/class-wp-html-text-replacement.php',
            '/wp-includes/html-api/class-wp-html-decoder.php',
            '/wp-includes/html-api/class-wp-html-tag-processor.php',
        );

        foreach ( $files as $relative_path ) {
            if ( file_exists( $core_dir . $relative_path ) ) {
                require_once $core_dir . $relative_path;
            }
        }

        // Core declares the entity table as a global; required from here it lands in this scope.
        if ( isset( $html5_named_character_references ) ) {
            $GLOBALS['html5_named_character_references'] = $html5_named_character_references;
        }

        return class_exists( 'WP_HTML_Tag_Processor' );
    }

    /**
     * Find a WordPress core checkout: WP_CORE_DIR first, then wp-env's local cache.
     *
     * @return string|null Core directory, or null if none is found.
     */
    private static function find_wp_core_dir(): ?string {
        $candidates = array();

        if ( getenv( 'WP_CORE_DIR' ) ) {
            $candidates[] = rtrim( getenv( 'WP_CORE_DIR' ), '/' );
        }

        if ( getenv( 'HOME' ) ) {
            $candidates = array_merge( $candidates, glob( getenv( 'HOME' ) . '/.wp-env/*/WordPress', GLOB_ONLYDIR ) ?: array() );
        }

        foreach ( $candidates as $candidate ) {
            if ( file_exists( $candidate . '/wp-includes/html-api/class-wp-html-tag-processor.php' ) ) {
                return $candidate;
            }
        }

        return null;
    }
}
