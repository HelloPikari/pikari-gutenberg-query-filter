# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Unique class on each radio and checkbox option label: `{taxonomy}_{term-slug}`, `post-type_{name}`, or `author_{nicename}`, plus `{key}_all` on the "All" radio. Also applied in the editor preview.
- `pikari_gutenberg_query_filter_options` filter for the option list.
- `pikari_gutenberg_query_filter_option_classes` filter for option label classes.
- `pikari_gutenberg_query_filter_option_label` filter for the markup inside option labels.
- `docs/hooks.md` theming and hooks reference.

### Changed

- The Query Filter block is not rendered when the option list is empty after filtering.

### Deprecated

### Removed

### Fixed

### Security

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

[Unreleased]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.2.0
[0.1.0]: https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases/tag/v0.1.0
