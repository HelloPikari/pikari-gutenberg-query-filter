<?php
/**
 * Tests for BlockFilters core block integrations.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Mockery;
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
        Functions\when( 'wp_unslash' )->returnArg();
    }

    protected function tearDown(): void {
        unset( $GLOBALS['wp_rewrite'] );
        unset( $_SERVER['QUERY_STRING'], $_SERVER['REQUEST_URI'] );
        $_GET = array();
        parent::tearDown();
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
     * render_block_query(): form injection
     */

    /**
     * Render a Query block whose inner HTML is given, with the request set.
     *
     * @param string $inner        Inner HTML of the Query wrapper.
     * @param array  $attrs        Query block attributes.
     * @param string $query_string Raw query string for the request.
     * @param string $tag          Wrapper tag name.
     * @return string Rendered HTML.
     */
    private function render_query( string $inner, array $attrs = array( 'queryId' => 3 ), string $query_string = '', string $tag = 'div' ): string {
        $_SERVER['QUERY_STRING'] = $query_string;
        $_SERVER['REQUEST_URI']  = '/library/?' . $query_string;

        return ( new BlockFilters() )->render_block_query(
            sprintf( '<%1$s class="wp-block-query">%2$s</%1$s>', $tag, $inner ),
            array( 'attrs' => $attrs )
        );
    }

    public function test_render_block_query_injects_the_form_when_a_control_claims_it(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>'
        );

        $this->assertStringContainsString( 'id="pikari-gutenberg-query-filter-form-3"', $html );
    }

    public function test_render_block_query_injects_the_form_as_the_last_child(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select><p>After</p>'
        );

        $this->assertMatchesRegularExpression( '#<p>After</p><form [^>]*id="pikari-gutenberg-query-filter-form-3"#', $html );
        $this->assertStringEndsWith( '</form></div>', $html );
    }

    public function test_render_block_query_injects_before_a_non_div_wrapper_close(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>',
            array( 'queryId' => 3 ),
            '',
            'main'
        );

        $this->assertStringEndsWith( '</form></main>', $html );
    }

    public function test_render_block_query_injects_no_form_without_a_control(): void {
        $html = $this->render_query( '<p>Just posts</p>' );

        $this->assertStringNotContainsString( '<form', $html );
    }

    public function test_render_block_query_ignores_another_loops_control(): void {
        // Loop 3 must not claim loop 30's select (spec §5.2).
        $html = $this->render_query(
            '<select name="query-30-category" form="pikari-gutenberg-query-filter-form-30"></select>'
        );

        $this->assertStringNotContainsString( '<form', $html );
    }

    public function test_render_block_query_owns_its_controls_names(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>',
            array( 'queryId' => 3 ),
            'query-3-category=news&lang=fr'
        );

        $this->assertStringNotContainsString( '<input type="hidden" name="query-3-category"', $html );
        $this->assertStringContainsString( '<input type="hidden" name="lang" value="fr"', $html );
    }

    public function test_render_block_query_owns_a_checkbox_name_in_array_form(): void {
        $html = $this->render_query(
            '<input type="checkbox" name="query-3-category[]" form="pikari-gutenberg-query-filter-form-3">',
            array( 'queryId' => 3 ),
            'query-3-category=news&lang=fr'
        );

        $this->assertStringNotContainsString( '<input type="hidden" name="query-3-category"', $html );
        $this->assertStringContainsString( 'name="lang"', $html );
    }

    public function test_render_block_query_resets_the_page_key_and_cst(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>',
            array( 'queryId' => 3 ),
            'query-3-page=2&page=4&cst=1&lang=fr'
        );

        $this->assertStringNotContainsString( 'name="query-3-page"', $html );
        $this->assertStringNotContainsString( 'name="page"', $html );
        $this->assertStringNotContainsString( 'name="cst"', $html );
        $this->assertStringContainsString( 'name="lang"', $html );
    }

    public function test_render_block_query_uses_the_inherit_form_for_an_inherited_loop(): void {
        $html = $this->render_query(
            '<select name="query-category" form="pikari-gutenberg-query-filter-form-inherit"></select>',
            array( 'query' => array( 'inherit' => true ) )
        );

        $this->assertStringContainsString( 'id="pikari-gutenberg-query-filter-form-inherit"', $html );
        $this->assertStringContainsString( 'data-query-inherit="true"', $html );
    }

    public function test_render_block_query_gives_a_loop_without_a_query_id_the_same_form_id_its_controls_use(): void {
        // The controls read queryId from block context, this filter from
        // block attributes. Both paths must land on `…-form-0`, or the
        // controls would point at a form that is never injected.
        $params = new \Pikari\GutenbergQueryFilter\Url\QueryParams( null );
        $html   = $this->render_query(
            sprintf( '<select name="query-0-category" form="%s"></select>', $params->form_id() ),
            array()
        );

        $this->assertStringContainsString( sprintf( 'id="%s"', $params->form_id() ), $html );
        $this->assertStringContainsString( 'data-query-page-key="query-page"', $html );
    }

    public function test_render_block_query_gives_a_nested_loop_its_own_form(): void {
        // The inner loop rendered first, so its form is already in the HTML.
        // The outer loop must add its own and leave the inner one alone.
        $inner = '<div class="wp-block-query">'
            . '<select name="query-9-category" form="pikari-gutenberg-query-filter-form-9"></select>'
            . '<form id="pikari-gutenberg-query-filter-form-9"><input type="hidden" name="lang" value="fr" /></form>'
            . '</div>';

        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>' . $inner
        );

        $this->assertSame( 1, substr_count( $html, 'id="pikari-gutenberg-query-filter-form-9"' ) );
        $this->assertSame( 1, substr_count( $html, 'id="pikari-gutenberg-query-filter-form-3"' ) );
        $this->assertStringEndsWith( '</form></div>', $html );
    }

    public function test_render_block_query_does_not_own_a_hidden_input_of_a_nested_form(): void {
        // The nested form's own hidden inputs carry a name but no `form`
        // attribute, so the outer scan must not treat them as controls.
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>'
            . '<form id="pikari-gutenberg-query-filter-form-9"><input type="hidden" name="lang" value="fr" /></form>',
            array( 'queryId' => 3 ),
            'lang=fr'
        );

        $this->assertSame(
            2,
            substr_count( $html, 'name="lang"' ),
            'the nested form\'s literal input, plus the injected form\'s own'
        );
    }

    public function test_render_block_query_still_marks_the_router_region_when_it_injects(): void {
        $html = $this->render_query(
            '<select name="query-3-category" form="pikari-gutenberg-query-filter-form-3"></select>'
        );

        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag();

        $this->assertSame( 'query-3', $processor->get_attribute( 'data-wp-router-region' ) );
    }

    /*
     * render_block_search()
     */

    /**
     * Names the search input from the loop's queryId (spec §3.1).
     */
    public function test_render_block_search_names_input_from_query_id(): void {
        $this->stub_search_render_functions();
        $_GET = array();

        $instance          = Mockery::mock( 'WP_Block' );
        $instance->context = array(
            'queryId' => 3,
            'query'   => array( 'inherit' => false ),
        );

        $html = ( new BlockFilters() )->render_block_search(
            '<form><input type="search" class="wp-block-search__input"></form>',
            array(),
            $instance
        );

        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'input', 'class_name' => 'wp-block-search__input' ) );

        $this->assertSame( 'query-3-s', $processor->get_attribute( 'name' ) );
    }

    /**
     * A loop with no queryId — every loop in Twenty Twenty-Five — still gets a
     * named search input, using the query-0- prefix (spec §3.1). 0.3.4's
     * empty( $query_id ) check skipped these loops entirely.
     */
    public function test_render_block_search_without_query_id_uses_zero_prefix(): void {
        $this->stub_search_render_functions();
        $_GET = array();

        $instance          = Mockery::mock( 'WP_Block' );
        $instance->context = array(
            'query' => array( 'inherit' => false ),
        );

        $html = ( new BlockFilters() )->render_block_search(
            '<form><input type="search" class="wp-block-search__input"></form>',
            array(),
            $instance
        );

        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'input', 'class_name' => 'wp-block-search__input' ) );

        $this->assertSame( 'query-0-s', $processor->get_attribute( 'name' ) );
    }

    /**
     * Render a core/search block inside a loop.
     *
     * @param array $context Block context.
     * @return string Rendered HTML.
     */
    private function render_search( array $context ): string {
        $this->stub_search_render_functions();

        $instance          = Mockery::mock( 'WP_Block' );
        $instance->context = $context;

        return ( new BlockFilters() )->render_block_search(
            '<form role="search" method="get" action="/" class="wp-block-search">'
            . '<input class="wp-block-search__input" type="search" name="s" required value="" />'
            . '<button type="submit" class="wp-block-search__button">Search</button>'
            . '</form>',
            array(),
            $instance
        );
    }

    public function test_render_block_search_leaves_cores_form_alone(): void {
        $html = $this->render_search(
            array(
                'queryId' => 3,
                'query'   => array( 'inherit' => false ),
            )
        );

        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'form' ) );

        $this->assertSame( '/', $processor->get_attribute( 'action' ) );
        $this->assertNull( $processor->get_attribute( 'data-wp-interactive' ) );
        $this->assertNull( $processor->get_attribute( 'data-wp-context' ) );
        $this->assertNull( $processor->get_attribute( 'data-wp-on--submit' ) );
    }

    public function test_render_block_search_joins_the_input_to_the_loop_form(): void {
        $_GET = array( 'query-3-s' => 'cats' );

        $html      = $this->render_search(
            array(
                'queryId' => 3,
                'query'   => array( 'inherit' => false ),
            )
        );
        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'input' ) );

        $this->assertSame( 'query-3-s', $processor->get_attribute( 'name' ) );
        $this->assertSame( 'cats', $processor->get_attribute( 'value' ) );
        $this->assertSame( 'pikari-gutenberg-query-filter-form-3', $processor->get_attribute( 'form' ) );
        $this->assertSame(
            'pikari/gutenberg-query-filter::{"searchValue":"cats"}',
            $processor->get_attribute( 'data-wp-context' )
        );
        $this->assertSame(
            'pikari/gutenberg-query-filter::context.searchValue',
            $processor->get_attribute( 'data-wp-bind--value' )
        );
        $this->assertSame(
            'pikari/gutenberg-query-filter::actions.change',
            $processor->get_attribute( 'data-wp-on--input' )
        );
        $this->assertSame(
            'pikari/gutenberg-query-filter::actions.change',
            $processor->get_attribute( 'data-wp-on--compositionend' )
        );
        $this->assertSame(
            'pikari/gutenberg-query-filter::actions.endBurst',
            $processor->get_attribute( 'data-wp-on--blur' )
        );
    }

    public function test_render_block_search_joins_the_submit_button_to_the_loop_form(): void {
        $html      = $this->render_search(
            array(
                'queryId' => 3,
                'query'   => array( 'inherit' => false ),
            )
        );
        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'button' ) );

        $this->assertSame( 'pikari-gutenberg-query-filter-form-3', $processor->get_attribute( 'form' ) );
    }

    public function test_render_block_search_uses_the_bare_s_in_an_inherited_loop(): void {
        $html      = $this->render_search( array( 'query' => array( 'inherit' => true ) ) );
        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'input' ) );

        $this->assertSame( 's', $processor->get_attribute( 'name' ) );
        $this->assertSame( 'pikari-gutenberg-query-filter-form-inherit', $processor->get_attribute( 'form' ) );
    }

    public function test_render_block_search_handles_a_loop_without_a_query_id(): void {
        // A Query Loop with no queryId still provides `query` context; the
        // Search block in it must join that loop's form (spec §5.3).
        $html      = $this->render_search( array( 'query' => array( 'inherit' => false ) ) );
        $processor = new \WP_HTML_Tag_Processor( $html );
        $processor->next_tag( array( 'tag_name' => 'input' ) );

        $this->assertSame( 'query-0-s', $processor->get_attribute( 'name' ) );
        $this->assertSame( 'pikari-gutenberg-query-filter-form-0', $processor->get_attribute( 'form' ) );
    }

    public function test_render_block_search_ignores_a_block_outside_a_loop(): void {
        $instance          = Mockery::mock( 'WP_Block' );
        $instance->context = array();

        $content = '<form class="wp-block-search"><input class="wp-block-search__input" name="s" /></form>';

        $this->assertSame(
            $content,
            ( new BlockFilters() )->render_block_search( $content, array(), $instance )
        );
    }

    /**
     * Stub the WordPress functions render_block_search() calls beyond naming.
     */
    private function stub_search_render_functions(): void {
        Functions\when( 'wp_enqueue_script_module' )->justReturn( null );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
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
