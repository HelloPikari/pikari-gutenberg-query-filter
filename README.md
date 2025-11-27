# Pikari Gutenberg Query Filter

A WordPress plugin that adds advanced filtering capabilities to Query Loop blocks using the WordPress Interactivity API.

## Features

- **Search Integration**: WordPress core search blocks automatically work within Query Loop blocks
- **Post Type Filtering**: Filter posts by one or multiple post types
- **Taxonomy Filtering**: Filter by categories, tags, and custom taxonomies
- **Author Filtering**: Filter posts by author with cached author lists
- **Sort Controls**: Sort by date, title, and other post fields
- **Advanced Query Loop Support**: Works with both core Query Loop blocks and Advanced Query Loop by Ryan Welcher
- **Client-Side Filtering**: Fast, AJAX-free filtering using WordPress Interactivity API
- **Context-Aware**: Automatic detection of inherited vs custom queries
- **URL-Based State**: Filter state persists in URLs for sharing and bookmarking

## Requirements

- WordPress 6.8 or higher
- PHP 8.2 or higher
- Modern browser with JavaScript enabled

## Installation

### From WordPress Admin

1. Download the plugin ZIP file from the [releases page](https://github.com/pikariweb/pikari-gutenberg-query-filter/releases)
2. Go to Plugins → Add New → Upload Plugin
3. Select the ZIP file and click "Install Now"
4. Activate the plugin

### Manual Installation

1. Download and extract the plugin files
2. Upload the `pikari-gutenberg-query-filter` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin

### Via Composer

```bash
composer require pikari/gutenberg-query-filter
```

## Usage

### Basic Setup

1. Create a Query Loop block in the WordPress block editor
2. Add your desired filter blocks inside or near the Query Loop:
   - **Query Filter Block**: For post types, taxonomies, and authors
   - **Sort Block**: For sorting options
   - **WordPress Search Block**: For search functionality (automatically detected)

### Filter Block Configuration

The Query Filter block provides multiple filter types:

- **Post Type Filter**: Choose which post types to include in the filter
- **Taxonomy Filter**: Select taxonomies (categories, tags, custom taxonomies) to filter by
- **Author Filter**: Enable author filtering with cached author lists

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
- Custom fields (when configured)

## Examples

### Basic Blog with Filters

```html
<!-- wp:query -->
<div class="wp-block-query">
	<!-- wp:pikari/query-filter {"filterType":"post_type,category,author"} -->
	<!-- /wp:pikari/query-filter -->

	<!-- wp:pikari/sort -->
	<!-- /wp:pikari/sort -->

	<!-- wp:search -->
	<form class="wp-block-search">
		<input type="search" placeholder="Search posts..." />
	</form>
	<!-- /wp:search -->

	<!-- wp:post-template -->
	<!-- Your post template blocks here -->
	<!-- /wp:post-template -->
</div>
<!-- /wp:query -->
```

### Portfolio with Custom Post Types

```html
<!-- wp:query {"query":{"postType":"portfolio"}} -->
<div class="wp-block-query">
	<!-- wp:pikari/query-filter {"filterType":"portfolio_category,portfolio_tag"} -->
	<!-- /wp:pikari/query-filter -->

	<!-- wp:pikari/sort {"options":[{"label":"Latest","value":"date-desc"},{"label":"Title","value":"title-asc"}]} -->
	<!-- /wp:pikari/sort -->

	<!-- wp:post-template -->
	<!-- Portfolio item template -->
	<!-- /wp:post-template -->
</div>
<!-- /wp:query -->
```

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
git clone https://github.com/pikariweb/pikari-gutenberg-query-filter.git
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
```

### Project Structure

```
pikari-gutenberg-query-filter/
├── includes/                 # PHP classes
│   ├── Core/                # Core functionality
│   ├── Helpers/             # Helper classes
│   └── Integrations/        # WordPress integrations
├── src/                     # Source files
│   ├── blocks/              # Block definitions
│   │   ├── query-filter/    # Main filter block
│   │   └── sort/            # Sort control block
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
- **Custom Queries**: Supports both inherited and custom query configurations

### Themes

- **Block Themes**: Full support for block-based themes
- **Classic Themes**: Works with classic themes that support blocks
- **Custom CSS**: Provides CSS classes for custom styling

### Browsers

- **Modern Browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **JavaScript**: Requires JavaScript enabled for interactive features
- **Progressive Enhancement**: Graceful degradation when JavaScript is disabled

## Troubleshooting

### Search Block Not Working

- Ensure the search block is placed inside a Query Loop block
- Check that the Query Loop has a valid query configuration
- Verify JavaScript is enabled and no console errors

### Filters Not Updating

- Check that filter blocks are configured with appropriate post types/taxonomies
- Ensure the Query Loop block has compatible query settings
- Review browser console for any JavaScript errors

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
- GitHub: <https://github.com/pikariweb>

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## Support

- **Documentation**: See [docs/](docs/) folder for detailed guides
- **Issues**: Report bugs on [GitHub Issues](https://github.com/pikariweb/pikari-gutenberg-query-filter/issues)
- **Discussions**: Join the conversation in [GitHub Discussions](https://github.com/pikariweb/pikari-gutenberg-query-filter/discussions)
