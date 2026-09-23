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
- **Every filter and sort control now lives inside a real `<form>`,** so filtering works with JavaScript off. This changes markup and store internals a theme or plugin may depend on:
  - **A radio group's `name` is now its query parameter** (for example `query-3-category`), not the block's own UUID. Two Query Filter blocks filtering the same taxonomy in one loop now share one `name` and therefore merge into a single radio group — selecting an option in one visually separate block also selects it in the other, and arrow-key navigation and assistive-technology announcements span both. See [docs/hooks.md](docs/hooks.md#form-and-controls).
  - **The Sort block's `<select>` now has a `name` and a `form` attribute,** so it submits without JavaScript.
  - **Core's Search `<form>` is no longer modified at all.** This plugin used to add its own `data-wp-interactive`, `data-wp-context` and `data-wp-on--submit` to that form; none of that happens anymore. Only the `<input>` (`name`, `value`, `form`, plus this plugin's own directives) and the submit button (`form` only, no `name`) change. Core's own attributes on the `<form>` — including its own `data-wp-interactive="core/search"` on the button-only variant — are left exactly as core renders them, and its `action` is untouched; nothing joins that `<form>` to the loop, the input and button submit the loop's own hidden `<form>` by id instead.
  - **The Search block's `data-wp-context` moved from core's `<form>` to its `<input>`.**
  - **`data-wp-context` on filter and sort block wrappers no longer carries `queryVar` or `pageVar`** — `orderbyVar` and `orderVar` went earlier, with the move to a single `sort` parameter. `view.js` now reads a control's own `name`, `form` and `value` attributes instead.
  - **`wp_interactivity_state( 'searchValue' )` is gone.** Search state now lives entirely in the input's own `data-wp-context`.
  - **The store actions `updateFilters`, `handleSelect`, `handleSort` and `search` are removed,** replaced by `actions.change`, `actions.submit`, `actions.endBurst` and `actions.navigate`. Any theme or plugin code that calls the old action names directly breaks.
  - **The injected loop `<form>` is `novalidate`.** Core marks its Search `<input>` `required`; without `novalidate`, a browser would block every filter submit whenever the search box is empty, since all controls now share one form. This is deliberate, but it means a no-JS submit with an empty search box no longer shows a native "please fill out this field" validation bubble the way core's own search form does on its own.

### Added

- Filters, sorting and search now work in Query Loops that inherit the template's query, so archive, search and home templates can carry them, not just Query Loops with their own query settings. They use unnumbered parameters (`query-{taxonomy}`, `query-post_type`, `query-author`, `query-sort`), core's own `s` for search, and core's own pagination.
- Filtering an inherited loop never changes what the page is: a post type filter is ignored on a post type archive, an author filter narrows an author archive to its own author instead of replacing it, and the archive's title, template and queried object stay put. An unknown term or a filtered date archive returns an empty result instead of a 404. Filtering, sorting or searching from page 2 returns to page 1.
- Filters, sort and search now work **without JavaScript**. Each Query Filter and Sort block renders a `<noscript>` "Apply filters" button, and every control in a loop submits through one shared, hidden `<form>` as a plain GET request. See [docs/hooks.md](docs/hooks.md#form-and-controls) for the markup, and the README's Troubleshooting section for a known no-JS limitation on inherited loops with a Search block.

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
