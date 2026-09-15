# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Radio and checkbox filters now wrap their options in a `<fieldset>` whose `<legend>` names the group for screen readers. The block label used to be a `<label for>` pointing at an id that no element had. The legend keeps the `wp-block-pikari-gutenberg-query-filter__label` class, but theme CSS written as `label.wp-block-pikari-gutenberg-query-filter__label` no longer matches it. Select filters are unchanged.
- Styles that other scripts add at runtime, such as the WPForms honeypot CSS, now also stay enabled after Query Loop enhanced pagination. Core's pagination links navigate with the Interactivity API router without going through this plugin, so the page's forms showed their hidden spam-trap fields after clicking to another page. The styles are now restored after every router navigation.

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

[Unreleased]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.3.0
[0.2.0]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.2.0
[0.1.0]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.1.0
