# Pikari Gutenberg Query Filter

A WordPress plugin that adds advanced filtering capabilities to Query Loop blocks using the WordPress Interactivity API.

## Features

- **Search Integration**: WordPress core search blocks automatically work within Query Loop blocks
- **Post Type Filtering**: Filter posts by one or multiple post types
- **Taxonomy Filtering**: Filter by categories, tags, and custom taxonomies
- **Author Filtering**: Filter posts by author
- **Sort Controls**: Sort by date or title, extensible with the `pikari_gutenberg_query_filter_sort_options` filter
- **Advanced Query Loop**: Post type filters include the extra post types chosen in Advanced Query Loop by Ryan Welcher
- **In-Place Updates**: Results update through the Interactivity API router, without a full page reload
- **Works Without JavaScript**: Every filter, sort and search control lives inside a real `<form>`. With JavaScript off, each Query Filter and Sort block shows an "Apply filters" button that submits the whole loop as a plain GET request
- **Custom and Inherited Query Loops**: Filters, sort and search apply to Query Loops with their own query settings, and to loops that inherit the template's query on home, archive and search templates
- **URL-Based State**: Filter state persists in URLs for sharing and bookmarking
- **Theme-Friendly Markup**: Unique classes on every radio and checkbox option, plus PHP filters for the option list, option classes, and label markup
- **Screen Reader Announcements**: Announces the number of results to screen readers after each filter change

## Requirements

