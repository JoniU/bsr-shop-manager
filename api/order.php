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

/**
 * Callback function for the API endpoint to get orders data.
 */
function shop_manager_get_orders_data()
{
    $upload_dir = wp_upload_dir();
    $cache_file = trailingslashit($upload_dir['basedir']) . 'bsr-shop-manager/all_orders.json';
    $cache_lifetime = 24 * 60 * 60; // 24 hours

    // Check if the cached file exists and is still valid
    if (file_exists($cache_file) && time() - filemtime($cache_file) < $cache_lifetime) {
        // Read the contents of the cached file
        $data = file_get_contents($cache_file);
        if ($data === false) {
            return new WP_Error('file_read_error', 'Failed to read cached file.', ['status' => 500]);
        }
        return rest_ensure_response(json_decode($data, true));
    }

    // If the cache is missing or expired, regenerate it
    $success = shop_manager_store_all_orders_to_json();
    if ($success) {
        $data = file_get_contents($cache_file);
        if ($data === false) {
            return new WP_Error('file_read_error', 'Failed to read newly generated file.', ['status' => 500]);
        }
        return rest_ensure_response(json_decode($data, true));
    }

    return new WP_Error('no_orders_data', 'Could not retrieve or generate order data.', ['status' => 500]);
}

/**
 * Generate a JSON file with all WooCommerce orders.
 */
function shop_manager_store_all_orders_to_json()
{
    // Define the JSON file path
    $upload_dir = wp_upload_dir();
    $cache_file = trailingslashit($upload_dir['basedir']) . 'bsr-shop-manager/all_orders.json';

    // Ensure the directory exists
    wp_mkdir_p(dirname($cache_file));

    $per_page = 100; // Number of orders to fetch per batch
    $current_page = 1;
    $first_item = true; // Tracks if the first item is being written to JSON

    // Open the file for writing
    $file_handle = fopen($cache_file, 'w');
    if (!$file_handle) {
        error_log("Failed to open cache file for writing: $cache_file");
        return false;
    }

    // Write the JSON array opening
    fwrite($file_handle, '{"orders":[');

    do {
        // Get orders in batches
        $args = [
            'limit' => $per_page,
            'page' => $current_page,
            'status' => ['wc-completed', 'wc-processing'], // Order statuses
            'type' => 'shop_order', // Post type
        ];

        $query = new WC_Order_Query($args);
        $orders = $query->get_orders();

        if (empty($orders)) {
            break; // Exit loop if no orders are found
        }

        foreach ($orders as $order) {
            // Retrieve the base currency exchange rate
            $exchange_rate = floatval(get_post_meta($order->get_id(), '_base_currency_exchange_rate', true)) ?: 1;

            // Collect all meta fields for the order
            $order_meta_data = [];
            foreach ($order->get_meta_data() as $meta) {
                $order_meta_data[$meta->key] = $meta->value;
            }

            // Calculate totals in base currency
            $order_total = isset($order_meta_data['_order_total_base_currency'])
                ? floatval($order_meta_data['_order_total_base_currency'])
                : floatval($order->get_total() * $exchange_rate);

            $order_shipping_total = isset($order_meta_data['_order_shipping_base_currency'])
                ? floatval($order_meta_data['_order_shipping_base_currency'])
                : floatval($order->get_shipping_total() * $exchange_rate);

            $order_tax_total = isset($order_meta_data['_order_tax_base_currency'])
                ? floatval($order_meta_data['_order_tax_base_currency'])
                : floatval($order->get_total_tax() * $exchange_rate);

            $order_shipping_tax = isset($order_meta_data['_order_shipping_tax_base_currency'])
                ? floatval($order_meta_data['_order_shipping_tax_base_currency'])
                : floatval($order->get_shipping_tax() * $exchange_rate);

            $order_discount = isset($order_meta_data['_cart_discount_base_currency'])
                ? floatval($order_meta_data['_cart_discount_base_currency'])
                : floatval($order->get_discount_total() * $exchange_rate);

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

            // Process line items
            foreach ($order->get_items() as $item) {
                $line_item_meta_data = [];
                foreach ($item->get_meta_data() as $meta) {
                    $line_item_meta_data[$meta->key] = $meta->value;
                }

                $order_data['line_items'][] = [
                    'product_id' => $item->get_product_id(),
                    'name' => strip_tags($item->get_name()),
                    'quantity' => $item->get_quantity(),
                    'subtotal' => isset($line_item_meta_data['_line_subtotal_base_currency'])
                        ? floatval($line_item_meta_data['_line_subtotal_base_currency'])
                        : floatval($item->get_subtotal() * $exchange_rate),
                    'total' => isset($line_item_meta_data['_line_total_base_currency'])
                        ? floatval($line_item_meta_data['_line_total_base_currency'])
                        : floatval($item->get_total() * $exchange_rate),
                    '_woosb_ids' => isset($line_item_meta_data['_woosb_ids'])
                        ? floatval($line_item_meta_data['_woosb_ids'])
                        : null,
                    '_woosb_parent_id' => isset($line_item_meta_data['_woosb_parent_id'])
                        ? floatval($line_item_meta_data['_woosb_parent_id'])
                        : null,
                    'meta_data' => $line_item_meta_data,
                ];
            }

            // Write the order data to the file
            if (!$first_item) {
                fwrite($file_handle, ',');
            } else {
                $first_item = false;
            }

            fwrite($file_handle, json_encode($order_data));
        }

        $current_page++; // Move to the next page
    } while (count($orders) === $per_page); // Continue until no more orders are fetched

    // Write the JSON array closing
    fwrite($file_handle, ']}');

    // Close the file handle
    fclose($file_handle);

    error_log("All orders stored successfully at: $cache_file");
    return true;
}

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
