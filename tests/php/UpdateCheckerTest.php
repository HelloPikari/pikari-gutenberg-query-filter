<?php
/**
 * Tests for the plugin-update-checker wiring in the main plugin file.
 *
 * @package Pikari\Tests
 */

namespace Pikari\Tests;

/**
 * The updater must never offer a release that has no matching ZIP asset.
 *
 * The release workflow refuses to build a ZIP when the plugin header and the
 * release tag disagree, which leaves a published release with no assets. PUC's
 * default (PREFER_RELEASE_ASSETS) falls back to GitHub's generated source
 * zipball in that case — an archive with no build/ directory, which would route
 * around the guard entirely and ship a plugin with no compiled assets.
 */
class UpdateCheckerTest extends TestCase {

    private function get_plugin_source(): string {
        return file_get_contents( dirname( __DIR__, 2 ) . '/pikari-gutenberg-query-filter.php' );
    }

    public function test_release_assets_are_required_not_merely_preferred(): void {
        $this->assertStringContainsString(
            'REQUIRE_RELEASE_ASSETS',
            $this->get_plugin_source(),
            'enableReleaseAssets() must be passed REQUIRE_RELEASE_ASSETS, otherwise a '
            . 'release with no ZIP falls back to the auto-generated source archive.'
        );
    }

    public function test_asset_filter_matches_the_release_workflow_zip_name(): void {
        $source = $this->get_plugin_source();

        // Pull the regex literal out of the enableReleaseAssets( '...' call so this
        // test verifies the actual pattern in use, not a hand-copied duplicate of it.
        $this->assertMatchesRegularExpression(
            '/enableReleaseAssets\(\s*\'([^\']+)\'/',
            $source,
            'Could not find an enableReleaseAssets() call with a name-filter regex.'
        );
        preg_match( '/enableReleaseAssets\(\s*\'([^\']+)\'/', $source, $matches );
        $asset_filter_regex = $matches[1];

        // release.yml builds "${SLUG}-v${VERSION}.zip".
        $this->assertMatchesRegularExpression(
            $asset_filter_regex,
            'pikari-gutenberg-query-filter-v1.2.3.zip',
            'The asset filter must match the pikari-gutenberg-query-filter-vX.Y.Z.zip name that release.yml builds.'
        );

        // ...and must NOT match the checksums file uploaded alongside it.
        $this->assertDoesNotMatchRegularExpression(
            $asset_filter_regex,
            'pikari-gutenberg-query-filter-v1.2.3-checksums.txt',
            'The asset filter must not match the checksums file.'
        );
    }

    /**
     * The VCS API lives under a minor-version namespace (v5p7 today, v5p6 before),
     * and only PucFactory is aliased to v5. An earlier draft of this wiring named
     * \YahnisElsts\PluginUpdateChecker\v5\Vcs\Api directly; that class does not
     * exist, and referencing a constant on an undefined class is a fatal Error in
     * PHP 8 — on every request, for every visitor.
     */
    public function test_vcs_api_class_is_not_hardcoded(): void {
        $source = $this->get_plugin_source();

        $this->assertStringNotContainsString(
            'v5\\Vcs\\Api',
            $source,
            'Do not hardcode the VCS API class: PUC namespaces it by minor version, '
            . 'so this fatals on the next point release. Read the constant off the '
            . 'concrete instance with get_class() instead.'
        );
        $this->assertStringContainsString(
            'get_class(',
            $source,
            'REQUIRE_RELEASE_ASSETS should be read off the concrete VCS API instance.'
        );
    }
}
