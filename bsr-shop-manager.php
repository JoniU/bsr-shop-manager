<?php
/*
Plugin Name: Shop Manager
Description: A WordPress plugin boilerplate with Nuxt for the admin interface.
Version: 1.0
Author: Joni Uunila
 */

add_action('admin_menu', 'bsr_shop_manager_add_admin_page');
function bsr_shop_manager_add_admin_page()
{
    // Add the custom admin page
    add_menu_page(
        __('BSR Shop Manager', 'bsr-shop-manager'), // Page title
        __('Shop Manager', 'bsr-shop-manager'), // Menu title
        'manage_options', // Capability
        'bsr-shop-manager', // Menu slug
        'bsr_shop_manager_render_page', // Callback function
        'dashicons-store', // Icon
        25, // Position
    );
}

function bsr_shop_manager_render_page()
{
    // Path to the index.html file
    $index_file = plugin_dir_path(__FILE__) . 'dist/public/index.html';

    // Check if the file exists
    if (file_exists($index_file)) {
        // Get the content of the file
        $content = file_get_contents($index_file);
        // Add custom CSS using a script
        $custom_css = "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var style = document.createElement('style');
                    style.innerHTML = `
                        /* Add your custom styles here */
                    `;
                    document.head.appendChild(style);
                });
            </script>
        ";

        // Append the script with styles to the content
        echo $content . $custom_css;
    } else {
        // Display an error message if the file is missing
        echo '<div class="error"><p>' .
            __('The index.html file could not be found.', 'bsr-shop-manager') .
            '</p></div>';
    }
}

include_once plugin_dir_path(__FILE__) . 'api/product.php';
include_once plugin_dir_path(__FILE__) . 'api/settings.php';
include_once plugin_dir_path(__FILE__) . 'api/nounce.php';

require_once plugin_dir_path(__FILE__) . 'admin/meta-fields.php';

add_action('admin_enqueue_scripts', 'bsr_shop_manager_add_admin_styles', 100);
function bsr_shop_manager_add_admin_styles($hook)
{
    // Only add styles to your custom admin page
    if ($hook === 'toplevel_page_bsr-shop-manager') {
        wp_deregister_style('ie');
        wp_deregister_style('wp-admin');
        // Enqueue a custom CSS file
        wp_enqueue_style(
            'bsr-admin-styles', // Handle
            plugins_url('assets/admin.css', __FILE__), // Path to CSS file
            [], // Dependencies
            '1.0.0', // Version
        );
    }
}

add_filter(
    'rest_pre_serve_request',
    function ($served, $result, $request, $server) {
        // Apply CORS settings only if WP_ENV is set to 'development'
        if (defined('WP_ENV') && WP_ENV === 'development') {
            // Allow localhost and the current WordPress site URL
            $allowed_origins = [
                'http://localhost:3000', // Local Nuxt dev server
                get_site_url(), // Dynamically fetch the local WordPress URL
            ];

            // Get the Origin from the request headers
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

            // Check if the origin is in the allowed list
            if (in_array($origin, $allowed_origins, true)) {
                // Set CORS headers
                header("Access-Control-Allow-Origin: $origin");
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type');
                header('Access-Control-Allow-Credentials: true');
            }
        }

        return $served;
    },
    10,
    4,
);

/*
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
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Pattern: $pattern");
        error_log('Files found: ' . print_r($files, true));
    }

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
require_once plugin_dir_path(__FILE__) . 'admin/fetch-orders.php';
require_once plugin_dir_path(__FILE__) . 'admin/fetch-products.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once plugin_dir_path(__FILE__) . 'admin/generate-data-cache.php';
}
*/
