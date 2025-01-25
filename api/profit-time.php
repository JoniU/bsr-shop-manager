<?php
if (!defined('ABSPATH')) {
    exit(); // Prevent direct access.
}
// Register the REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/profit-time', [
        'methods' => 'GET',
        'callback' => 'get_order_product_report_api',
        'permission_callback' => '__return_true', // Adjust permissions as needed
    ]);
});

// Callback for the API endpoint
function get_order_product_report_api(WP_REST_Request $request)
{
    $regenerate = $request->get_param('regenerate') === 'true'; // Check if regeneration is requested
    $report = generate_and_cache_report($regenerate);

    if (is_wp_error($report)) {
        return $report; // Return the error if something went wrong
    }

    return rest_ensure_response($report);
}

function generate_and_cache_report($force_regenerate = false)
{
    $upload_dir = wp_upload_dir();
    $cache_file = trailingslashit($upload_dir['basedir']) . 'bsr-shop-manager/order_product_report.json';
    $cache_lifetime = 24 * 60 * 60; // Cache duration in seconds (24 hours)

    // Check if the cache file exists and is valid
    if (!$force_regenerate && file_exists($cache_file) && time() - filemtime($cache_file) < $cache_lifetime) {
        $data = file_get_contents($cache_file);
        if ($data === false) {
            return new WP_Error('file_read_error', 'Failed to read cached report file.', ['status' => 500]);
        }
        return json_decode($data, true);
    }

    // Generate the report
    $report = generate_order_product_report();
    if (is_wp_error($report)) {
        return $report; // Return the error if generation failed
    }

    // Save the report to the cache file
    $result = file_put_contents($cache_file, json_encode($report, JSON_PRETTY_PRINT));
    if ($result === false) {
        return new WP_Error('file_write_error', 'Failed to write report to cache file.', ['status' => 500]);
    }

    return $report;
}

function generate_order_product_report()
{
    $upload_dir = wp_upload_dir();
    $orders_file = trailingslashit($upload_dir['basedir']) . 'bsr-shop-manager/all_orders.json';

    // Read the orders data
    if (!file_exists($orders_file)) {
        return new WP_Error('file_not_found', 'Orders file not found.');
    }

    $orders_data = json_decode(file_get_contents($orders_file), true);
    if (empty($orders_data['orders'])) {
        return new WP_Error('no_orders', 'No orders found in the file.');
    }

    $report = [];

    foreach ($orders_data['orders'] as $order) {
        $date = $order['date'];
        if (!isset($report[$date])) {
            $report[$date] = [
                'total' => 0,
                'discount' => 0,
                'shipping' => 0,
                'tax' => 0,
                'shipping_tax' => 0,
                'quantity' => 0,
                'cogs_price' => 0,
                'packing_cost' => 0,
                'work_time_minutes' => 0,
                'development_cost' => 0,
                'development_months' => 0,
            ];
        }

        // Aggregate order-level values
        $report[$date]['total'] += $order['total'];
        $report[$date]['discount'] += $order['discount'];
        $report[$date]['shipping'] += $order['shipping'];
        $report[$date]['tax'] += $order['tax'];
        $report[$date]['shipping_tax'] += $order['shipping_tax'];

        // Process line items
        foreach ($order['line_items'] as $item) {
            $product_id = $item['product_id'];
            $variation_id = $item['variation_id'];

            // Fetch product data
            $product = wc_get_product($variation_id ?: $product_id);
            if (!$product) {
                continue;
            }

            $quantity = $item['quantity'];
            $cogs_price = floatval($product->get_meta('_cogs_price')) ?: 0;
            $packing_cost = floatval($product->get_meta('_packing_cost')) ?: 0;
            $work_time_minutes = floatval($product->get_meta('_work_time_minutes')) ?: 0;

            // Aggregate product-level values
            $report[$date]['quantity'] += $quantity;
            $report[$date]['cogs_price'] += $quantity * $cogs_price;
            $report[$date]['packing_cost'] += $quantity * $packing_cost;
            $report[$date]['work_time_minutes'] += (($quantity * $work_time_minutes) / 60) * 40;
        }
    }

    return $report;
}
