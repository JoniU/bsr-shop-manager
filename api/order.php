<?php
if (!defined('ABSPATH')) {
    exit(); // Prevent direct access.
}

/**
 * Register the REST API endpoint for fetching orders.
 */
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/order', [
        'methods' => ['GET', 'OPTIONS'],
        'callback' => 'shop_manager_get_orders_data',
        'permission_callback' => '__return_true',
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/order/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'shop_manager_get_order_by_id',
        'permission_callback' => '__return_true',
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/order-flush', [
        'methods' => 'GET',
        'callback' => 'shop_manager_paginated_orders_to_json',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Callback function for the API endpoint to get orders data with pagination.
 */
function shop_manager_get_orders_data(WP_REST_Request $request)
{
    $page = absint($request->get_param('page')) ?: 1; // Default to page 1
    $per_page = absint($request->get_param('per_page')) ?: 80; // Default to 100 orders per page

    // Regenerate cache if requested
    $regenerate = $request->get_param('regenerate') === 'true';
    if ($regenerate) {
        $success = shop_manager_store_all_orders_to_json();
        if (!$success) {
            return new WP_Error('regeneration_failed', 'Failed to regenerate the cache file.', ['status' => 500]);
        }
    }

    // Define the JSON file path
    $upload_dir = wp_upload_dir();
    $cache_file = trailingslashit($upload_dir['basedir']) . 'bsr-shop-manager/all_orders.json';

    // Read cached file
    if (!file_exists($cache_file)) {
        return new WP_Error('no_cache_file', 'The cache file does not exist.', ['status' => 500]);
    }

    $data = file_get_contents($cache_file);
    if ($data === false) {
        return new WP_Error('file_read_error', 'Failed to read the cache file.', ['status' => 500]);
    }

    $orders = json_decode($data, true)['orders'] ?? [];
    $total_orders = count($orders);
    $total_pages = ceil($total_orders / $per_page);

    // Paginate the orders
    $paginated_orders = array_slice($orders, ($page - 1) * $per_page, $per_page);

    return rest_ensure_response([
        'orders' => $paginated_orders,
        'meta' => [
            'total_orders' => $total_orders,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page,
        ],
    ]);
}

/**
 * Generate a JSON file with all WooCommerce orders.
 */

function shop_manager_get_order_by_id(WP_REST_Request $request)
{
    $order_id = $request->get_param('id');

    // Fetch the order object
    $order = wc_get_order($order_id);

    if (!$order) {
        return new WP_Error('order_not_found', 'Order not found.', ['status' => 404]);
    }

    // Collect raw order data
    $order_data = [
        'id' => $order->get_id(),
        'status' => $order->get_status(),
        'date_created' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
        'total' => $order->get_total(),
        'currency' => $order->get_currency(),
        'billing' => $order->get_address('billing'),
        'shipping' => $order->get_address('shipping'),
        'payment_method' => $order->get_payment_method(),
        'payment_method_title' => $order->get_payment_method_title(),
        'transaction_id' => $order->get_transaction_id(),
        'customer_id' => $order->get_customer_id(),
        'customer_note' => $order->get_customer_note(),
        'meta_data' => [], // Order metadata
        'line_items' => [], // Line items
    ];

    // Get order meta data
    foreach ($order->get_meta_data() as $meta) {
        $order_data['meta_data'][$meta->key] = $meta->value;
    }

    // Get line items data
    foreach ($order->get_items() as $item_id => $item) {
        $line_item_meta = [];
        foreach ($item->get_meta_data() as $meta) {
            $line_item_meta[$meta->key] = $meta->value;
        }

        $order_data['line_items'][] = [
            'product_id' => $item->get_product_id(),
            'variation_id' => $item->get_variation_id(),
            'name' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'subtotal' => $item->get_subtotal(),
            'total' => $item->get_total(),
            'tax_class' => $item->get_tax_class(),
            'taxes' => $item->get_taxes(),
            'meta_data' => $line_item_meta,
        ];
    }

    // Return the raw order data
    return rest_ensure_response($order_data);
}

