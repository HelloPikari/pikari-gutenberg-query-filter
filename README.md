# Pikari Gutenberg Query Filter

A WordPress plugin that adds advanced filtering capabilities to Query Loop blocks using the WordPress Interactivity API.

## Features

- **Search Integration**: WordPress core search blocks automatically work within Query Loop blocks
- **Post Type Filtering**: Filter posts by one or multiple post types
- **Taxonomy Filtering**: Filter by categories, tags, and custom taxonomies
- **Author Filtering**: Filter posts by author with cached author lists
- **Sort Controls**: Sort by date or title
- **Advanced Query Loop Support**: Works with both core Query Loop blocks and Advanced Query Loop by Ryan Welcher
- **In-Place Updates**: Results update through the Interactivity API router, without a full page reload
- **Custom Query Loops**: Filters and sort apply to Query Loops with their own query settings. In loops that inherit the template's query (archive and search templates), only the Search block works for now
- **URL-Based State**: Filter state persists in URLs for sharing and bookmarking
- **Theme-Friendly Markup**: Unique classes on every radio and checkbox option, plus PHP filters for the option list, option classes, and label markup

## Requirements

- WordPress 6.8 or higher
- PHP 8.4 or higher
- Modern browser with JavaScript enabled

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
- **Author Filter**: Enable author filtering with cached author lists

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

| Attribute         | Default     | Values                                                   |
| ----------------- | ----------- | -------------------------------------------------------- |
| `filterType`      | `post-type` | `post-type`, `taxonomy`, `author`                        |
| `taxonomy`        | —           | Taxonomy name. Required when `filterType` is `taxonomy`. |
| `displayType`     | `select`    | `select`, `radio`, `checkbox`                            |
| `layoutDirection` | `vertical`  | `vertical`, `horizontal` (radio and checkbox only)       |
| `label`           | Per type    | Label text; defaults to the filter type's name           |
| `showLabel`       | `true`      | `false` keeps the label for screen readers only          |
| `emptyLabel`      | `All`       | Text for the "All" choice                                |

**Sort** (`pikari-gutenberg-query-filter/sort`): `label`, `showLabel`, `emptyLabel`.

## Theming

Radio and checkbox options render as:

```html
<label
	class="wp-block-pikari-gutenberg-query-filter__checkbox-item category_news"
>
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

- **Block Integration**: Modifies core WordPress blocks to add query context support
- **Query Handler**: Processes URL parameters and modifies WP_Query arguments
- **Helper Classes**: Cached data providers for authors, taxonomies, etc.
- **Interactivity API**: Client-side state management and navigation

### Security

- All user inputs are sanitized using WordPress functions
- POST type and taxonomy validation prevents invalid queries
- No database queries without proper validation
- Follows WordPress security best practices

### Performance

- **Caching**: Author lists and other expensive queries are cached
- **Minimal Queries**: Only loads necessary data for active filters
- **Client-Side Navigation**: No page reloads, uses WordPress Interactivity API
- **Lazy Loading**: Scripts only enqueue when blocks are present

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
│   ├── Core/                # Core functionality
│   ├── Helpers/             # Helper classes
│   └── Integrations/        # WordPress integrations
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
- **Advanced Query Loop**: Compatible with Advanced Query Loop by Ryan Welcher
- **Query Settings**: Filters and sort apply to custom queries. Inherited queries support search only

### Themes

- **Block Themes**: Full support for block-based themes
- **Classic Themes**: Works with classic themes that support blocks
- **Custom CSS**: Provides CSS classes for custom styling

### Browsers

- **Modern Browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **JavaScript**: Filters and sort need JavaScript. Without it, only the Search block submits

## Troubleshooting

### Search Block Not Working

- Ensure the search block is placed inside a Query Loop block
- Check that the Query Loop has a valid query configuration
- Verify JavaScript is enabled and no console errors

### Filters Not Updating

- Check that filter blocks are configured with appropriate post types/taxonomies
- Ensure the Query Loop block has compatible query settings
- Review browser console for any JavaScript errors

### A Filter Shows No Results, or Too Few

The Query Loop's own settings still apply once a filter is chosen, and an **offset** is applied _after_ the filter. An offset of 1 skips the newest post in the selected term, not the newest post overall.

A common way to hit this: a Query Loop uses an offset of 1 to skip the featured post shown above it. Filter it to a category with one post and it shows nothing; filter it to any other category and it silently drops that category's newest post.

To keep a post from appearing twice, exclude it by ID instead of using an offset, for example by adding `post__not_in` in a `query_loop_block_query_vars` filter.

### Performance Issues

- Review the number of posts being queried (use pagination)
- Check if author caching is working properly
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
