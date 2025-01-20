<?php
// Ensuring ABSPATH for security
if (!defined('ABSPATH')) {
    exit();
}

function shop_manager_fetch_product_data_callback()
{
    check_ajax_referer('shop_manager_nonce', 'security');

    // Define the cache file path
    $cache_file = dirname(__DIR__) . '/product_data_cache.json';

    $cached_data = [];
    $last_order_date = null;

    // Load the cache if it exists
    if (file_exists($cache_file)) {
        $cached_content = json_decode(file_get_contents($cache_file), true);
        if ($cached_content && isset($cached_content['data'])) {
            $cached_data = $cached_content['data'];

            // Find the latest order date in the cache
            $last_order_date = max(array_column($cached_data, 'order_date'));
        }
    }

    $paged = 1; // Start from the first page
    $new_data = [];
    $has_more_pages = true;

    while ($has_more_pages) {
        // Query for orders
        $args = [
            'post_type' => 'shop_order',
            'post_status' => ['wc-completed', 'wc-processing'],
            'posts_per_page' => 200,
            'paged' => $paged,
        ];

        // Add date query to fetch only new orders
        if ($last_order_date) {
            $args['date_query'] = [
                [
                    'after' => $last_order_date,
                    'inclusive' => false,
                ],
            ];
        }

        $orders_query = new WP_Query($args);

        if ($orders_query->have_posts()) {
            while ($orders_query->have_posts()) {
                $orders_query->the_post();
                $order = wc_get_order(get_the_ID());
                $order_date = $order->get_date_created()->date('Y-m-d'); // Get the order date

                foreach ($order->get_items() as $item_id => $item) {
                    if (!$item instanceof WC_Order_Item_Product) {
                        continue;
                    }

                    $product_id = $item->get_product_id();
                    $product = wc_get_product($product_id);

                    // Ensure the product exists
                    if (!$product) {
                        continue;
                    }

                    $parent_id = $product->get_parent_id();
                    $quantity = $item->get_quantity();

                    // Check if the product is part of a bundle
                    $is_part_of_bundle = false;
                    $bundle_parent_id = $item->get_meta('_woosb_parent_id');
                    if (!empty($bundle_parent_id)) {
                        $is_part_of_bundle = true;
                    }

                    // Get the subtotal in base currency
                    $subtotal_base = $item->get_meta('_line_subtotal_base_currency');
                    $subtotal_base = $subtotal_base !== '' ? floatval($subtotal_base) : floatval($item->get_subtotal());

                    // Get fallback meta values
                    $meta_data = [
                        '_cogs_price' =>
                            floatval(get_post_meta($product_id, '_cogs_price', true)) ?:
                            floatval(get_post_meta($parent_id, '_cogs_price', true)),
                        '_packing_cost' =>
                            floatval(get_post_meta($product_id, '_packing_cost', true)) ?:
                            floatval(get_post_meta($parent_id, '_packing_cost', true)),
                        '_work_time_minutes' =>
                            intval(get_post_meta($product_id, '_work_time_minutes', true)) ?:
                            intval(get_post_meta($parent_id, '_work_time_minutes', true)),
                        '_development_cost' =>
                            floatval(get_post_meta($product_id, '_development_cost', true)) ?:
                            floatval(get_post_meta($parent_id, '_development_cost', true)),
                        '_development_months' =>
                            intval(get_post_meta($product_id, '_development_months', true)) ?:
                            intval(get_post_meta($parent_id, '_development_months', true)),
                    ];

                    $new_data[] = [
                        'product_id' => $product_id,
                        'parent_id' => $parent_id,
                        'is_part_of_bundle' => $is_part_of_bundle,
                        'bundle_parent_id' => $bundle_parent_id,
                        'name' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'quantity' => $quantity,
                        'subtotal' => $subtotal_base,
                        'total' => floatval($item->get_total()),
                        'order_date' => $order_date,
                        'meta' => $meta_data,
                    ];
                }
            }
        }

        // Check if there are more pages
        $has_more_pages = $paged < $orders_query->max_num_pages;
        $paged++;
        wp_reset_postdata();
    }

    // Merge new data with cached data
    $merged_data = array_merge($cached_data, $new_data);

    // Save updated data to the cache file
    $cache_data = [
        'data' => $merged_data,
    ];
    file_put_contents($cache_file, json_encode($cache_data));

    if (empty($merged_data)) {
        error_log('No orders found.');
    } else {
        error_log('Orders fetched');
    }

    // Return the merged data
    wp_send_json_success($merged_data);
}
add_action('wp_ajax_shop_manager_fetch_product_data', 'shop_manager_fetch_product_data_callback');
