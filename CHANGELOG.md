# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Breaking changes

- **Sort links use one URL parameter.** A loop's sort is now read from a single `query-{id}-sort` value (for example `query-3-sort=title-asc`), not the old pair of `orderby` and `order` parameters. Old bookmarked or shared sort links stop sorting; the loop falls back to its own default order.
- **Author filter links use nicenames, not IDs.** Author options and links now write the user's nicename (`query-3-author=jane-doe`). A plain numeric author ID is still accepted and resolved, so links generated before this change keep working.
- **An author value that matches nobody now shows no results,** instead of showing every author's posts. This matches how an unknown taxonomy term already behaved, and avoids revealing whether a given username exists.
- **The Sort dropdown no longer shows a separate "Default" choice when the loop's own order already matches one of the sort options.** That option is selected instead, and choosing it keeps the URL clean.
- **Internal helper classes are removed:** the `SortHelper` and `AbstractQueryHelper` classes, and the methods `FilterHelper::get_current_filter_value()`, `FilterHelper::get_*_filter_config()`, and `AuthorHelper::get_author_filter_config()`. These were never part of the documented `docs/hooks.md` contract, but any code calling them directly will need to move to `Url\QueryParams`, `Url\FilterState`, `Query\QueryArgs` and `Query\SortOptions`.

### Fixed

- Sticky posts no longer leak into filtered Query Loop results. A sticky post that doesn't match a taxonomy, author or search filter used to appear anyway; filtering now correctly excludes it.
- A taxonomy filter no longer forces the loop's own taxonomy query to `relation => AND`. A Query Loop already configured with its own tax query (for example, its own `OR` relation) keeps that relation; the filter's terms are combined with it instead of overwriting it.
- A post type filter no longer drops a loop's `post__in`. A loop built around a fixed list of posts (such as a "sticky only" loop) keeps that list when a post type filter is also applied.

## [0.3.4] - 2026-09-16

### Fixed

- A Sort block now works on its own. It loaded a script from a block that no longer exists, so without a Query Filter or Search block on the same page, choosing a sort did nothing. Its stylesheet also pointed at a file the build doesn't produce.
- Block stylesheets are now versioned with the plugin version. They were stuck at `?ver=0.1.0`, so where a stylesheet isn't inlined, browsers and CDNs could keep serving CSS from an earlier release.

### Changed

- The blocks' stylesheets no longer contain `@view-transition { navigation: auto; }`. It turned on animated transitions for every full page load on any page where a filter block appeared, and it had no effect on filtering itself. Themes that want cross-document view transitions should opt in themselves.

### Removed

- `AuthorHelper::get_authors_with_post_count()` and `AuthorHelper::invalidate_specific_author_cache()`, which nothing called. The second built a different cache key from the one it was meant to clear.
- The plugin no longer flushes rewrite rules on activation and deactivation. It adds no rewrite rules.

## [0.3.3] - 2026-09-15

### Fixed

- Styles that other scripts add at runtime, such as the WPForms honeypot CSS, now also stay enabled after Query Loop enhanced pagination. Core's pagination links navigate with the Interactivity API router without going through this plugin, so the page's forms showed their hidden spam-trap fields after clicking to another page. The styles are now restored after every router navigation.

## [0.3.2] - 2026-09-13

### Fixed

- Radio and checkbox filters now wrap their options in a `<fieldset>` whose `<legend>` names the group for screen readers. The block label used to be a `<label for>` pointing at an id that no element had. The legend keeps the `wp-block-pikari-gutenberg-query-filter__label` class, but theme CSS written as `label.wp-block-pikari-gutenberg-query-filter__label` no longer matches it. Select filters are unchanged.

## [0.3.1] - 2026-09-13

### Added

- README troubleshooting entry on Query Loop offsets, which apply after a filter and can hide a term's posts.

### Fixed

- Content after a Query Loop no longer loses its styling when a filter changes the number of results. Block style variation (`is-style-{name}--{n}`) and element (`wp-elements-{n}`) classes are numbered in render order, so markup after the loop kept numbers that no longer matched the new page's CSS. Each Query Loop now reserves 1,000 of these numbers, so later classes carry higher numbers than before, for example `is-style-eyebrow--2010`.
- Styles that other scripts add at runtime, such as the WPForms honeypot CSS, stay enabled after filtering and after back/forward navigation. The Interactivity API router disables any stylesheet that is not in the fetched page's HTML.

## [0.3.0] - 2026-09-12

### Added

- Unique class on each radio and checkbox option label: `{taxonomy}_{term-slug}`, `post-type_{name}`, or `author_{nicename}`, plus `{key}_all` on the "All" radio. Also applied in the editor preview.
- `pikari_gutenberg_query_filter_options` filter for the option list.
- `pikari_gutenberg_query_filter_option_classes` filter for option label classes.
- `pikari_gutenberg_query_filter_option_label` filter for the markup inside option labels.
- `docs/hooks.md` theming and hooks reference.
- Update notices in the WordPress admin for sites installed from a ZIP. The plugin now checks GitHub releases for new versions.

### Changed

- The Query Filter block is not rendered when the option list is empty after filtering.

### Fixed

- Changing a filter now updates the results on Query Loops without enhanced pagination. Previously the URL changed but the post list did not, because the Query block's router region was not marked interactive.

## [0.2.0] - 2026-09-12

### Changed

- Tested with WordPress 7.1.
- Raised the minimum PHP version to 8.4.
- Composer installs now come from the Pikari package index (`https://hellopikari.github.io/packages/`) and use the release ZIP.

Releases between 0.1.0 and 0.2.0 are listed on [GitHub Releases](https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases).

## [0.1.0] - 2025-09-12

### Added

- Initial release of pikari-gutenberg-query-filter
- [Add initial features here]

[Unreleased]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/compare/v0.3.4...HEAD
[0.3.4]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.3.4
[0.3.3]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.3.3
[0.3.2]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.3.2
[0.3.1]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.3.1
[0.3.0]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.3.0
[0.2.0]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.2.0
[0.1.0]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.1.0
