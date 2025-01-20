<?php
// Ensuring ABSPATH for security
if (defined('WP_CLI') && WP_CLI) {
    error_log('generate-data-cache.php included successfully');
    WP_CLI::add_command('shop_manager:generate_cache', function () {
        // Define the cache file path
        $cache_file = __DIR__ . '/product_data_cache.json';

        $paged = 1; // Start from the first page
        $raw_data = [];
        $has_more_pages = true;

        WP_CLI::log('Starting cache generation...');

        while ($has_more_pages) {
            // Query for orders
            $args = [
                'post_type' => 'shop_order',
                'post_status' => ['wc-completed', 'wc-processing'],
                'posts_per_page' => 200, // Adjust as needed
                'paged' => $paged,
            ];

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

                        // Initialize/reset variables for each loop iteration
                        $product_id = $item->get_product_id();
                        $product = wc_get_product($product_id);

                        // Ensure the product exists
                        if (!$product) {
                            continue;
                        }

                        // Check if the product is a main bundle product and skip it
                        if ($item->meta_exists('_woosb_ids')) {
                            continue; // Skip main bundle product
                        }

                        $parent_id = $product->get_parent_id();
                        $quantity = $item->get_quantity();

                        // Check if the product is part of a bundle
                        $is_part_of_bundle = false;
                        $bundle_parent_id = $item->get_meta('_woosb_parent_id');
                        if (!empty($bundle_parent_id)) {
                            $is_part_of_bundle = true;
                        }

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
                            '_line_subtotal_base_currency' => floatval($item->get_meta('_line_subtotal_base_currency')),
                            '_line_total_base_currency' => floatval($item->get_meta('_line_total_base_currency')),
                            '_line_subtotal' => floatval($item->get_meta('_line_subtotal')),
                            '_line_total' => floatval($item->get_meta('_line_total')),
                            '_woosb_parent_id' => floatval($item->get_meta('_woosb_parent_id')),
                            '_woosb_ids' => floatval($item->get_meta('_woosb_ids')),
                        ];

                        $raw_data[] = [
                            'product_id' => $product_id,
                            'parent_id' => $parent_id,
                            'is_part_of_bundle' => $is_part_of_bundle,
                            'bundle_parent_id' => $bundle_parent_id,
                            'name' => $product->get_name(),
                            'sku' => $product->get_sku(),
                            'quantity' => $quantity,
                            'subtotal' => floatval($item->get_meta('_line_subtotal_base_currency')),
                            'total' => floatval($item->get_meta('_line_total_base_currency')),
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

        // Save data to the cache file
        $cache_data = [
            'data' => $raw_data,
        ];
        file_put_contents($cache_file, json_encode($cache_data));

        WP_CLI::success("Cache file generated successfully at $cache_file.");
    });
}
