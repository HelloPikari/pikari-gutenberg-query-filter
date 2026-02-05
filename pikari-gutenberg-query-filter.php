<?php
/**
 * Plugin Name: Pikari Gutenberg Query Filter
 * Plugin URI:  https://github.com/pikariweb/pikari-gutenberg-query-filter
 * Description: Advanced filtering for Query Loop blocks with search, post types, taxonomies, authors, and sorting. Integrates seamlessly with WordPress core blocks using the Interactivity API.
 * Version:     0.1.13
 * Author:      Pikari Inc.
 * Author URI:  https://pikari.io
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pikari-gutenberg-query-filter
 * Domain Path: /languages
 * Requires at least: 6.8
 * Tested up to: 6.8
 * Requires PHP: 8.2
 * Network: false
 *
 * @package pikari-gutenberg-query-filter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin version.
 */
define( 'PIKARI_GUTENBERG_QUERY_FILTER_VERSION', '0.1.0' );

/**
 * Plugin directory path.
 */
define( 'PIKARI_GUTENBERG_QUERY_FILTER_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'PIKARI_GUTENBERG_QUERY_FILTER_URL', plugin_dir_url( __FILE__ ) );

// Autoloader for plugin classes.
spl_autoload_register(
    function ( $class ) {
        $prefix   = 'Pikari\\GutenbergQueryFilter\\';
        $base_dir = PIKARI_GUTENBERG_QUERY_FILTER_DIR . 'includes/';

        $len = strlen($prefix);
        if ( strncmp($prefix, $class, $len) !== 0 ) {
            return;
        }

        $relative_class = substr($class, $len);
        $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if ( file_exists($file) ) {
            require $file;
        }
    }
);

/**
 * Initialize the plugin on WordPress init.
 */
function pikari_gutenberg_query_filter_init() {
    // Load plugin text domain for translations.
    load_plugin_textdomain( 'pikari-gutenberg-query-filter', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Register Gutenberg blocks.
    pikari_gutenberg_query_filter_register_blocks();

    // Setup cache invalidation hooks.
    pikari_gutenberg_query_filter_setup_cache_hooks();

    // Initialize Query Loop handler.
    pikari_gutenberg_query_filter_init_query_handler();

    // Note: Block filters are initialized early outside init hook to catch core block registration.
    // See pikari_gutenberg_query_filter_safe_init_block_filters() call above.

    // Hook frontend script enqueuing.
    add_action( 'wp_enqueue_scripts', 'pikari_gutenberg_query_filter_enqueue_scripts' );
}
add_action( 'init', 'pikari_gutenberg_query_filter_init' );

// Initialize core block filters immediately to catch all block registrations.
// This must happen before init to catch core WordPress blocks.
pikari_gutenberg_query_filter_safe_init_block_filters();

/**
 * Register Gutenberg blocks.
 */
function pikari_gutenberg_query_filter_register_blocks() {
    $build_dir = __DIR__ . '/build/blocks';

    if ( ! file_exists( $build_dir ) ) {
        return;
    }

    $block_json_files = glob( $build_dir . '/*/block.json' );

    foreach ( $block_json_files as $block_json_file ) {
        register_block_type( dirname( $block_json_file ) );
    }
}

/**
 * Enqueue plugin scripts and styles.
 */
function pikari_gutenberg_query_filter_enqueue_scripts() {
    // Enqueue your scripts and styles here.
    // Example:
    // wp_enqueue_style( 'pikari-query-filter', PIKARI_QUERY_FILTER_URL . 'assets/css/style.css', array(), PIKARI_QUERY_FILTER_VERSION );
    // wp_enqueue_script( 'pikari-query-filter', PIKARI_QUERY_FILTER_URL . 'assets/js/script.js', array( 'jquery' ), PIKARI_QUERY_FILTER_VERSION, true );
}

/**
 * Setup cache invalidation hooks for author filter data.
 */
function pikari_gutenberg_query_filter_setup_cache_hooks() {
    // Clear author cache when posts are published/updated.
    add_action( 'save_post', array( 'Pikari\GutenbergQueryFilter\Helpers\AuthorHelper', 'invalidate_author_cache' ) );
    add_action( 'delete_post', array( 'Pikari\GutenbergQueryFilter\Helpers\AuthorHelper', 'invalidate_author_cache' ) );

    // Clear author cache when users are created/deleted.
    add_action( 'user_register', array( 'Pikari\GutenbergQueryFilter\Helpers\AuthorHelper', 'invalidate_author_cache' ) );
    add_action( 'delete_user', array( 'Pikari\GutenbergQueryFilter\Helpers\AuthorHelper', 'invalidate_author_cache' ) );

    // Clear author cache when user capabilities change.
    add_action( 'add_user_role', array( 'Pikari\GutenbergQueryFilter\Helpers\AuthorHelper', 'invalidate_author_cache' ) );
    add_action( 'remove_user_role', array( 'Pikari\GutenbergQueryFilter\Helpers\AuthorHelper', 'invalidate_author_cache' ) );
    add_action( 'set_user_role', array( 'Pikari\GutenbergQueryFilter\Helpers\AuthorHelper', 'invalidate_author_cache' ) );
}

/**
 * Initialize the Query Loop handler.
 */
function pikari_gutenberg_query_filter_init_query_handler() {
    // Instantiate the handler which will register its own hooks.
    new \Pikari\GutenbergQueryFilter\Core\QueryLoopHandler();
}

/**
 * Safely initialize core block filters with fallback.
 */
function pikari_gutenberg_query_filter_safe_init_block_filters() {
    // Try to initialize early to catch core block registrations.
    if ( pikari_gutenberg_query_filter_init_block_filters() === false ) {
        // If early initialization fails, add fallback on init hook with high priority.
        add_action( 'init', 'pikari_gutenberg_query_filter_init_block_filters', 0 );
    }
}

/**
 * Initialize core block filters.
 *
 * @return bool True if successful, false if class not available.
 */
function pikari_gutenberg_query_filter_init_block_filters() {
    // Check if the class is available before trying to instantiate.
    if ( ! class_exists( '\Pikari\GutenbergQueryFilter\Integrations\BlockFilters' ) ) {
        return false;
    }

    // Instantiate the block filters which will register their own hooks.
    new \Pikari\GutenbergQueryFilter\Integrations\BlockFilters();
    return true;
}

/**
 * Activation hook.
 */
function pikari_gutenberg_query_filter_activate() {
    // Code to run on plugin activation.
    // Check minimum requirements.
    if ( version_compare(get_bloginfo('version'), '6.8', '<') ) {
        deactivate_plugins(plugin_basename(__DIR__ . '/pikari-gutenberg-query-filter.php'));
        wp_die(
            esc_html__('This plugin requires WordPress 6.8 or higher.', 'pikari-gutenberg-query-filter')
        );
    }

    if ( version_compare(PHP_VERSION, '8.2', '<') ) {
        deactivate_plugins(plugin_basename(__DIR__ . '/pikari-gutenberg-query-filter.php'));
        wp_die(
            esc_html__('This plugin requires PHP 8.2 or higher.', 'pikari-gutenberg-query-filter')
        );
    }

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pikari_gutenberg_query_filter_activate' );

/**
 * Deactivation hook.
 */
function pikari_gutenberg_query_filter_deactivate() {
    // Code to run on plugin deactivation.
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pikari_gutenberg_query_filter_deactivate' );
