<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function: Validate and sanitize AJAX input.
 */
function shop_manager_validate_ajax_input($field, $type = 'string', $default = '') {
    $value = isset($_POST[$field]) ? $_POST[$field] : $default;
    switch ($type) {
        case 'float':
            return floatval(str_replace(',', '.', sanitize_text_field($value)));
        case 'int':
            return intval($value);
        case 'string':
        default:
            return sanitize_text_field($value);
    }
}

/**
 * Helper function: Update product meta for multiple product IDs.
 */
function shop_manager_update_product_meta($product_ids, $meta_key, $value) {
    foreach ($product_ids as $id) {
        if ($value !== '') {
            update_post_meta($id, $meta_key, $value);
        }
    }
}

/**
 * Helper function: Update product SKU for multiple product IDs.
 */
function shop_manager_update_product_sku($product_ids, $sku) {
    global $wpdb;

    if (empty($sku)) {
        return;
    }

    // Check for duplicate SKUs
    $existing_product_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
        $sku
    ));
    $conflicting_ids = array_diff($existing_product_ids, $product_ids);

    if (!empty($conflicting_ids)) {
        wp_send_json_error(['message' => 'SKU already exists on another product.']);
        return;
    }

    // Update SKU for all provided product IDs
    foreach ($product_ids as $id) {
        $product = wc_get_product($id);
        if ($product) {
            $product->set_sku($sku);
            $product->save();
        }
    }
}


/**
 * Helper function: Determine if a product is in the default or single language.
 */
function shop_manager_is_original_or_single_language($product_id) {
    if (!function_exists('pll_get_post_language') || !function_exists('pll_get_post_translations')) {
        return true; // No Polylang; consider all products valid.
    }

    $default_language = pll_default_language('slug');
    $translations = pll_get_post_translations($product_id);
    $current_language = pll_get_post_language($product_id, 'slug');

    return (count($translations) === 1) || 
           ($current_language === $default_language && $translations[$current_language] == $product_id);
}

/**
 * Helper function: Send a consistent AJAX response.
 */
function shop_manager_send_ajax_response($success, $message = '', $data = []) {
    if ($success) {
        wp_send_json_success(['message' => $message, 'data' => $data]);
    } else {
        wp_send_json_error(['message' => $message]);
    }
}