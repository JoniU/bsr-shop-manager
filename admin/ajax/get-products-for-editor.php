<?php
// Ensuring ABSPATH for security
if (!defined('ABSPATH')) {
    exit;
}

function shop_manager_fetch_products_callback() {
    if (!isset($_POST['security']) || !check_ajax_referer('shop_manager_nonce', 'security', false)) {
        error_log("Nonce check failed or security key is missing.");
        wp_send_json_error(['message' => 'Invalid security token.'], 400);
        return;
    }

    error_log("Nonce verified: " . $_POST['security']);
    error_log("Received AJAX request for fetching products.");

    $products_data = array();

    $args = array(
        'post_type' => array('product', 'product_variation'),
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => 'Woosb',
                'operator' => 'NOT IN',
            ),
        ),
        'orderby' => 'ID',
        'order' => 'ASC',
    );
    
    $products_query = new WP_Query($args);

    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product_id = get_the_ID();

            $product = wc_get_product($product_id);

            if (!shop_manager_is_original_or_single_language($product_id)) {
                continue;
            }

            if (empty($product->get_name())) {
                continue;
            }

            $meta_data = get_post_meta($product_id);

            // Extract required meta fields
            $cogs_price = $meta_data['_cogs_price'][0] ?? '';
            $packing_cost = $meta_data['_packing_cost'][0] ?? '';
            $manage_stock = isset($meta_data['_manage_stock'][0]) && $meta_data['_manage_stock'][0] === 'yes';
            $work_time_minutes = $meta_data['_work_time_minutes'][0] ?? '';
            $development_cost = $meta_data['_development_cost'][0] ?? '';
            $development_months = $meta_data['_development_months'][0] ?? '';

            $stock_quantity = $product->get_stock_quantity();
            $product_type = ucfirst($product->get_type());
            $price_excl_tax = wc_get_price_excluding_tax($product);
            $formatted_price = number_format((float) $price_excl_tax, 2, '.', '') . ' €';

            $products_data[] = array(
                'id' => $product_id,
                'name' => get_the_title(),
                'type' => $product_type,
                'sku' => $product->get_sku(),
                'price' => $formatted_price,
                'cogs' => $cogs_price,
                'packing_cost' => $packing_cost,
                'stock' => $stock_quantity,
                'parent_id' => $product->is_type('variation') ? $product->get_parent_id() : 0,
                'manage_stock' => $manage_stock,
                'work_time_minutes' => $work_time_minutes,
                'development_cost' => $development_cost,
                'development_months' => $development_months,
            );
        }
    }
    
    wp_reset_postdata();
    wp_send_json_success($products_data);
}
add_action('wp_ajax_shop_manager_fetch_products', 'shop_manager_fetch_products_callback');