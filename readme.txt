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

== Changelog ==

= 1.0.0 =
1.0 freezes the plugin's public contract: URL parameters, markup and classes, block attributes, and PHP hooks. It has breaking changes; read the upgrade notice first. The full list is in CHANGELOG.md in the GitHub repository.

* New: filters, sort and search work in Query Loops that inherit the template's query, on home, archive and search templates.
* New: filters, sort and search work without JavaScript. Every control belongs to one hidden form per Query Loop, and each Query Filter and Sort block shows an "Apply filters" button when JavaScript is off.
* New: screen readers hear the result count after filtering, for example "12 results found", instead of "Page loaded.".
* New: editor notices for two filters that use the same URL parameter in one Query Loop, and for a filter that will display nothing.
* Breaking: sort links use one parameter, for example `query-3-sort=title-asc`. Old `orderby` and `order` links fall back to the loop's own order.
* Breaking: author links use nicenames. Old numeric author links still work. An author that matches nobody now shows no results.
* Breaking: radio groups are named after their URL parameter, so two filters for the same taxonomy in one loop act as one group.
* Breaking: an empty Label shows the default label. To hide a label, turn off Show Label.
* Breaking: the store actions `updateFilters`, `handleSelect`, `handleSort` and `search` are replaced, and core's Search form is no longer modified.
* Fixed: sticky posts no longer appear in filtered results they don't match. A loop's own tax query relation and `post__in` are kept.
* Fixed: the editor preview lists what the frontend shows, and the Author filter preview works for users below Administrator.

= 0.3.4 =
* Fixed: a Sort block works on its own, without a Query Filter or Search block on the same page.
* Fixed: block stylesheets are versioned with the plugin, so browsers and CDNs pick up new CSS.
* Changed: the blocks no longer turn on cross-document view transitions for the whole page.

= 0.3.3 =
* Fixed: styles that other scripts add at runtime stay enabled after Query Loop enhanced pagination.

= 0.3.2 =
* Fixed: radio and checkbox filters are a `<fieldset>` whose `<legend>` names the group. Theme CSS written as `label.wp-block-pikari-gutenberg-query-filter__label` no longer matches radio and checkbox labels.

= 0.3.1 =
* Fixed: content after a Query Loop no longer loses its styling when a filter changes the number of results. Block style variation and element classes after the loop now keep the same numbers, which are higher than before, for example `is-style-eyebrow--2010`.
* Fixed: styles that other plugins add with JavaScript, such as the WPForms honeypot CSS, stay enabled after filtering and after using the browser's back and forward buttons.
* Docs: the README explains why a Query Loop offset can hide a term's posts once a filter is applied.

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

= 1.0.0 =
Breaking changes. Composer users must change a ^0.3 constraint to ^1.0. Bookmarked sort links stop sorting, empty labels show the default, and custom code calling the old store actions breaks. Page caches must key on the full query string. See CHANGELOG.md.

= 0.3.4 =
Fixes a Sort block that does nothing when it is the only filter block on the page.

= 0.3.1 =
Fixes styling after a Query Loop breaking when a filter changes the results, including form fields that other plugins hide with JavaScript.

= 0.3.0 =
Fixes filters not updating the results on Query Loops without enhanced pagination. After this update, sites installed from a ZIP get update notices in the admin. If you install with Composer, widen a `^0.2` constraint, since a caret range on a 0.x version will not pick up 0.3.0.

= 0.2.0 =
Requires PHP 8.4 or later. If you install this plugin with Composer, switch your repositories entry to https://hellopikari.github.io/packages/ and widen the version constraint, since a caret range on a 0.x version will not pick up 0.2.0.