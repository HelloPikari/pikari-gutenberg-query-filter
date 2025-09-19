<?php
/**
 * Plugin Name: pikari-gutenberg-query-filter
 * Plugin URI:  https://pikari.io
 * Description: Filter controls for the query loop block, using the interactivity API
 * Version:     0.1.0
 * Author:      Pikari Inc.
 * Author URI:  https://pikari.io
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pikari-gutenberg-query-filter
 * Domain Path: /languages
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

    // Hook frontend script enqueuing.
    add_action( 'wp_enqueue_scripts', 'pikari_gutenberg_query_filter_enqueue_scripts' );
}
add_action( 'init', 'pikari_gutenberg_query_filter_init' );

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
