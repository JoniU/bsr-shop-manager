<?php
if (!defined('ABSPATH')) {
    exit(); // Prevent direct access.
}

/**
 * Fetch WooCommerce products and store them in a JSON file.
 */
function shop_manager_store_all_products_to_json()
{
    // Define the JSON file path
    $upload_dir = wp_upload_dir();
    $cache_file = trailingslashit($upload_dir['basedir']) . 'bsr-shop-manager/all_products.json';

    // Ensure the directory exists
    wp_mkdir_p(dirname($cache_file));

    // Query for all products
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1, // Retrieve all products
    ];

    $query = new WP_Query($args);
    $products_data = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());

            if ($product) {
                // Collect all meta fields for the product
                $product_meta_data = [];
                $meta = get_post_meta(get_the_ID());
                foreach ($meta as $key => $value) {
                    $product_meta_data[$key] = maybe_unserialize($value[0]);
                }

                // Add product data
                $products_data[] = [
                    'product_id' => $product->get_id(),
                    'name' => strip_tags($product->get_name()),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price(),
                    'regular_price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price(),
                    'stock_status' => $product->get_stock_status(),
                    'total_sales' => $product->get_total_sales(),
                    'meta_data' => $product_meta_data, // Include all meta fields
                ];
            }
        }
        wp_reset_postdata();
    }

    // Write the data to a JSON file
    $result = file_put_contents($cache_file, json_encode(['products' => $products_data], JSON_PRETTY_PRINT));

    if ($result === false) {
        error_log("Failed to write products JSON to: $cache_file");
        return false;
    }

    error_log("Product list stored successfully at: $cache_file");
    return true;
}

/**
 * Add a manual button in the WordPress admin to generate products JSON.
 */
function shop_manager_add_generate_products_json_button()
{
    shop_manager_add_generate_json_button(
        'generate-all-products-json',
        'Generate All Products JSON',
        'All Products JSON',
        'generate_all_products_json',
        'shop_manager_store_all_products_to_json',
    );
}
add_action('admin_menu', 'shop_manager_add_generate_products_json_button');