- WordPress 6.8 or higher
- PHP 8.4 or higher
- Any modern browser. JavaScript is optional — see [Compatibility](#browsers)

## Installation

### From WordPress Admin

1. Download the plugin ZIP file from the [releases page](https://github.com/HelloPikari/pikari-gutenberg-query-filter/releases)
2. Go to Plugins → Add New → Upload Plugin
3. Select the ZIP file and click "Install Now"
4. Activate the plugin

### Manual Installation

1. Download and extract the plugin files
2. Upload the `pikari-gutenberg-query-filter` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin

### Via Composer

This package is not on Packagist, so add the Pikari package index first:

```json
{
	"repositories": [
		{ "type": "composer", "url": "https://hellopikari.github.io/packages/" }
	]
}
```

```bash
composer require pikari-inc/pikari-gutenberg-query-filter
```

The index serves the same release ZIP published on the releases page, so the
installed plugin already has its `build/` directory compiled. With
`composer/installers` and an `installer-paths` entry for `type:wordpress-plugin`
it lands in `wp-content/plugins/pikari-gutenberg-query-filter/`.

## Upgrading to 1.0

1.0 has breaking changes, listed in full in [CHANGELOG.md](CHANGELOG.md). Check these on a site before updating it:

- **Composer constraint.** A `^0.3` constraint never resolves 1.0.0. Change it to `^1.0`.
- **Plugin folder name.** Composer installs the plugin as `wp-content/plugins/pikari-gutenberg-query-filter/`. If the site had it in a folder with another name, WordPress sees a different plugin and it's left inactive. Reactivate it after deploying.
- **Saved links.** Sort links that use `orderby` and `order` stop sorting, and fall back to the loop's own order. Author links with a numeric ID keep working.
- **Empty labels.** A filter whose Label was cleared to hide it now shows the default label. Turn off **Show Label** instead; that keeps the label for screen readers.
- **Duplicate filters.** Two radio filters for the same taxonomy in one Query Loop now share a name, so they act as one radio group. The editor shows a notice when a loop has two filters for the same parameter.
- **Custom JavaScript.** The store actions `updateFilters`, `handleSelect`, `handleSort` and `search` are gone, and so are `queryVar` and `pageVar` in block context. Code that calls them needs updating; see [docs/hooks.md](docs/hooks.md#form-and-controls).
- **Custom PHP.** In `pikari_gutenberg_query_filter_options`, an author option's `value` is now the nicename, not the user ID, so a callback comparing it to an ID stops matching. `SortHelper`, `AbstractQueryHelper` and several helper methods are removed. They were never documented, but code calling them breaks.
- **Stray parameters on archives.** Any `query-*` parameter now filters the main query on home, archive and search pages, even on a page with no filter block. In 0.3.x those parameters did nothing there.
- **Stricter URL values.** At most 50 values per parameter are read. A taxonomy must pass `is_taxonomy_viewable()`, and `attachment` is accepted only when attachment pages are enabled. Hand-built links that relied on anything else stop filtering.
- **Page caches.** A full-page cache or CDN must key on the whole query string; see [Page-Level Caching](#page-level-caching).

Theme CSS written against the documented classes is unaffected: the BEM classes and the `{key}_{slug}` option classes are unchanged. Structural selectors can change, though. The hidden loop `<form>` is now the Query Loop's last child, so `.wp-block-query > :last-child` matches it.

## Usage

### Basic Setup

1. Create a Query Loop block in the WordPress block editor
2. Add your desired filter blocks inside the Query Loop:
   - **Query Filter Block**: For post types, taxonomies, and authors
   - **Sort Block**: For sorting options
   - **WordPress Search Block**: For search functionality (automatically detected)

### Filter Block Configuration

The Query Filter block provides multiple filter types:

- **Post Type Filter**: Choose which post types to include in the filter
- **Taxonomy Filter**: Select taxonomies (categories, tags, custom taxonomies) to filter by
- **Author Filter**: Lists authors with published posts; the list is cached for up to an hour

Each filter has a **Display Type** — Select (dropdown), Radio (single choice), or Checkbox (multiple choice) — and radio and checkbox groups can be laid out vertically or horizontally.

### Search Block Integration

Simply add a WordPress core Search block inside a Query Loop block - it will automatically:

- Detect the Query Loop context
- Use appropriate search parameters
- Reset pagination when searching
- Integrate with other filters

### Sort Block

Add sort controls to allow users to sort posts by:

- Date (newest/oldest)
- Title (A-Z/Z-A)

## Examples

### Blog with Filters

Both blocks are dynamic, so they serialize as self-closing comments. Each Query Filter block handles one filter type; add one block per filter.

```html
<!-- wp:query {"queryId":1,"query":{"perPage":10,"postType":"post","inherit":false}} -->
<div class="wp-block-query">
	<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"taxonomy","taxonomy":"category","displayType":"checkbox","layoutDirection":"horizontal"} /-->

	<!-- wp:pikari-gutenberg-query-filter/query-filter {"filterType":"author"} /-->

	<!-- wp:pikari-gutenberg-query-filter/sort /-->

	<!-- wp:search {"label":"Search","buttonText":"Search"} /-->

	<!-- wp:post-template -->
	<!-- wp:post-title {"isLink":true} /-->
	<!-- /wp:post-template -->
</div>
<!-- /wp:query -->
```

### Block Attributes

**Query Filter** (`pikari-gutenberg-query-filter/query-filter`):

| Attribute         | Default     | Values                                                                 |
| ----------------- | ----------- | ---------------------------------------------------------------------- |
| `filterType`      | `post-type` | `post-type`, `taxonomy`, `author`                                      |
| `taxonomy`        | —           | Taxonomy name. Required when `filterType` is `taxonomy`.               |
| `displayType`     | `select`    | `select`, `radio`, `checkbox`                                          |
| `layoutDirection` | `vertical`  | `vertical`, `horizontal` (radio and checkbox only)                     |
| `label`           | Per type    | Label text. Empty uses the filter type's name; hide it with showLabel. |
| `showLabel`       | `true`      | `false` keeps the label for screen readers only                        |
| `emptyLabel`      | `""`        | Text for the "All" choice. Empty shows "All".                          |

**Sort** (`pikari-gutenberg-query-filter/sort`): `label` (empty shows "Sort By"), `showLabel` (`true`), and `emptyLabel` (empty shows "Default"). The Default choice appears only when the loop's own order matches no sort option.

## Theming

Radio and checkbox options render as:

```html
<label
	class="wp-block-pikari-gutenberg-query-filter__checkbox-item category_news"
>
	<!-- name, form and directives omitted; see docs/hooks.md -->
	<input type="checkbox" value="news" />
	<span class="wp-block-pikari-gutenberg-query-filter__checkbox-text"
		>News</span
	>
</label>
```

Each option label gets a unique `{key}_{slug}` class — `category_news`, `post-type_page`, `author_jane-doe`, and `category_all` for the "All" radio — so themes can style individual options. Developers can change the option list, label classes, and label markup with the `pikari_gutenberg_query_filter_options`, `pikari_gutenberg_query_filter_option_classes`, and `pikari_gutenberg_query_filter_option_label` filters.

See [docs/hooks.md](docs/hooks.md) for the full markup, class rules, filter parameters, and examples.

## Architecture

### Plugin Structure

- **Block Integration** (`Integrations\BlockFilters`): makes every Query Loop a router region, joins core's Search input to the loop, and injects each loop's hidden `<form>`
- **Custom loops** (`Core\QueryLoopHandler`): applies URL parameters on `query_loop_block_query_vars`
- **Inherited loops** (`Integrations\MainQueryFilter`): applies them to the main query on `pre_get_posts`
- **URL and query logic** (`Url\*`, `Query\*`): parameter names, parsing and validation, query arguments, sort options and result counts
- **Helpers**: option lists for post types, terms and authors
- **Interactivity API**: client-side navigation and screen reader announcements

### Security

- URL input is sanitized or validated against an allowlist before it reaches a query: viewable post types and taxonomies, existing users, and the sort options list
- Sort can't set arbitrary `orderby`, `order` or `meta_key` values
- No database queries without proper validation
- Follows WordPress security best practices

### Performance

- **Caching**: The author list is cached in a transient for up to an hour. Term and post type lists aren't cached by the plugin
- **Client-Side Navigation**: With JavaScript, results update without a full page reload
- **Lazy Loading**: The view script loads only when a filter or Sort block is on the page, or a core Search block sits inside a Query Loop

### Page-Level Caching

A full-page cache or CDN in front of the site **must key on the whole query string**, not on a subset of it.

Filter state lives in ordinary URL query parameters, and each loop's hidden `<form>` re-emits **every** parameter of the current request as a hidden input, so that submitting a filter without JavaScript doesn't drop the rest of the URL. Between them, any parameter a cache leaves out of its key can leak from one visitor's cached page into another visitor's form:

- **A cache that ignores `query-*`, `s` or `paged`** can serve one visitor's filtered, sorted or searched results to a different visitor who asked for the unfiltered page.
- **A cache that keys on those but strips what it treats as tracking noise** — `utm_*`, `fbclid`, `gclid`, which is the usual default — bakes visitor A's campaign parameters into the cached page's hidden inputs and serves them to visitor B, whose Apply then re-submits A's campaign as if it were their own.

Configure the cache to key on the full URL, or explicitly allowlist every parameter it would otherwise drop.

## Development

### Setup Development Environment

```bash
# Clone the repository
git clone https://github.com/HelloPikari/pikari-gutenberg-query-filter.git
cd pikari-gutenberg-query-filter

# Install dependencies
npm install
composer install

# Start development build with file watching
npm start

# Production build
npm run build
```

### Available Scripts

```bash
# Development
npm start              # Start development build with file watching
npm run build         # Create production build
npm run plugin-zip    # Create distribution ZIP file

# Code Quality
npm run lint:all      # Run all linters
npm run lint:fix      # Auto-fix linting issues
npm run lint:php      # PHP linting only
npm run lint:js       # JavaScript linting only
npm run lint:css      # CSS linting only

# Testing
npm test              # Run JavaScript tests
composer test         # Run PHP tests

# WordPress Playground
npm run playground    # Start local WordPress environment

# Translations (requires wp-env to be running)
npm run i18n:pot     # Generate .pot template from source files
npm run i18n:mo      # Compile all .po files to .mo
npm run i18n:json    # Generate JSON files for JS translations
npm run i18n         # Run all i18n steps (pot + mo + json)
```

### Translations

Translation files live in the `languages/` directory. The plugin ships with French Canadian (fr_CA) translations.

To update translations after changing translatable strings:

1. Start wp-env: `npm run wp-env`
2. Regenerate the `.pot` template: `npm run i18n:pot`
3. Update existing `.po` files with new strings: `msgmerge --update languages/pikari-gutenberg-query-filter-fr_CA.po languages/pikari-gutenberg-query-filter.pot`
4. Translate any new `msgid` entries in the `.po` file
5. Compile all translation files: `npm run i18n:mo && npm run i18n:json`

To add a new locale, create a `.po` file from the `.pot` template using `msginit`:

```bash
msginit --input=languages/pikari-gutenberg-query-filter.pot \
        --output-file=languages/pikari-gutenberg-query-filter-{locale}.po \
        --locale={locale} --no-translator
```

### Project Structure

```text
pikari-gutenberg-query-filter/
├── includes/                 # PHP classes
│   ├── Core/                # Custom-loop query adapter
│   ├── Helpers/             # Option lists
│   ├── Integrations/        # Core block and main-query integrations
│   ├── Query/               # Query arguments, sort options, result counts
│   └── Url/                 # Parameter names, filter state, the loop form
├── src/                     # Source files
│   ├── blocks/              # Block definitions
│   │   ├── query-filter/    # Main filter block
│   │   └── sort/            # Sort control block
│   ├── components/          # Shared editor components
│   └── utils/               # Shared JS helpers
├── docs/                    # Theming/hooks reference, release guide
├── build/                   # Compiled assets (gitignored)
├── tests/                   # Test files
└── _playground/             # WordPress Playground config
```

### Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes following the coding standards in `CLAUDE.md`
4. Run tests and linting (`npm run lint:all && npm test`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## Compatibility

### WordPress

- **Core Query Loop**: Full support for WordPress core Query Loop blocks
- **Advanced Query Loop**: Post type filters include AQL's extra post types in custom loops. In AQL's inherited mode, the Sort block has no effect. There is no automated test against AQL
- **Query Settings**: Filters, sort and search apply to custom queries and to inherited queries on home, archive and search templates

### Themes

- **Block Themes**: Full support for block-based themes
- **Custom CSS**: Provides CSS classes for custom styling

### Browsers

- **Modern Browsers**: The view script is an ES module loaded through an import map, which needs Chrome or Edge 89+, Firefox 108+, or Safari 16.4+. An older browser with JavaScript on gets neither in-place updates nor the no-JS button
- **JavaScript**: Optional. With it, results update in place through the Interactivity API router. Without it, every filter, sort and search control still works — each Query Filter and Sort block shows an "Apply filters" button, and core's Search block uses its own submit button; either submits the whole loop as a normal GET request and reloads the page

## Troubleshooting

### Search Block Not Working

- Ensure the search block is placed inside a Query Loop block
- Check that the Query Loop has a valid query configuration
- Check the browser console for errors. With JavaScript off, search submits with a full page load; that's expected

### Filters Not Updating

- Check that filter blocks are configured with appropriate post types/taxonomies
- Ensure the Query Loop block has compatible query settings
- Review browser console for any JavaScript errors

### A Filter Shows No Results, or Too Few

The Query Loop's own settings still apply once a filter is chosen, and an **offset** is applied _after_ the filter. An offset of 1 skips the newest post in the selected term, not the newest post overall.

A common way to hit this: a Query Loop uses an offset of 1 to skip the featured post shown above it. Filter it to a category with one post and it shows nothing; filter it to any other category and it silently drops that category's newest post.

To keep a post from appearing twice, exclude it by ID instead of using an offset, for example by adding `post__not_in` in a `query_loop_block_query_vars` filter.

### A No-JS Search Submit Lands on the Search Page, Not the Archive

**Known limitation.** On an inherited loop (an archive or search template) that contains a core Search block, clicking "Apply filters" with JavaScript off and an empty search box submits `s=` along with the other filters. WordPress's template hierarchy checks `is_search` before `is_archive`, so the response renders the site's search template instead of the archive template the visitor started on. The filtered results themselves are still correct — only the page's template and identity change for that one request.

This doesn't happen with JavaScript enabled: the plugin's `buildUrl()` omits empty values, so an empty search box never adds `s=` to the URL in the first place. There's no fix planned for the no-JS path short of a site-wide `request` filter that overrides core's own template selection, which is outside this plugin's scope.

### Performance Issues

- Review the number of posts being queried (use pagination)
- The author list is cached for up to an hour. After renaming a user, or on a site with a persistent object cache, delete the `pikari_query_filter_authors_*` transients to refresh it
- Consider limiting the number of filter options

## License

GPL-2.0-or-later - see [LICENSE](LICENSE) file for details.

## Author

**Pikari Inc.**

- Website: <https://pikari.io>
- Email: development@pikari.io
- GitHub: <https://github.com/HelloPikari>

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## Support

- **Documentation**: See [docs/](docs/) folder for detailed guides
- **Issues**: Report bugs on [GitHub Issues](https://github.com/HelloPikari/pikari-gutenberg-query-filter/issues)
