<?php
/*
Plugin Name: Shop Manager
Description: A WordPress plugin boilerplate with React for the admin interface.
Version: 1.0
Author: Joni Uunila
*/

if (!defined('ABSPATH')) {
    exit(); // Prevent direct access.
}

add_action('admin_init', function () {
    delete_transient('shop_manager_js_file');
    delete_transient('shop_manager_css_file');
});

// Include caching functionality
require_once plugin_dir_path(__FILE__) . 'admin/cache-manager.php';

// Function to get the hashed asset file dynamically
function shop_manager_get_asset_file($type)
{
    $cache_key = "shop_manager_{$type}_file";
    $cached_file = get_transient($cache_key);

    if ($cached_file) {
        return $cached_file;
    }

    $plugin_dir = plugin_dir_path(__FILE__);
    $plugin_url = plugin_dir_url(__FILE__);
    $pattern = $plugin_dir . "admin/build/static/{$type}/main.*.{$type}";
    $files = glob($pattern);

    // Debugging: Log matched files
    error_log("Pattern: $pattern");
    error_log('Files found: ' . print_r($files, true));

    if (!empty($files)) {
        $file_url = $plugin_url . 'admin/build/static/' . $type . '/' . basename($files[0]);
        set_transient($cache_key, $file_url, 12 * HOUR_IN_SECONDS);
        return $file_url;
    }

    // Fallback for development or missing file
    return $plugin_url . 'admin/build/static/' . $type . '/main.' . $type;
}

// Enqueue React app assets
function shop_manager_enqueue_scripts()
{
    $css_file = shop_manager_get_asset_file('css');
    $js_file = shop_manager_get_asset_file('js');

    if ($css_file) {
        wp_enqueue_style('shop-manager-styles', $css_file, [], null);
    }

    if ($js_file) {
        wp_enqueue_script('shop-manager-script', $js_file, ['wp-element'], null, true);
    }

    wp_localize_script('shop-manager-script', 'shopManagerData', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('shop_manager_nonce'),
        'edit_product_url' => admin_url('post.php?post='),
    ]);
}
add_action('admin_enqueue_scripts', 'shop_manager_enqueue_scripts');

// Add admin menu page
function shop_manager_add_admin_page()
{
    add_menu_page(
        'Shop Manager',
        'Shop Manager',
        'manage_options',
        'shop-manager',
        'shop_manager_render_admin_page',
        'dashicons-analytics',
        6,
    );
}
add_action('admin_menu', 'shop_manager_add_admin_page');

// Render admin page
function shop_manager_render_admin_page()
{
    echo '<div id="shop-manager-app"></div>';
}

include_once plugin_dir_path(__FILE__) . 'admin/ajax/ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'admin/meta-fields.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once plugin_dir_path(__FILE__) . 'admin/generate-data-cache.php';
}
