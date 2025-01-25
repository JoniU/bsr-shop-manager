<?php

if (!defined('ABSPATH')) {
    exit(); // Prevent direct access.
}

/**
 * Generate cache for shop manager.
 */
function shop_manager_generate_cache()
{
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('shop_manager_generate_cache called');
    }

    try {
        // Call the actual cache generation function
        require_once plugin_dir_path(__FILE__) . 'generate-data-cache.php';
        shop_manager_generate_data_cache(false); // Pass `false` for logging to WP-CLI
    } catch (Exception $e) {
        error_log('Cache generation failed: ' . $e->getMessage());
        throw new Exception('Cache generation failed: ' . $e->getMessage());
    }
}

/**
 * Add submenu for manual cache regeneration.
 */
function shop_manager_add_cache_regeneration_menu()
{
    add_submenu_page(
        'tools.php',
        'Cache Regeneration',
        'Cache Regeneration',
        'manage_options',
        'shop-manager-cache',
        'shop_manager_render_cache_page',
    );
}
add_action('admin_menu', 'shop_manager_add_cache_regeneration_menu');

/**
 * Render cache regeneration page.
 */
function shop_manager_render_cache_page()
{
    if (isset($_POST['regenerate_cache']) && check_admin_referer('shop_manager_regenerate_cache')) {
        try {
            shop_manager_generate_cache();
            echo '<div class="updated"><p>Cache regenerated successfully.</p></div>';
        } catch (Exception $e) {
            error_log('Cache generation failed: ' . $e->getMessage());
            echo '<div class="error"><p>Cache regeneration failed: ' . $e->getMessage() . '</p></div>';
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Cache Regeneration</h1>';
    echo '<form method="post">';
    wp_nonce_field('shop_manager_regenerate_cache');
    echo '<p><input type="submit" name="regenerate_cache" class="button-primary" value="Regenerate Cache Now"></p>';
    echo '</form>';
    echo '</div>';
}

/**
 * Schedule daily cache regeneration.
 */
function shop_manager_schedule_cache_regeneration()
{
    if (!wp_next_scheduled('shop_manager_daily_cache_regeneration')) {
        wp_schedule_event(time(), 'daily', 'shop_manager_daily_cache_regeneration');
    }
}
add_action('wp', 'shop_manager_schedule_cache_regeneration');

/**
 * Clear scheduled events on plugin deactivation.
 */
function shop_manager_clear_scheduled_events()
{
    $timestamp = wp_next_scheduled('shop_manager_daily_cache_regeneration');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'shop_manager_daily_cache_regeneration');
    }
}
register_deactivation_hook(__FILE__, 'shop_manager_clear_scheduled_events');

/**
 * Handle the daily cache regeneration event.
 */
add_action('shop_manager_daily_cache_regeneration', function () {
    shop_manager_generate_cache();
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Daily cache regeneration completed successfully.');
    }
});

// Register WP-CLI command
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('shop_manager:generate_cache', function () {
        shop_manager_generate_data_cache(true);
    });
}
