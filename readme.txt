=== Pikari Gutenberg Query Filter ===
Contributors: pikari
Tags: query, filter, gutenberg, block, interactivity, loop
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.4
Stable tag: trunk
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Filter controls for the query loop block, using the interactivity API.

== Description ==

Filter controls for the query loop block, using the WordPress Interactivity API. This plugin provides enhanced filtering capabilities for Query Loop blocks in the WordPress Gutenberg editor.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/pikari-gutenberg-query-filter/`, or install through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the filter controls with Query Loop blocks in the Gutenberg editor

== Frequently Asked Questions ==

= What is the WordPress Interactivity API? =

The Interactivity API is a standard way to handle frontend interactions in WordPress blocks. This plugin uses it to provide dynamic filtering functionality.

= Does it work with my theme? =

Yes! The plugin uses WordPress core features and follows coding standards for broad theme compatibility.

= Which WordPress version is required? =

WordPress 6.8 or higher is required to use the Interactivity API features.

= Can I customize the filter controls? =

Yes. Each radio and checkbox option's label gets a unique class your theme can style: `{taxonomy}_{term-slug}` (for example `category_news`), `post-type_{name}`, or `author_{nicename}`. The "All" choice gets `{key}_all`.

Developers can change the option list, the label classes, and the markup inside each label with the `pikari_gutenberg_query_filter_options`, `pikari_gutenberg_query_filter_option_classes`, and `pikari_gutenberg_query_filter_option_label` filters. See docs/hooks.md in the GitHub repository for parameters and examples.

== Screenshots ==

1. Query filter controls in the editor
2. Filter controls in action with Query Loop blocks
3. Plugin settings and configuration options

== Changelog ==

= 0.3.0 =
* Fixed: changing a filter now updates the results on Query Loops without enhanced pagination. Previously the URL changed but the posts did not.
* New: each radio and checkbox option label gets a unique class your theme can style, for example `category_news`, `post-type_page`, or `author_jane-doe`.
* New: `pikari_gutenberg_query_filter_options`, `pikari_gutenberg_query_filter_option_classes`, and `pikari_gutenberg_query_filter_option_label` filters for changing the option list, label classes, and label markup.
* New: sites installed from a ZIP now see update notices in the WordPress admin. The plugin checks GitHub releases for new versions.

= 0.2.0 =
* Tested with WordPress 7.1.
* Raised the minimum PHP version to 8.4.
* Composer installs now come from the Pikari package index and use the release ZIP, the same build this plugin ships to every other install. Add the repository https://hellopikari.github.io/packages/ and remove any VCS entry for this plugin.

For releases before 0.2.0, see https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases

== Upgrade Notice ==

= 0.3.0 =
Fixes filters not updating the results on Query Loops without enhanced pagination. After this update, sites installed from a ZIP get update notices in the admin. If you install with Composer, widen a `^0.2` constraint, since a caret range on a 0.x version will not pick up 0.3.0.

= 0.2.0 =
Requires PHP 8.4 or later. If you install this plugin with Composer, switch your repositories entry to https://hellopikari.github.io/packages/ and widen the version constraint, since a caret range on a 0.x version will not pick up 0.2.0.