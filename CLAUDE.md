# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

pikari-gutenberg-query-filter is a WordPress plugin that Filter controls for the query loop block, using the interactivity API.

## Development Commands

### Build and Development

```bash
# Install dependencies
npm install
composer install

# Start development build with file watching
npm start

# Production build
npm run build

# Create plugin ZIP for distribution
npm run plugin-zip
```

### Code Quality

```bash
# Run all linting
npm run lint:all

# Auto-fix linting issues
npm run lint:fix

# Run PHP linting only
npm run lint:php

# Run JavaScript linting only
npm run lint:js

# Run CSS linting only
npm run lint:css
```

### Asset Compilation Best Practices

**IMPORTANT**: Always prefer building assets over running the dev server for task completion.

```bash
# Build assets for production (preferred for task completion)
npm run build

# Only use dev server when actively developing/testing in browser
npm run start
```

### Testing

```bash
# Run JavaScript tests
npm test

# Run PHP tests
composer test

# Run end-to-end tests (Playwright, wp-env tests instance on port 5885)
npm run build          # E2E tests use build/, so build first
npx wp-env start       # afterStart sets pretty permalinks on the tests instance
npm run test:e2e       # all specs
npm run test:e2e -- tests/e2e/specs/filters.spec.js   # one spec
```

The E2E global setup (`tests/e2e/setup/fixtures.js`) deletes and recreates all posts, pages, non-admin users and categories on the tests instance each run. Fixture data and expected results live in `tests/e2e/fixtures/content.js`. CI doesn't run E2E yet (roadmap #28).

`test:e2e` checks for and installs Playwright browsers on each run; set `PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1` to skip it once they're installed.

### WordPress Playground

```bash
# Start local WordPress Playground
npm run playground
```

### Translations

Translation files are in `languages/`. All commands require wp-env to be running (`npm run wp-env`).

```bash
# Generate .pot template from source files
npm run i18n:pot

# Compile .po files to .mo (PHP translations)
npm run i18n:mo

# Generate JSON files for JS editor translations
npm run i18n:json

# Run all i18n steps in sequence
npm run i18n
```

**Workflow for updating translations after code changes:**

1. Run `npm run i18n:pot` to regenerate the `.pot` template
2. Run `msgmerge --update languages/pikari-gutenberg-query-filter-fr_CA.po languages/pikari-gutenberg-query-filter.pot` to merge new strings into the `.po` file
3. Translate any new `msgid` entries in the `.po` file
4. Run `npm run i18n:mo && npm run i18n:json` to compile

**Adding a new locale:**

```bash
msginit --input=languages/pikari-gutenberg-query-filter.pot \
        --output-file=languages/pikari-gutenberg-query-filter-{locale}.po \
        --locale={locale} --no-translator
```

## Coding Standards

### PHP Coding Standards

- Follow WordPress Coding Standards with **4 spaces indentation (NOT TABS)**
- This project's phpcs.xml enforces space-based indentation
- Use meaningful function and variable names with underscores (not camelCase)
- Prefix all global functions with your plugin/theme prefix
- Document all functions with proper PHPDoc blocks
- Use WordPress functions when available (e.g., `wp_remote_get()` instead of `curl`)

### JavaScript Standards

- Use WordPress ESLint configuration
- Single quotes for strings
- Space indentation as configured in .eslintrc.cjs
- Meaningful variable names in camelCase
- Use `wp` global for WordPress JavaScript APIs

### CSS/SCSS Standards

- Follow WordPress CSS coding standards
- Use semantic, descriptive class names
- Prefix all CSS classes with your plugin/theme prefix
- Mobile-first responsive design
- Use CSS custom properties for theme compatibility

### HTML Standards

- Use semantic HTML5 elements
- Ensure proper accessibility (ARIA labels, alt text, etc.)
- Follow WordPress HTML coding standards
- Validate HTML output

### Database Queries

- Use WordPress database APIs (`$wpdb`)
- Always prepare SQL queries to prevent injection
- Cache expensive queries using transients
- Follow WordPress database schema conventions