function shop_manager_paginated_orders_to_json(WP_REST_Request $request)
{
    $page = max(1, intval($request->get_param('page') ?: 1));
    $per_page = max(1, intval($request->get_param('per_page') ?: 100));
    $is_last = $request->get_param('last') === 'true'; // Detect if explicitly marked as last

    error_log("Processing page: $page");
    error_log('Memory usage before query: ' . memory_get_usage(true) . ' bytes');

    // Define the JSON file path
    $upload_dir = wp_upload_dir();
    $cache_file = trailingslashit($upload_dir['basedir']) . 'bsr-shop-manager/all_orders.json';
    $temp_file = $cache_file . '.tmp'; // Temporary file for safer writes

    // Ensure the directory exists
    wp_mkdir_p(dirname($cache_file));

    // Open the file for writing or appending
    $is_new_file = $page === 1 && !file_exists($cache_file);
    $file_handle = fopen($is_new_file ? $temp_file : $cache_file, $page === 1 ? 'w' : 'a');
    if (!$file_handle) {
        error_log("Failed to open cache file for writing: $cache_file");
        return new WP_Error('file_open_failed', 'Failed to open the cache file.', ['status' => 500]);
    }

    // If this is the first page, write the JSON array opening
    if ($page === 1) {
        fwrite($file_handle, '{"orders":[');
    }

    $args = [
        'limit' => $per_page,
        'page' => $page,
        'status' => ['wc-completed', 'wc-processing'], // Order statuses
        'type' => 'shop_order', // Post type
    ];

    $query = new WC_Order_Query($args);
    $orders = $query->get_orders();

    if (empty($orders)) {
        // No orders to process; close JSON properly if on the first page
        if ($page === 1) {
            fwrite($file_handle, ']}');
            fclose($file_handle);
            rename($temp_file, $cache_file); // Rename temp file to final file
        }
        return rest_ensure_response(['message' => 'No more orders to process']);
    }

    $is_first_item = $page === 1;

    foreach ($orders as $order) {
        $order_data = shop_manager_format_order_data($order);

        // Add a comma if this is not the first item
        if (!$is_first_item) {
            fwrite($file_handle, ',');
        } else {
            $is_first_item = false;
        }

        fwrite($file_handle, json_encode($order_data));
        fflush($file_handle); // Ensure data is written to disk
        unset($order); // Free memory
    }

    // Close JSON only if it's the last page or explicitly marked as last
    // Close JSON only if it's the last page or explicitly marked as last
    if (count($orders) < $per_page || $is_last) {
        fwrite($file_handle, ']}'); // Close the array and object
        fclose($file_handle);

        // If using a temp file, rename it to the final file
        if ($is_new_file) {
            rename($temp_file, $cache_file);
        }

        // Validate the last few bytes of the file to ensure proper JSON closure
        $file_handle = fopen($cache_file, 'r+');
        if ($file_handle) {
            fseek($file_handle, -10, SEEK_END); // Move to the last 10 bytes of the file
            $end_chunk = fread($file_handle, 10); // Read the last 10 bytes

            if (!str_contains($end_chunk, ']}')) {
                error_log('Invalid JSON detected: fixing the end of the file.');

                // Fix the end of the file
                fseek($file_handle, -strlen($end_chunk), SEEK_END);
                fwrite($file_handle, rtrim($end_chunk, ',') . ']}');
            } else {
                error_log('JSON file is valid.');
            }

            fclose($file_handle);
        } else {
            error_log('Failed to open the file for validation.');
        }

        // Log memory usage
        error_log('Final memory usage: ' . memory_get_usage(true) . ' bytes');
    } else {
        fclose($file_handle);
    }

    gc_collect_cycles();

    return rest_ensure_response([
        'message' => 'Orders processed successfully.',
        'page' => $page,
        'orders_count' => count($orders),
    ]);
}

function shop_manager_format_order_data($order)
{
    $exchange_rate = floatval(get_post_meta($order->get_id(), '_base_currency_exchange_rate', true)) ?: 1;

    $order_meta_data = [];
    foreach ($order->get_meta_data() as $meta) {
        $order_meta_data[$meta->key] = $meta->value;
    }

    // Calculate totals in base currency
    $order_total = isset($order_meta_data['_order_total_base_currency'])
        ? floatval($order_meta_data['_order_total_base_currency'])
        : floatval($order->get_total()) * $exchange_rate;

    $order_shipping_total = isset($order_meta_data['_order_shipping_base_currency'])
        ? floatval($order_meta_data['_order_shipping_base_currency'])
        : floatval($order->get_shipping_total()) * $exchange_rate;

    $order_tax_total = isset($order_meta_data['_order_tax_base_currency'])
        ? floatval($order_meta_data['_order_tax_base_currency'])
        : floatval($order->get_total_tax()) * $exchange_rate;

    $order_shipping_tax = isset($order_meta_data['_order_shipping_tax_base_currency'])
        ? floatval($order_meta_data['_order_shipping_tax_base_currency'])
        : floatval($order->get_shipping_tax()) * $exchange_rate;

    $order_discount = isset($order_meta_data['_cart_discount_base_currency'])
        ? floatval($order_meta_data['_cart_discount_base_currency'])
        : floatval($order->get_discount_total()) * $exchange_rate;

    // Calculate revenue
    $order_revenue = $order_total - $order_tax_total - $order_discount;

    // Build order data
    $order_data = [
        'order_id' => $order->get_id(),
        'status' => $order->get_status(),
        'date' => $order->get_date_created()->date('Y-m-d'),
        'currency' => $order->get_currency(),
        'total' => $order_total,
        'revenue' => $order_revenue,
        'shipping' => $order_shipping_total,
        'tax' => $order_tax_total,
        'shipping_tax' => $order_shipping_tax,
        'discount' => $order_discount,
        'line_items' => [],
    ];

    foreach ($order->get_items() as $item) {
        $line_item_meta_data = [];
        foreach ($item->get_meta_data() as $meta) {
            $line_item_meta_data[$meta->key] = $meta->value;
        }

        $order_data['line_items'][] = [
            'product_id' => $item->get_product_id(),
            'variation_id' => $item->get_variation_id(),
            'name' => strip_tags($item->get_name()),
            'quantity' => $item->get_quantity(),
            'subtotal' => isset($line_item_meta_data['_line_subtotal_base_currency'])
                ? floatval($line_item_meta_data['_line_subtotal_base_currency'])
                : floatval($item->get_subtotal() * $exchange_rate),
            'total' => isset($line_item_meta_data['_line_total_base_currency'])
                ? floatval($line_item_meta_data['_line_total_base_currency'])
                : floatval($item->get_total() * $exchange_rate),
            'meta_data' => $line_item_meta_data,
        ];
    }

    return $order_data;
}
