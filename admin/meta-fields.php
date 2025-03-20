<?php
// Ensuring ABSPATH for security
if (!defined('ABSPATH')) {
    exit();
}

// Define fields metadata keys and configurations
$custom_fields = [
    '_cogs_price' => [
        'label' => __('Cost of Goods Sold (COGS)', 'bsr-cogs-stock-management'),
        'description' => __('Enter the cost of goods sold for this product.', 'bsr-cogs-stock-management'),
    ],
    '_packing_cost' => [
        'label' => __('Packing Cost', 'bsr-cogs-stock-management'),
        'description' => __('Enter the packing cost for this product.', 'bsr-cogs-stock-management'),
    ],
    '_work_time_minutes' => [
        'label' => __('Work Time (Minutes)', 'bsr-cogs-stock-management'),
        'description' => __('Enter the work time in minutes for this product.', 'bsr-cogs-stock-management'),
    ],
    '_development_cost' => [
        'label' => __('Development Cost (€)', 'bsr-cogs-stock-management'),
        'description' => __('Enter the development cost for this product.', 'bsr-cogs-stock-management'),
    ],
    '_development_months' => [
        'label' => __('Development Months', 'bsr-cogs-stock-management'),
        'description' => __('Enter the development duration in months for this product.', 'bsr-cogs-stock-management'),
    ],
];

// Add custom fields to simple products
function bsr_add_custom_fields_to_simple_products()
{
    global $custom_fields;
    foreach ($custom_fields as $key => $field) {
        woocommerce_wp_text_input([
            'id' => $key,
            'label' => $field['label'],
            'desc_tip' => 'true',
            'description' => $field['description'],
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);
    }
}
add_action('woocommerce_product_options_general_product_data', 'bsr_add_custom_fields_to_simple_products');

// Save custom fields for simple products
function bsr_save_simple_product_custom_fields($post_id)
{
    global $custom_fields;
    foreach ($custom_fields as $key => $field) {
        if (isset($_POST[$key])) {
            $value = floatval(str_replace(',', '.', sanitize_text_field($_POST[$key])));
            update_post_meta($post_id, $key, $value);
        }
    }
}
add_action('woocommerce_process_product_meta', 'bsr_save_simple_product_custom_fields');

// Add custom fields to variations
function bsr_add_custom_fields_to_variations($loop, $variation_data, $variation)
{
    global $custom_fields;
    foreach ($custom_fields as $key => $field) {
        woocommerce_wp_text_input([
            'id' => $key . '[' . $variation->ID . ']',
            'label' => $field['label'],
            'desc_tip' => 'true',
            'description' => $field['description'],
            'value' => get_post_meta($variation->ID, $key, true),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);
    }
}
add_action('woocommerce_product_after_variable_attributes', 'bsr_add_custom_fields_to_variations', 10, 3);

// Save custom fields for variations
function bsr_save_variation_custom_fields($variation_id, $i)
{
    global $custom_fields;
    foreach ($custom_fields as $key => $field) {
        if (isset($_POST[$key][$variation_id])) {
            $value = floatval(str_replace(',', '.', sanitize_text_field($_POST[$key][$variation_id])));
            update_post_meta($variation_id, $key, $value);
        }
    }
}
add_action('woocommerce_save_product_variation', 'bsr_save_variation_custom_fields', 10, 2);

// Add custom field to the Inventory tab in WooCommerce product edit page
function add_exclude_from_stock_field()
{
    woocommerce_wp_checkbox([
        'id' => '_exclude_from_stock', // meta key for storing the value
        'label' => __('Exclude this product from stock value', 'woocommerce'),
        'description' => __('Check this box to exclude the product from stock calculations.', 'woocommerce'),
    ]);
}
add_action('woocommerce_product_options_inventory_product_data', 'add_exclude_from_stock_field');

// Save the custom field value when the product is saved
function save_exclude_from_stock_field($post_id)
{
    // Check if the checkbox is checked; if not, set to "no"
    $exclude = isset($_POST['_exclude_from_stock']) ? 'yes' : 'no';
    update_post_meta($post_id, '_exclude_from_stock', $exclude);
}
add_action('woocommerce_process_product_meta', 'save_exclude_from_stock_field');