## WordPress Data Fetching Best Practices

### Use useEntityRecords for Data Fetching

When fetching WordPress data in React components (Gutenberg blocks, editor plugins, etc.), **always use `useEntityRecords`** instead of the older `useSelect` + `getEntityRecords` pattern.

#### Why useEntityRecords?

- Modern WordPress pattern (recommended approach)
- Cleaner, more concise code
- Better state management
- Automatic handling of loading states
- Follows WordPress best practices

#### Example Usage

```javascript
import { useEntityRecords } from '@wordpress/core-data';

// Fetch posts, pages, or custom post types
const { records, isResolving, hasResolved } = useEntityRecords(
	'postType',
	'post', // or 'page', 'attachment', 'custom-post-type'
	{
		per_page: 10,
		orderby: 'date',
		order: 'desc',
		// Add any REST API query parameters
	}
);

// For media/attachments with specific mime type
const { records: pdfs } = useEntityRecords('postType', 'attachment', {
	media_type: 'application/pdf',
	per_page: 100,
});
```

#### Common Query Parameters

- `per_page`: Number of items to fetch
- `orderby`: Sort field (date, title, menu_order, etc.)
- `order`: Sort direction (asc, desc)
- `media_type`: For attachments (e.g., 'application/pdf', 'image/jpeg')
- `_fields`: Limit returned fields for performance
- `search`: Search term
- `status`: Post status (publish, draft, etc.)

### Code Style and Linting

**IMPORTANT**: All generated code MUST follow the linting configurations defined in this project:

- **PHP**: Use 4 spaces for indentation (NO TABS) - see phpcs.xml
- **JavaScript**: Follow ESLint configuration (ESLint handles all JS formatting)
- **CSS/SCSS**: Follow Stylelint configuration (Prettier formats CSS/SCSS)
- Generated code must pass all linting checks without modifications

Note: Prettier is configured to ignore JavaScript files. ESLint handles all JavaScript formatting to ensure WordPress coding standards are followed.

### General Principles

- Write clean, readable, and maintainable code
- Follow the principle of least surprise
- Prefer clarity over cleverness
- Use meaningful variable and function names
- Keep functions small and focused on a single responsibility
- Comment complex logic, not obvious code
- Maintain consistent formatting (enforced by linters)

### Translation and Internationalization Requirements

**CRITICAL**: Always review code for proper internationalization and consistency:

#### Translation Functions

- **Always check for missing translation functions** on frontend-facing strings
- Any user-visible text MUST use appropriate WordPress i18n functions: `__()`, `_e()`, `_n()`, `_x()`, etc.
- Ask if untranslated strings should be fixed when found
- Server-side (PHP): Use `__( 'Text', 'pikari-gutenberg-query-filter' )`
- Client-side (JS): Use `__( 'Text', 'pikari-gutenberg-query-filter' )`

#### Text Domain Consistency

- **Always verify text domain**: Must be `'pikari-gutenberg-query-filter'` (as defined in main plugin file)
- Check both PHP and JavaScript files for consistency
- Never use shortened domains like `'query-filter'`
- Interactivity API store names should also use full plugin name for consistency

#### WordPress Interactivity API Standards

- **Store Name**: This plugin uses `'pikari/gutenberg-query-filter'` as the interactivity store name
- **HTML Attribute**: Use `data-wp-interactive="pikari/gutenberg-query-filter"` in render.php files
- **JavaScript Store**: Use `store( 'pikari/gutenberg-query-filter', { ... } )` in view.js files
- **Consistency Rule**: Store name MUST match across all render.php and view.js files
- **Why Full Name**: Prevents conflicts with other plugins using similar short names

#### CSS Class Name Standards

- **Always review CSS class names** when copying code from other codebases
- All plugin-specific classes MUST include plugin name: `wp-block-pikari-gutenberg-query-filter-*`
- Example: `wp-block-pikari-gutenberg-query-filter-post-type__select`
- Avoid generic names like `wp-block-query-filter` that could conflict
- Maintain BEM methodology: `block__element--modifier`

