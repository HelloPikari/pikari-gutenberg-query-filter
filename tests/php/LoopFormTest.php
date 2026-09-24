<?php
/**
 * Tests for the hidden per-loop filter form.
 *
 * @package Pikari\Tests\GutenbergQueryFilter
 */

namespace Pikari\Tests\GutenbergQueryFilter;

use Pikari\Tests\TestCase;
use Pikari\GutenbergQueryFilter\Url\LoopForm;
use Pikari\GutenbergQueryFilter\Url\QueryParams;
use Brain\Monkey\Functions;

class LoopFormTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Functions\stubEscapeFunctions();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'esc_url' )->returnArg();
    }

    protected function tearDown(): void {
        unset( $_SERVER['QUERY_STRING'] );
        $_GET = array();
        parent::tearDown();
    }

    /*
     * reset_names()
     */

    public function test_reset_names_covers_the_page_key_page_and_cst(): void {
        $this->assertSame(
            array( 'query-3-page', 'page', 'cst' ),
            LoopForm::reset_names( new QueryParams( 3 ) )
        );
    }

    public function test_reset_names_uses_paged_for_an_inherited_loop(): void {
        $this->assertSame(
            array( 'paged', 'page', 'cst' ),
            LoopForm::reset_names( new QueryParams( null, true ) )
        );
    }

    /*
     * hidden_inputs()
     */

    public function test_hidden_inputs_decodes_names_and_values(): void {
        $this->assertSame(
            array( array( 'name' => 'utm source', 'value' => 'a b' ) ),
            LoopForm::hidden_inputs( 'utm+source=a%20b', array() )
        );
    }

    public function test_hidden_inputs_keeps_repeated_keys_php_would_collapse(): void {
        // $_GET would keep only the last `tag`; the raw query string keeps both.
        $this->assertSame(
            array(
                array( 'name' => 'tag', 'value' => 'a' ),
                array( 'name' => 'tag', 'value' => 'b' ),
            ),
            LoopForm::hidden_inputs( 'tag=a&tag=b', array() )
        );
    }

    public function test_hidden_inputs_keeps_names_php_would_rewrite(): void {
        // PHP turns `a.b` into `a_b` in $_GET.
        $this->assertSame(
            array( array( 'name' => 'a.b', 'value' => '1' ) ),
            LoopForm::hidden_inputs( 'a.b=1', array() )
        );
    }

    public function test_hidden_inputs_drops_an_owned_name(): void {
        $this->assertSame(
            array( array( 'name' => 'lang', 'value' => 'fr' ) ),
            LoopForm::hidden_inputs( 'query-3-category=news&lang=fr', array( 'query-3-category' ) )
        );
    }

    public function test_hidden_inputs_drops_an_owned_name_in_array_form(): void {
        $this->assertSame(
            array(),
            LoopForm::hidden_inputs( 'query-3-category%5B%5D=news', array( 'query-3-category' ) )
        );
    }

    public function test_hidden_inputs_drops_cst(): void {
        // core/query-pagination-numbers adds it; it describes a page this
        // filter change is resetting (spec §5.2).
        $this->assertSame(
            array(),
            LoopForm::hidden_inputs( 'cst=1', array( 'cst' ) )
        );
    }

    public function test_hidden_inputs_keeps_a_name_that_merely_starts_with_an_owned_one(): void {
        // `query-3-category` must not swallow `query-30-category`.
        $this->assertSame(
            array( array( 'name' => 'query-30-category', 'value' => 'news' ) ),
            LoopForm::hidden_inputs( 'query-30-category=news', array( 'query-3-category' ) )
        );
    }

    public function test_hidden_inputs_keeps_a_valueless_pair(): void {
        $this->assertSame(
            array( array( 'name' => 'debug', 'value' => '' ) ),
            LoopForm::hidden_inputs( 'debug', array() )
        );
    }

    public function test_hidden_inputs_ignores_empty_segments(): void {
        $this->assertSame( array(), LoopForm::hidden_inputs( '&&', array() ) );
    }

    public function test_hidden_inputs_preserves_a_percent_encoded_slug(): void {
        // A non-Latin term slug is stored percent-encoded; decoding must give
        // the octets back, not mangle them (the B1 sanitize_text_field bug).
        $this->assertSame(
            array( array( 'name' => 'query-9-category', 'value' => '%e6%96%b0%e9%97%bb' ) ),
            LoopForm::hidden_inputs( 'query-9-category=%25e6%2596%25b0%25e9%2597%25bb', array() )
        );
    }

    public function test_hidden_inputs_preserves_a_value_containing_equals_sign(): void {
        // The `?` and `=` inside this value arrive percent-encoded, so they
        // must survive decoding as part of the value. What pins explode()'s
        // limit of 2 is the next test, whose value carries a literal `=`.
        $this->assertSame(
            array( array( 'name' => 'redirect', 'value' => '/a?b=c' ) ),
            LoopForm::hidden_inputs( 'redirect=/a%3Fb%3Dc', array() )
        );
    }

    public function test_hidden_inputs_handles_unencoded_equals_in_value(): void {
        // If somehow an equals sign appears unencoded in the value, it must not
        // split the value further. This ensures explode uses limit 2.
        $this->assertSame(
            array( array( 'name' => 'code', 'value' => 'foo=bar' ) ),
            LoopForm::hidden_inputs( 'code=foo=bar', array() )
        );
    }

    /*
     * query_string()
     */

    public function test_query_string_prefers_the_raw_server_value(): void {
        $_SERVER['QUERY_STRING'] = 'tag=a&tag=b';
        $_GET                    = array( 'tag' => 'b' );

        $this->assertSame( 'tag=a&tag=b', LoopForm::query_string() );
    }

    public function test_query_string_falls_back_to_get(): void {
        $_GET = array( 'tag' => 'a' );

        $this->assertSame( 'tag=a', LoopForm::query_string() );
    }

    /*
     * action()
     */

    public function test_action_drops_the_query_string_and_fragment(): void {
        $this->assertSame(
            '/resources/library/',
            LoopForm::action( '/resources/library/?query-3-category=news#results', false, 'page' )
        );
    }

    public function test_action_keeps_a_custom_loops_pagination_segment(): void {
        // Only an inherited loop paginates through the path (spec §5.2).
        $this->assertSame(
            '/library/page/2/',
            LoopForm::action( '/library/page/2/', false, 'page' )
        );
    }

    public function test_action_strips_an_inherited_loops_pagination_segment(): void {
        $this->assertSame(
            '/category/news/',
            LoopForm::action( '/category/news/page/2/', true, 'page' )
        );
    }

    public function test_action_keeps_the_trailing_slash_style(): void {
        $this->assertSame(
            '/category/news',
            LoopForm::action( '/category/news/page/2', true, 'page' )
        );
    }

    public function test_action_strips_a_root_level_pagination_segment(): void {
        // A root-level inherited loop on page 2, on a site whose permalink
        // structure omits the trailing slash. Nothing is left of the path,
        // and an empty action means *the current document* to a browser — so
        // a no-JS submit would keep the `/page/2` the strip exists to drop,
        // against a filtered query that may have fewer pages (spec §5.2).
        $this->assertSame( '/', LoopForm::action( '/page/2', true, 'page' ) );
    }

    public function test_action_strips_a_root_level_pagination_segment_with_a_trailing_slash(): void {
        $this->assertSame( '/', LoopForm::action( '/page/2/', true, 'page' ) );
    }

    public function test_action_honours_a_translated_pagination_base(): void {
        $this->assertSame(
            '/categorie/actualites/',
            LoopForm::action( '/categorie/actualites/pagina/3/', true, 'pagina' )
        );
    }

    public function test_action_leaves_a_path_that_merely_contains_the_base(): void {
        $this->assertSame(
            '/page-two/',
            LoopForm::action( '/page-two/', true, 'page' )
        );
    }

    public function test_action_cannot_be_made_protocol_relative(): void {
        // esc_url() short-circuits its protocol check for anything starting
        // with a slash, so a request for `//evil.com/x` would render
        // `action="//evil.com/x"` and a no-JS submit would post the visitor's
        // whole query string to that host.
        $this->assertSame( '/evil.com/x', LoopForm::action( '//evil.com/x', false, 'page' ) );
    }

    public function test_action_falls_back_to_the_site_root(): void {
        $this->assertSame( '/', LoopForm::action( '?s=cat', false, 'page' ) );
    }

    /*
     * render()
     */

    public function test_render_describes_the_loop_for_the_browser_and_the_store(): void {
        $html = LoopForm::render(
            new QueryParams( 3 ),
            '/library/',
            array( array( 'name' => 'lang', 'value' => 'fr' ) ),
            'page'
        );

        $this->assertStringContainsString( 'id="pikari-gutenberg-query-filter-form-3"', $html );
        $this->assertStringContainsString( 'class="wp-block-pikari-gutenberg-query-filter__form"', $html );
        $this->assertStringContainsString( 'method="get"', $html );
        $this->assertStringContainsString( 'action="/library/"', $html );
        $this->assertStringContainsString( 'data-wp-interactive="pikari/gutenberg-query-filter"', $html );
        $this->assertStringContainsString( 'data-wp-on--submit="actions.submit"', $html );
        $this->assertStringContainsString( 'data-query-page-key="query-3-page"', $html );
        $this->assertStringContainsString( 'data-query-inherit="false"', $html );
        $this->assertStringContainsString( 'data-query-pagination-base="page"', $html );
        $this->assertStringContainsString( '<input type="hidden" name="lang" value="fr"', $html );
    }

    public function test_render_hides_the_form_twice_over(): void {
        // A theme that overrides [hidden] must not put the form back in flow,
        // and controls outside a hidden form still submit with it (spec §5.2).
        $html = LoopForm::render( new QueryParams( 3 ), '/', array(), 'page' );

        $this->assertStringContainsString( 'hidden', $html );
        $this->assertStringContainsString( 'style="display:none"', $html );
    }

    public function test_render_is_novalidate(): void {
        // core/search marks its input required; without novalidate the browser
        // would block every submit while the box is empty (spec §5.2).
        $this->assertStringContainsString(
            'novalidate',
            LoopForm::render( new QueryParams( 3 ), '/', array(), 'page' )
        );
    }

    public function test_render_marks_an_inherited_loop(): void {
        $html = LoopForm::render( new QueryParams( null, true ), '/category/news/', array(), 'page' );

        $this->assertStringContainsString( 'data-query-inherit="true"', $html );
        $this->assertStringContainsString( 'data-query-page-key="paged"', $html );
    }

    public function test_render_escapes_a_hidden_input(): void {
        $html = LoopForm::render(
            new QueryParams( 3 ),
            '/',
            array( array( 'name' => 'x', 'value' => '"><script>alert(1)</script>' ) ),
            'page'
        );

        $this->assertStringNotContainsString( '<script>', $html );
    }

    public function test_render_stamps_the_count_and_its_message(): void {
        Functions\stubTranslationFunctions();
        Functions\when( 'number_format_i18n' )->alias( 'strval' );

        $html = LoopForm::render( new QueryParams( 3 ), '/', array(), 'page', 12 );

        $this->assertStringContainsString( 'data-query-found-posts="12"', $html );
        $this->assertStringContainsString( 'data-query-results-message="12 results found"', $html );
    }

    public function test_render_stamps_a_zero_count(): void {
        Functions\stubTranslationFunctions();

        $html = LoopForm::render( new QueryParams( 3 ), '/', array(), 'page', 0 );

        $this->assertStringContainsString( 'data-query-found-posts="0"', $html );
        $this->assertStringContainsString( 'data-query-results-message="No results found"', $html );
    }

    public function test_render_omits_both_attributes_without_a_count(): void {
        $html = LoopForm::render( new QueryParams( 3 ), '/', array(), 'page' );

        $this->assertStringNotContainsString( 'data-query-found-posts', $html );
        $this->assertStringNotContainsString( 'data-query-results-message', $html );
    }
}
