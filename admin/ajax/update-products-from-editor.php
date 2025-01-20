<?php
// Ensuring ABSPATH for security
if (!defined('ABSPATH')) {
    exit;
}

function shop_manager_update_product_callback() {
    global $wpdb;

    check_ajax_referer('shop_manager_nonce', 'security');

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $sku = shop_manager_validate_ajax_input('sku');
    $cogs = shop_manager_validate_ajax_input('cogs', 'float');
    $packing_cost = shop_manager_validate_ajax_input('packing_cost', 'float');
    $stock = shop_manager_validate_ajax_input('stock', 'int');
    $work_time_minutes = shop_manager_validate_ajax_input('work_time_minutes', 'int');
    $development_cost = shop_manager_validate_ajax_input('development_cost', 'float');
    $development_months = shop_manager_validate_ajax_input('development_months', 'int');

    $product = wc_get_product($product_id);

    if (!$product) {
        shop_manager_send_ajax_response(false, 'Product not found.');
    }

    $translations = function_exists('pll_get_post_translations') ? pll_get_post_translations($product_id) : [$product_id];
    $translated_ids = array_values($translations);

    if (!empty($sku)) {
        shop_manager_update_product_sku($translated_ids, $sku);
    }

    shop_manager_update_product_meta($translated_ids, '_cogs_price', $cogs);
    shop_manager_update_product_meta($translated_ids, '_packing_cost', $packing_cost);
    shop_manager_update_product_meta($translated_ids, '_work_time_minutes', $work_time_minutes);
    shop_manager_update_product_meta($translated_ids, '_development_cost', $development_cost);
    shop_manager_update_product_meta($translated_ids, '_development_months', $development_months);

    shop_manager_send_ajax_response(true, 'Product and its translations updated successfully.');
}
add_action('wp_ajax_shop_manager_update_product', 'shop_manager_update_product_callback');