#### Review Checklist

When working with frontend code, always:

1. ✅ Scan for user-facing strings without translation functions
2. ✅ Verify all text domains match `'pikari-gutenberg-query-filter'`
3. ✅ Check CSS classes follow plugin naming convention
4. ✅ Test that interactivity store names are consistent

### Documentation

- Document all public APIs
- Include examples in documentation
- Keep documentation up-to-date with code changes
- Use inline comments sparingly and only when necessary

### Error Handling

- Always handle errors appropriately
- Provide meaningful error messages
- Log errors for debugging but don't expose sensitive info
- Fail fast and fail clearly

### Performance

- Optimize for readability first, performance second
- Profile before optimizing
- Avoid premature optimization
- Consider caching for expensive operations

## Architecture

### Project Structure

- `pikari-gutenberg-query-filter.php` - Main plugin/theme file with WordPress headers
- `includes/` - PHP classes and core functionality (PSR-4 autoloaded)
- `src/` - JavaScript and SCSS source files
- `build/` - Compiled assets (gitignored, created by build process)
- `languages/` - Translation files (.pot, .po, .mo)
- `tests/` - Unit and integration tests
- `bin/` - Utility scripts (e.g., release automation)
- `_playground/` - WordPress Playground configuration

### Key WordPress Patterns

- Use WordPress hooks: `add_action()`, `add_filter()`, `remove_action()`, `remove_filter()`
- Enqueue scripts and styles properly using `wp_enqueue_script()` and `wp_enqueue_style()`
- Register scripts/styles first with `wp_register_script()` when reusing
- Use WordPress APIs for all operations (database, HTTP requests, filesystem)
- Follow WordPress file naming conventions
- Use WordPress template hierarchy for themes

### Dependencies

- WordPress 6.8
- PHP 8.4
- Node.js for build tools
- Composer for PHP dependencies

## Frontend Rendering & Extensibility

`docs/hooks.md` is the public contract for theme authors: markup, classes, and PHP filters. Read it before changing anything the Query Filter block outputs, and update it in the same change.

### Query Filter render flow

1. `Url\QueryParams::from_block( $block )` resolves this loop's parameter names from its block context (`queryId`, or none for `query-0-`): `$params->key( $name )` for a filter's URL variable, `$params->page_key()` for its pagination key. Both `src/blocks/query-filter/render.php` and `src/blocks/sort/render.php` start here.
2. `src/blocks/query-filter/render.php` loads raw items for the block's `filterType`: `FilterHelper::get_filter_post_types()`, `FilterHelper::get_taxonomy_filter_terms()`, or `AuthorHelper::get_filter_authors()`.
3. `FilterHelper::get_filter_options( $items, $attributes )` normalizes items into `value` / `label` / `slug` / `item` arrays and applies `pikari_gutenberg_query_filter_options`.
4. Radio groups prepend `FilterHelper::get_all_option()`, the "All" choice, which stays outside the options filter. For each radio or checkbox option, `FilterHelper::get_option_classes()` builds the `<label>` classes, including the unique `{key}_{slug}` class, and applies `pikari_gutenberg_query_filter_option_classes`.
5. `FilterHelper::get_option_label_html()` builds the markup after the `<input>`, applies `pikari_gutenberg_query_filter_option_label`, and sanitizes it with `wp_kses_post()`.
6. Every control carries a `name` and a `form`, and `BlockFilters::render_block_query()` injects one hidden `<form>` per Query Loop through `Url\LoopForm`, so the browser's own form ownership answers "what filters this loop?". On a change, `src/blocks/query-filter/view.js` reads that form with `FormData` and `form.elements`, and `buildUrl()` (`src/utils/build-url.js`) rewrites only the names the form owns, leaving every other URL parameter untouched, before navigating with `@wordpress/interactivity-router`. The router only swaps in the new results because `BlockFilters::render_block_query()` gives the core/query wrapper both `data-wp-router-region` and `data-wp-interactive`. Without the interactive attribute the router fetches the filtered page and silently discards it — the URL changes, the results do not.

