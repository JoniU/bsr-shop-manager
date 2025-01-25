<?php
// Ensuring ABSPATH for security
if (!defined('ABSPATH')) {
    exit();
}

/**
 * Generates the product data cache.
 *
 * @param bool $log_to_cli Whether to log messages to WP-CLI.
 * @return bool True if the cache is successfully generated, false otherwise.
 */
function shop_manager_generate_data_cache($log_to_cli = false)
{
    $upload_dir = wp_upload_dir();
    $cache_dir = trailingslashit($upload_dir['basedir']) . 'bsr-shop-manager/';

    if (!is_dir($cache_dir)) {
        if (!mkdir($cache_dir, 0755, true) && !is_dir($cache_dir)) {
            error_log("Failed to create directory: $cache_dir");
            return false;
        }
    }

    $cache_file = $cache_dir . 'product_data_cache.json';

    if ($log_to_cli && defined('WP_CLI')) {
        WP_CLI::log('Starting cache generation...');
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Starting cache generation...');
    }

    $paged = 1;
    $raw_data = [];
    $has_more_pages = true;

    while ($has_more_pages) {
        $args = [
            'post_type' => 'shop_order',
            'post_status' => ['wc-completed', 'wc-processing'],
            'posts_per_page' => 200,
            'paged' => $paged,
        ];

        $orders_query = new WP_Query($args);

        if ($orders_query->have_posts()) {
            while ($orders_query->have_posts()) {
                $orders_query->the_post();
                $order = wc_get_order(get_the_ID());
                $order_date = $order->get_date_created()->date('Y-m-d');

                foreach ($order->get_items() as $item_id => $item) {
                    if (!$item instanceof WC_Order_Item_Product) {
                        continue;
                    }

                    $product_id = $item->get_product_id();
                    $product = wc_get_product($product_id);

                    if (!$product) {
                        continue;
                    }

                    $raw_data[] = [
                        'product_id' => $product_id,
                        'name' => $product->get_name(),
                        'quantity' => $item->get_quantity(),
                        'subtotal' => floatval($item->get_meta('_line_subtotal_base_currency')),
                        'total' => floatval($item->get_meta('_line_total_base_currency')),
                        'order_date' => $order_date,
                    ];
                }
            }
        }

        $has_more_pages = $paged < $orders_query->max_num_pages;
        $paged++;
        wp_reset_postdata();
    }

    $result = file_put_contents($cache_file, json_encode(['data' => $raw_data]));

    if ($result === false) {
        error_log("Failed to write cache file to: $cache_file");
        return false;
    }

    if ($log_to_cli && defined('WP_CLI')) {
        WP_CLI::success("Cache file generated successfully at $cache_file.");
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Cache file generated successfully at $cache_file.");
    }

    return true;
}
