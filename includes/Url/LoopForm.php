<?php
/**
 * The hidden filter form injected into each Query Loop.
 *
 * @package Pikari\GutenbergQueryFilter
 */

namespace Pikari\GutenbergQueryFilter\Url;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds the per-loop `<form>` that every filter control joins.
 *
 * The form is a GET form, so submitting it replaces the whole query string.
 * Anything the loop does not own is therefore re-sent as a hidden input, built
 * from the raw request rather than from $_GET (spec §5.2).
 */
class LoopForm {

    /**
     * Query parameter core's pagination-numbers block adds to describe a page.
     */
    private const PAGINATION_STATE = 'cst';

    /**
     * Names a filter change clears, whichever way the form is submitted.
     *
     * Kept in one place because both sides must agree: PHP drops them from
     * the hidden inputs, and buildUrl() deletes them from the URL. If they
     * disagreed, a no-JS submit would keep a page number a filter change
     * had just reset.
     *
     * @param QueryParams $params The loop's parameters.
     * @return string[] Names to drop.
     */
    public static function reset_names( QueryParams $params ): array {
        return array( $params->page_key(), 'page', self::PAGINATION_STATE );
    }

    /**
     * The current request's query string, exactly as it arrived.
     *
     * PHP rewrites `.` and space in $_GET keys and keeps only the last of a
     * repeated key, so $_GET is only a fallback for CLI and test contexts
     * where QUERY_STRING is not set.
     *
     * @return string Raw query string, without the leading `?`.
     */
    public static function query_string(): string {
        if ( isset( $_SERVER['QUERY_STRING'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Split and decoded below, then escaped at render.
            return (string) wp_unslash( $_SERVER['QUERY_STRING'] );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtering parameters don't require nonces.
        return http_build_query( wp_unslash( $_GET ) );
    }

    /**
     * A control's parameter name, without any `[]` suffix.
     *
     * `key[]=a` and `key=a` are the same parameter to this plugin: a no-JS
     * checkbox group submits the bracketed form, a JavaScript filter change
     * writes the comma-joined one. The JavaScript side applies the same rule
     * in `bareName()` (`src/utils/build-url.js`).
     *
     * @param string $name Control name, as submitted.
     * @return string Name up to the first `[`.
     */
    public static function bare_name( string $name ): string {
        $bracket = strpos( $name, '[' );

        return false === $bracket ? $name : substr( $name, 0, $bracket );
    }

    /**
     * Turn a raw query string into the form's hidden inputs.
     *
     * @param string   $query_string  Raw query string, without the leading `?`.
     * @param string[] $dropped_names Base names to leave out.
     * @return array<int, array{name: string, value: string}> Decoded pairs, in request order.
     */
    public static function hidden_inputs( string $query_string, array $dropped_names ): array {
        $inputs = array();

        foreach ( explode( '&', $query_string ) as $pair ) {
            if ( '' === $pair ) {
                continue;
            }

            $parts = explode( '=', $pair, 2 );
            $name  = urldecode( $parts[0] );
            $value = isset( $parts[1] ) ? urldecode( $parts[1] ) : '';

            if ( '' === $name ) {
                continue;
            }

            if ( in_array( self::bare_name( $name ), $dropped_names, true ) ) {
                continue;
            }

            $inputs[] = array(
                'name'  => $name,
                'value' => $value,
            );
        }

        return $inputs;
    }

    /**
     * The form's action: the request path, and nothing else.
     *
     * JavaScript strips the same pagination segment in
     * `stripInheritedPagination()` (`src/utils/build-url.js`); the two must
     * agree, and a root-level `/page/2` is where they last diverged.
     *
     * A GET submit replaces the query string and drops the fragment, so
     * carrying either here would be misleading. An inherited loop's page
     * number can live in the path, and a filter change resets it.
     *
     * @param string $request_uri     The request URI, path first.
     * @param bool   $inherit         Whether the loop inherits the main query.
     * @param string $pagination_base The rewrite's pagination base, e.g. `page`.
     * @return string Path, beginning with a slash.
     */
    public static function action( string $request_uri, bool $inherit, string $pagination_base ): string {
        $path = explode( '#', explode( '?', $request_uri, 2 )[0], 2 )[0];

        if ( $inherit ) {
            $base = preg_quote( $pagination_base, '#' );
            $path = (string) preg_replace( '#/' . $base . '/\d+(/?)$#', '$1', $path );
        }

        // Last, and always: stripping `/page/2` can leave nothing behind, and
        // an empty action means the current document to a browser — page
        // number included. A leading `//host` is worse: esc_url() waves
        // through anything starting with a slash, so the form would point
        // off-origin and a no-JS submit would post the query string there.
        return '/' . ltrim( $path, '/' );
    }

    /**
     * Render the form element.
     *
     * @param QueryParams $params          The loop's parameters.
     * @param string      $action          Action path, from action().
     * @param array       $hidden_inputs   Pairs, from hidden_inputs().
     * @param string      $pagination_base The rewrite's pagination base.
     * @return string The `<form>` element.
     */
    public static function render( QueryParams $params, string $action, array $hidden_inputs, string $pagination_base ): string {
        $inputs = '';
        foreach ( $hidden_inputs as $input ) {
            $inputs .= sprintf(
                '<input type="hidden" name="%1$s" value="%2$s" />',
                esc_attr( $input['name'] ),
                esc_attr( $input['value'] )
            );
        }

        return sprintf(
            '<form hidden novalidate style="display:none" id="%1$s" class="wp-block-pikari-gutenberg-query-filter__form" method="get" action="%2$s" data-wp-interactive="pikari/gutenberg-query-filter" data-wp-on--submit="actions.submit" data-query-page-key="%3$s" data-query-inherit="%4$s" data-query-pagination-base="%5$s">%6$s</form>',
            esc_attr( $params->form_id() ),
            esc_url( $action ),
            esc_attr( $params->page_key() ),
            $params->is_inherit() ? 'true' : 'false',
            esc_attr( $pagination_base ),
            $inputs
        );
    }
}