### Applying filters to the query

This is the other half of the round trip: turning the URL parameters `render.php` reads back into `WP_Query` arguments. It's a separate path from rendering options above and doesn't touch `FilterHelper`.

- `Core\QueryLoopHandler` hooks `query_loop_block_query_vars` at priority 19. It's a thin adapter: for a custom Query Loop (one with a `queryId`, or none, falling back to `query-0-`) it builds `Url\QueryParams::from_block( $block )`, reads `Url\FilterState::for_loop( $params )`, and merges the result into the loop's query arguments with `Query\QueryArgs::apply()`.
- `Url\FilterState::for_loop()` parses and validates `$_GET` once per loop prefix per request — core rebuilds a custom loop's query vars up to 6 times per page — and memoizes the result; `FilterState::reset_cache()` clears it between tests. `FilterState::from_array()` is the uncached constructor tests call directly.
- `Query\QueryArgs::apply()` is a pure merge, with no WordPress calls of its own: it overrides `post_type` (leaving `post__in` alone), builds one `tax_query` clause per filtered taxonomy (nesting the loop's own `tax_query` unchanged when it already has one, rather than replacing its relation), sets `author__in` and `s`, sets `orderby` / `order` / `meta_key` from the resolved sort option, and sets `ignore_sticky_posts` once any filter is active and the loop hasn't already decided.
- Sort uses one allowlist everywhere: `Query\SortOptions::all()` supplies the Sort block's `<select>` options and is filterable with `pikari_gutenberg_query_filter_sort_options`; `SortOptions::find()` validates the `sort` URL parameter against that same list; `SortOptions::match()` finds the option matching a loop's own default order (from block context for a custom loop) so selecting it renders with an empty value and clears the parameter.
- Inherited Query Loops (archive, search and home templates) aren't touched by `QueryLoopHandler` — `modify_query()` returns early for them. They're filtered separately, through `Integrations\MainQueryFilter` on `pre_get_posts`; see spec §4.4 for the rules.

### Extension rules

- Option logic lives in `FilterHelper`, not `render.php`. `render.php` is not unit-tested; keep it a loop over helper output. Do not reintroduce per-filter-type `switch` blocks there.
- Query-argument logic lives in `Query\QueryArgs`, sort logic in `Query\SortOptions`, and URL parsing/validation in `Url\FilterState` and `Url\QueryParams` — not in `Core\QueryLoopHandler`, which stays a thin adapter over them.
- New hooks use the `pikari_gutenberg_query_filter_` prefix and ship with a PHPDoc block at the `apply_filters()` call, a Brain\Monkey test (`Filters\expectApplied`), and a section in `docs/hooks.md`.
- Keep the `<input>` outside filterable markup. `view.js` depends on its `type`, `value`, `name`, `form`, and `data-wp-on--change` attributes. The `form` attribute is what joins the control to its loop's hidden form, so dropping it takes the control out of both the JavaScript URL and the no-JavaScript submit.
- Unique option classes (`category_news`, `post-type_page`, `author_jane-doe`, `category_all`) are deliberately **unprefixed** — a product decision (2026-09-12) and the one exception to the CSS Class Name Standards below. Do not add the plugin prefix to them.
- The `{key}_{slug}` format is implemented twice: `FilterHelper::get_option_classes()` for the frontend and `src/utils/option-class-name.js` for the editor preview. Change both together, with their tests.
- Existing BEM classes (`__radio-item`, `__checkbox-item`, `__radio-text`, `__checkbox-text`, `__*-group`, `__select`, `__label`) are public. Do not rename them.
- The Sort block has no radios or checkboxes, so none of these three option filters apply to it. It has its own filter instead, `pikari_gutenberg_query_filter_sort_options` (see `docs/hooks.md`). The form contract does apply to it: its `<select>` carries a `name` and a `form` like any other control, and it renders a `<noscript>` submit button, so sorting works without JavaScript.
- Label text is resolved by `FilterHelper::get_label()` in both `render.php` files, and mirrored by `label?.trim() ? label : default` in both `edit.js` files. Empty means default. Never store a default label as an attribute.

### Tests for this area

- `tests/php/FilterHelperTest.php` — Brain\Monkey. Plugin classes load through the composer `autoload.psr-4` entry for `includes/`; run `composer dump-autoload` after adding a class.
- `tests/php/QueryParamsTest.php` — URL parameter names per loop: the `query-{id}-` prefix, the `queryId: 0` vs. missing-`queryId` distinction, and inherited-loop keys.
- `tests/php/FilterStateTest.php` — parsing and validating `$_GET` into post types, taxonomies, author IDs, search and sort for one loop (spec §3.2).
- `tests/php/QueryArgsTest.php` — merging `FilterState` into `WP_Query` arguments: `post__in`, `tax_query` relations, sticky posts.
- `tests/php/QueryLoopHandlerTest.php` — the `query_loop_block_query_vars` adapter: hook registration, prefix resolution, the inherited-loop early return.
- `tests/php/SortOptionsTest.php` — the sort allowlist that keeps `orderby`/`order`/`meta_key` off arbitrary URL input.
- `tests/php/BlockFiltersTest.php` — core block integrations: router-region markup, Search block naming, block-style versioning, unique ID reservation.
- `tests/unit/utils/option-class-name.test.js` — Jest. The `{key}_{slug}` option class format, mirrored from `FilterHelper::get_option_classes()`.
- `tests/unit/blocks/block-metadata.test.js` — Jest. `block.json` fields match what the build actually produces.
- `tests/unit/utils/filter-notices.test.js` — Jest. Which editor notices apply, and duplicate detection per Query Loop (nested loops are separate).
- `tests/unit/utils/preview-queries.test.js` — Jest. The editor preview's REST queries match `FilterHelper` and `AuthorHelper`.
- `tests/unit/blocks/query-filter/variations.test.js` — Jest. Variations store no `label`.
- `tests/e2e/specs/` — Playwright, against a seeded wp-env. Filtering, sorting, sticky posts, author nicenames (including old numeric links), and injected styles surviving enhanced pagination.

## Git Workflow

- Main branch: `main`
- Feature branches: `feature/description`
- Bugfix branches: `fix/description`
- **IMPORTANT**: Always create new branches off main when starting new tasks
  - Switch to main: `git checkout main`
  - Update main: `git pull origin main`
  - Create new branch: `git checkout -b feature/task-description`
- Commit format: `type: Brief description`
  - Types: feat, fix, docs, style, refactor, test, chore
- Pre-commit hooks run linting automatically via Husky
- All commits must pass linting

## Testing

- JavaScript tests in `tests/unit/`
- PHP tests in `tests/` following PHPUnit structure
- Run all tests before submitting PR
- Write tests for new features and bug fixes
- Aim for good test coverage

## Security Considerations

### WordPress Security Best Practices

#### Output Escaping

- `esc_html()` - For plain text output
- `esc_attr()` - For HTML attribute values
- `esc_url()` - For URLs
- `esc_js()` - For inline JavaScript (deprecated, avoid inline JS)
- `wp_kses_post()` - For content with allowed HTML
- `esc_textarea()` - For textarea content

#### Input Sanitization

- `sanitize_text_field()` - For plain text input
- `sanitize_email()` - For email addresses
- `sanitize_url()` - For URLs
- `sanitize_key()` - For keys and slugs
- `wp_kses_post()` - For content with HTML
- `absint()` - For positive integers
- `intval()` - For integers

#### Nonces

- Always use nonces for forms and AJAX requests
- `wp_nonce_field()` - Add nonce to forms
- `check_admin_referer()` - Verify nonce in admin
- `wp_verify_nonce()` - Verify nonce programmatically

#### Capabilities

- Always check user capabilities before operations
- `current_user_can()` - Check if user has capability
- Use appropriate capabilities (e.g., 'edit_posts', 'manage_options')
- Never check for roles directly, always use capabilities

#### SQL Security

- Use `$wpdb->prepare()` for all queries with variables
- Never concatenate user input into SQL
- Use WordPress query functions when possible
- Validate and sanitize all database inputs

### Input Validation

- Never trust user input
- Validate all input on the server side
- Use allowlists over blocklists when possible
- Validate data type, length, format, and range

### Output Escaping

- Escape all output based on context
- Escape late (right before output)
- Use context-appropriate escaping functions

### Authentication & Authorization

- Check user permissions before any sensitive operation
- Use secure session management
- Implement proper access controls
- Never store passwords in plain text

### Data Protection

- Use HTTPS for all communications
- Encrypt sensitive data at rest
- Follow the principle of least privilege
- Never commit secrets or API keys to version control
- Use environment variables for sensitive configuration

### Dependencies

- Keep all dependencies up to date
- Regularly audit dependencies for vulnerabilities
- Only use trusted packages from reputable sources
- Review dependency licenses for compatibility

### Code Quality & Security Patterns

#### Input Validation & Sanitization

- **Always validate numeric IDs**: Use `absint()` for query IDs before using in `sprintf()`
- **GET parameter filtering**: Use phpcs ignore for nonce verification when handling filter parameters (`// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtering parameters don't require nonces.`)
- **Validate existence**: Use `post_type_exists()`, `taxonomy_exists()` before using in queries
- **Sanitize arrays**: Use `array_filter()` and `array_map()` for cleaning arrays from user input

#### Error Handling

- **JSON encoding**: Always check `wp_json_encode()` return value (can return false)
- **Database queries**: Validate query parameters exist before using
- **Context access**: Use null coalescing operator (`??`) for optional context values
- **Early returns**: Return early when required data is missing

#### Performance Considerations

- **Avoid serialization**: Use `wp_json_encode()` instead of `serialize()` for cache keys when possible
- **Cache expensive operations**: Use transients for database-heavy operations
- **Validate before sprintf**: Always validate numeric values before using in `sprintf()`

#### WordPress Integration

- **Filter parameters**: GET parameters for filtering (post types, taxonomies, etc.) don't require nonce verification
- **Block context**: Always validate block context data types and existence
- **Hook priorities**: Use higher priorities (20+) for render hooks to ensure proper timing

## Important Notes

- This project uses Husky for pre-commit hooks
- All PRs must pass CI checks (linting, tests, build)
- The `build/` folder is gitignored but required for the plugin to function
- Releases are built by `release.yml` from the tag on `main`; the ZIP asset it uploads is what both Composer and manual installs consume
- Compatible with WordPress 6.0+
- Requires PHP 8.4+
- Uses `@wordpress/scripts` for build tooling
- Follow WordPress plugin/theme guidelines for wordpress.org submission

## Release Process

See GitHub Releases for automated releases via Release Drafter

## WordPress-Specific Guidelines

### Block Editor (Gutenberg)

- Use `@wordpress/*` packages for block editor functionality
- Register blocks properly with `register_block_type()`
- Provide block.json for block metadata
- Support WordPress core blocks where applicable

### Internationalization

- All user-facing strings must be translatable
- Use proper text domains: `__()`, `_e()`, `_n()`, `_x()`, etc.
- Text domain must match plugin/theme slug
- Generate .pot files for translators

### Performance

- Minimize database queries
- Use object caching when available
- Lazy load assets and functionality
- Follow WordPress performance best practices

### Backwards Compatibility

- Maintain compatibility with supported WordPress versions
- Check for function existence when using newer functions
- Provide graceful degradation

## Quick Reference

### Common WordPress Functions

```php
// Escaping
esc_html( $text )
esc_attr( $text )
esc_url( $url )
wp_kses_post( $content )

// Sanitization
sanitize_text_field( $input )
sanitize_email( $email )
absint( $number )

// Capabilities
current_user_can( 'edit_posts' )
current_user_can( 'manage_options' )

// Nonces
wp_nonce_field( 'action_name' )
wp_verify_nonce( $_POST['_wpnonce'], 'action_name' )
```

### WP-CLI Commands

```bash
# Useful during development
wp cache flush
wp rewrite flush
wp cron run --all
```
