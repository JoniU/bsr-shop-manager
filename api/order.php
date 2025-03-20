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
    register_rest_route('custom/v1', '/order-live', [
        'methods' => 'GET',
        'callback' => 'shop_manager_last_days_orders',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Callback function for the API endpoint to get orders data with pagination.
 */
function shop_manager_get_orders_data(WP_REST_Request $request)
{
    // Before fetching, update the table with the 50 newest orders.
    // This call updates or inserts the 50 most recent orders without affecting backfill tracking.
    shop_manager_store_orders_db(true);

    global $wpdb;
    $table_name = $wpdb->prefix . 'shop_manager_orders';

    $page = absint($request->get_param('page')) ?: 1; // Default to page 1
    $per_page = absint($request->get_param('per_page')) ?: 80;

    // Get the total number of orders stored in the custom table.
    $total_orders = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"));
    $total_pages = $total_orders > 0 ? ceil($total_orders / $per_page) : 1;

    // Calculate offset for pagination.
    $offset = ($page - 1) * $per_page;

    // Retrieve paginated orders ordered by date_created descending (newest first)
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT order_data FROM {$table_name} ORDER BY date_created DESC LIMIT %d, %d",
            $offset,
            $per_page,
        ),
    );

    // Decode the JSON order data into an array.
    $orders = [];
    if (!empty($results)) {
        foreach ($results as $row) {
            $order = json_decode($row->order_data, true);
            if ($order) {
                $orders[] = $order;
            }
        }
    }

    return rest_ensure_response([
        'orders' => $orders,
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

function shop_manager_last_days_orders(WP_REST_Request $request)
{
    // Get the 'days' parameter or default to 32
    $days = max(1, intval($request->get_param('days') ?: 32));

    // Calculate the start date based on the 'days' parameter
    $start_date = (new DateTime())
        ->modify("-$days days")
        ->format('Y-m-d H:i:s');

    // Query WooCommerce orders from the last N days
    $args = [
        'date_created' => '>' . $start_date, // Ensure the format is correct
        'status' => ['wc-completed', 'wc-processing'], // Include desired order statuses
        'type' => 'shop_order', // Post type
        'limit' => -1, // Ensure no limit on the number of orders fetched
    ];

    $query = new WC_Order_Query($args);
    $orders = $query->get_orders();

    if (empty($orders)) {
        return rest_ensure_response(['message' => 'No orders found for the specified period.']);
    }

    // Format the orders data
    $report = [];
    foreach ($orders as $order) {
        $order_data = shop_manager_format_order_data($order);

        // Use the order date as the key
        $date = $order->get_date_created()->format('Y-m-d');
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

        // Aggregate order-level data
        $report[$date]['total'] += $order_data['total'];
        $report[$date]['discount'] += $order_data['discount'];
        $report[$date]['shipping'] += $order_data['shipping'];
        $report[$date]['tax'] += $order_data['tax'];
        $report[$date]['shipping_tax'] += $order_data['shipping_tax'];

        // Process line items
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            // Fetch product data
            $product = wc_get_product($variation_id ?: $product_id);
            if (!$product) {
                continue;
            }

            $quantity = $item->get_quantity();
            $cogs_price = floatval($product->get_meta('_cogs_price')) ?: 0;
            $packing_cost = floatval($product->get_meta('_packing_cost')) ?: 0;
            $work_time_minutes = floatval($product->get_meta('_work_time_minutes')) ?: 0;

            // Aggregate product-level data
            $report[$date]['quantity'] += $quantity;
            $report[$date]['cogs_price'] += $quantity * $cogs_price;
            $report[$date]['packing_cost'] += $quantity * $packing_cost;
            $report[$date]['work_time_minutes'] += (($quantity * $work_time_minutes) / 60) * 40;
        }
    }

    return rest_ensure_response($report);
}

/**
 * Create the custom table on plugin activation.
 */
function shop_manager_create_custom_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'shop_manager_orders';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL,
        order_data LONGTEXT NOT NULL,
        date_created DATETIME NOT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Initialize the last backfilled order ID to 0.
    if (false === get_option('shop_manager_last_backfilled_order')) {
        update_option('shop_manager_last_backfilled_order', 0);
    }
}
register_activation_hook(__FILE__, 'shop_manager_create_custom_table');

/**
 * Schedule the backfill event if not already scheduled.
 */
function shop_manager_schedule_backfill_event()
{
    if (!wp_next_scheduled('shop_manager_store_orders_db')) {
        wp_schedule_event(time(), 'daily', 'shop_manager_store_orders_db');
    }
}
add_action('wp', 'shop_manager_schedule_backfill_event');

/**
 * The scheduled backfill function.
 *
 * This function fetches orders in batches from the newest to the oldest,
 * and inserts them into the custom table.
 */
function shop_manager_store_orders_db($newestOnly = false)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'shop_manager_orders';

    if ($newestOnly) {
        // Query for orders from today.
        $start_date = date('Y-m-d 00:00:00', strtotime('-3 days'));
        $args = [
            'limit' => -1, // Fetch all orders from today.
            'orderby' => 'date_created',
            'order' => 'DESC',
            'status' => ['wc-completed', 'wc-processing'],
            'type' => 'shop_order',
            'date_created' => '>' . $start_date,
        ];
    } else {
        // Define batch size (number of orders per query).
        $batch_size = 50;

        // Get the last backfilled order ID (assuming orders are sequential).
        $last_backfilled = intval(get_option('shop_manager_last_backfilled_order', 0));

        // Set up query arguments for backfilling.
        $args = [
            'limit' => $batch_size,
            'orderby' => 'date_created',
            'order' => 'DESC', // Start from newest orders.
            'status' => ['wc-completed', 'wc-processing'],
            'type' => 'shop_order',
        ];

        // If we have a last backfilled order, restrict query to those newer than that order.
        if ($last_backfilled) {
            // Note: Adjust the query logic if your order IDs are not sequential.
            $args['include'] = range($last_backfilled + 1, $last_backfilled + $batch_size);
        }
    }

    $query = new WC_Order_Query($args);
    $orders = $query->get_orders();

    if (empty($orders)) {
        error_log('No orders found for processing.');
        return;
    }

    $max_id = 0;

    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $order_data = shop_manager_format_order_data($order);
        $formatted_date = $order->get_date_created()
            ? $order->get_date_created()->date('Y-m-d H:i:s')
            : current_time('mysql');

        // Insert or update the custom table record.
        $wpdb->replace(
            $table_name,
            [
                'id' => $order_id,
                'order_data' => wp_json_encode($order_data),
                'date_created' => $formatted_date,
            ],
            ['%d', '%s', '%s'],
        );

        if ($order_id > $max_id) {
            $max_id = $order_id;
        }
    }

    // Only update the tracking option if we're in backfill mode.
    if (!$newestOnly && $max_id) {
        update_option('shop_manager_last_backfilled_order', $max_id);
    }

    error_log('Processed ' . count($orders) . ' orders. Latest order ID: ' . $max_id);
}

