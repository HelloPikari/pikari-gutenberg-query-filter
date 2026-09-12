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
