<?php
/*
Plugin Name: Shop Manager
Description: A WordPress plugin boilerplate with Nuxt for the admin interface.
Version: 1.0
Author: Joni Uunila
 */
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
            } else {
            }
        } else {
            //error_log('WP_ENV is not development');
        }

        return $served;
    },
    10,
    4,
);

add_action('plugins_loaded', function () {
    if (defined('WP_ENV') && WP_ENV === 'development') {
    } else {
        if (!current_user_can('administrator')) {
            return; // Prevent access if the user is not an admin
        }
    }

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
    include_once plugin_dir_path(__FILE__) . 'api/order.php';
    include_once plugin_dir_path(__FILE__) . 'api/profit-time.php';

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
        wp_enqueue_style(
            'profit-dashboard-widget',
            plugin_dir_url(__FILE__) . 'assets/profit-dashboard.css',
            [],
            '1.0.0',
        );
        // Enqueue your profit dashboard script.
        wp_enqueue_script(
            'profit-dashboard',
            plugin_dir_url(__FILE__) . 'profit-dashboard.js',
            [], // Dependencies array (empty if none)
            '1.0.0', // Version
            true, // Load in footer
        );
    }
});

function my_profit_dashboard_widget()
{
    // Check if the current user can manage options (administrator capability).
    if (!current_user_can('manage_options')) {
        return;
    }
    // Output the widget content.
    echo '<div id="profit-dashboard-block">Loading profit dashboard...</div>';
}

function register_profit_dashboard_widget()
{
    // Only register the widget if the current user is an admin.
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget(
            'profit_dashboard_widget', // Widget slug.
            'Total Gross Margin (GM2) Targets', // Title.
            'my_profit_dashboard_widget', // Display callback.
        );
    }
}
add_action('wp_dashboard_setup', 'register_profit_dashboard_widget');

function reposition_profit_dashboard_widget()
{
    global $wp_meta_boxes;
    // Check if our widget exists in the normal core area.
    if (isset($wp_meta_boxes['dashboard']['normal']['core']['profit_dashboard_widget'])) {
        // Grab our widget.
        $widget = $wp_meta_boxes['dashboard']['normal']['core']['profit_dashboard_widget'];
        // Remove it from its current position.
        unset($wp_meta_boxes['dashboard']['normal']['core']['profit_dashboard_widget']);
        // Prepend it so it appears first.
        $wp_meta_boxes['dashboard']['normal']['core'] = array_merge(
            ['profit_dashboard_widget' => $widget],
            $wp_meta_boxes['dashboard']['normal']['core'],
        );
    }
}
add_action('wp_dashboard_setup', 'reposition_profit_dashboard_widget');

?>