add_action('shop_manager_store_orders_db', 'shop_manager_store_orders_db');

/**
 * Example of a REST endpoint that retrieves combined data from the custom table.
 */
function shop_manager_get_custom_order_data(WP_REST_Request $request)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'shop_manager_orders';
    $order_id = intval($request->get_param('id'));

    // Retrieve the order from our custom table.
    $row = $wpdb->get_row($wpdb->prepare("SELECT order_data FROM {$table_name} WHERE id = %d", $order_id), ARRAY_A);
    if (!$row) {
        return new WP_Error('order_not_found', 'Order not found.', ['status' => 404]);
    }
    $order_data = json_decode($row['order_data'], true);
    return rest_ensure_response($order_data);
}
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/custom-order/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'shop_manager_get_custom_order_data',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * REST endpoint to reset and process orders backfill.
 *
 * POST requests: Reset the backfill (clear table/tracking if requested).
 * GET requests: Process one batch (paginated) and return the number of orders processed.
 */
function shop_manager_reset_orders_backfill(WP_REST_Request $request)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'shop_manager_orders';

    // Branch by request method.
    if (strtoupper($request->get_method()) === 'POST') {
        // Reset Phase: Check if the custom table exists.
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        // Optionally, clear (truncate) the table if requested.
        $clear_table = $request->get_param('clear_table') === 'true';
        if ($table_exists && $clear_table) {
            $result = $wpdb->query("TRUNCATE TABLE {$table_name}");
            if (false === $result) {
                error_log('Failed to truncate table: ' . $wpdb->last_error);
                return new WP_Error('db_error', 'Failed to truncate the orders table.', ['status' => 500]);
            }
        } elseif (!$table_exists) {
            // Create the table if it doesn't exist.
            shop_manager_create_custom_table();
        }

        // Optionally, reset the backfill tracking option if requested.
        $clear_tracking = $request->get_param('clear_tracking') === 'true';
        if ($clear_tracking) {
            update_option('shop_manager_last_backfilled_order', 0);
        }

        return rest_ensure_response([
            'message' => 'Backfill reset completed. Now use GET to process batches.',
        ]);
    }

    // GET method: Process one batch of orders.
    if (strtoupper($request->get_method()) === 'GET') {
        $batchSize = 200; // Backend-defined batch size.
        $currentPage = absint($request->get_param('page')) ?: 1;

        $args = [
            'limit' => $batchSize,
            'page' => $currentPage,
            'status' => ['wc-completed', 'wc-processing'],
            'type' => 'shop_order',
        ];

        $query = new WC_Order_Query($args);
        $orders = $query->get_orders();
        $orders_count = count($orders);

        // Process each order in the batch.
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $order_data = shop_manager_format_order_data($order);
            $formatted_date = $order->get_date_created()
                ? $order->get_date_created()->date('Y-m-d H:i:s')
                : current_time('mysql');

            $wpdb->replace(
                $table_name,
                [
                    'id' => $order_id,
                    'order_data' => wp_json_encode($order_data),
                    'date_created' => $formatted_date,
                ],
                ['%d', '%s', '%s'],
            );
        }

        // Update the tracking option with the last processed order ID.
        if ($orders_count > 0) {
            $lastOrder = end($orders);
            update_option('shop_manager_last_backfilled_order', $lastOrder->get_id());
        }

        return rest_ensure_response([
            'orders_count' => $orders_count,
            'message' => 'Batch processed successfully.',
        ]);
    }

    return rest_ensure_response([
        'message' => 'Invalid request method.',
    ]);
}

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/order/recalculate-orders', [
        'methods' => ['GET', 'POST'],
        'callback' => 'shop_manager_reset_orders_backfill',
        'permission_callback' => '__return_true', // Adjust permissions as needed.
    ]);
});
