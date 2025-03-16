<?php
if (!defined('ABSPATH')) {
    exit(); // Prevent direct access.
}

/**
 * Register the REST API endpoint for profit timeline.
 */
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/profit-time', [
        'methods' => 'GET',
        'callback' => 'get_order_product_report_api',
        'permission_callback' => '__return_true', // Adjust permissions as needed
    ]);
});

/**
 * Callback for the API endpoint.
 */
function get_order_product_report_api(WP_REST_Request $request)
{
    $regenerate = $request->get_param('regenerate') === 'true'; // Force full regeneration if true
    $report = generate_and_cache_report($regenerate);

    if (is_wp_error($report)) {
        return $report; // Return the error if something went wrong
    }

    return rest_ensure_response($report);
}

/**
 * Generate the profit timeline report and cache it in a custom database table.
 *
 * If $force_regenerate is false, the cached report is used—but today's data is always refreshed.
 */
function generate_and_cache_report($force_regenerate = false)
{
    // Always update the orders table for today's orders.
    shop_manager_store_orders_db(true);

    global $wpdb;
    $table_name = $wpdb->prefix . 'shop_manager_profit_timeline';
    $cache_lifetime = 24 * 60 * 60; // Cache lifetime: 24 hours

    // Ensure the custom table exists.
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
    if (!$table_exists) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id INT(11) NOT NULL,
            report_data LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    // If not forced to regenerate, check for valid cache.
    if (!$force_regenerate) {
        $row = $wpdb->get_row("SELECT report_data, updated_at FROM {$table_name} WHERE id = 1");
        if ($row) {
            $updated_at = strtotime($row->updated_at);
            if (time() - $updated_at < $cache_lifetime) {
                $decoded_data = json_decode($row->report_data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Refresh today's data regardless of cache.
                    $today = date('Y-m-d');
                    $todays_report = generate_order_product_report_for_date($today);
                    if (!empty($todays_report[$today])) {
                        $decoded_data[$today] = $todays_report[$today];
                        // Update the cache with today's refreshed data.
                        $data = [
                            'id' => 1,
                            'report_data' => wp_json_encode($decoded_data, JSON_PRETTY_PRINT),
                            'updated_at' => current_time('mysql', 1),
                        ];
                        $wpdb->replace($table_name, $data, ['%d', '%s', '%s']);
                    }
                    return $decoded_data;
                }
            }
        }
    }

    // Otherwise, generate the full report.
    $report = generate_order_product_report();
    if (is_wp_error($report)) {
        error_log('Report generation failed: ' . $report->get_error_message());
        return $report;
    }

    // Save the report to the custom table.
    $data = [
        'id' => 1,
        'report_data' => wp_json_encode($report, JSON_PRETTY_PRINT),
        'updated_at' => current_time('mysql', 1),
    ];
    $wpdb->replace($table_name, $data, ['%d', '%s', '%s']);

    return $report;
}

/**
 * Generate the profit timeline report by aggregating order data from all orders.
 */
function generate_order_product_report()
{
    // Always refresh orders from the custom orders table.
    global $wpdb;
    $table_name = $wpdb->prefix . 'shop_manager_orders';

    // Retrieve all orders from the custom orders table.
    $results = $wpdb->get_results("SELECT order_data FROM {$table_name}");
    if (empty($results)) {
        return new WP_Error('no_orders', 'No orders found in the database.');
    }

    // Decode each order's JSON data.
    $orders = [];
    foreach ($results as $row) {
        $order = json_decode($row->order_data, true);
        if ($order) {
            $orders[] = $order;
        }
    }
    if (empty($orders)) {
        return new WP_Error('no_orders', 'No valid orders found in the database.');
    }

    // Load additional settings.
    $option_key = 'bsr_shop_manager_settings_data';
    $settings = get_option($option_key, [
        'costs' => [],
        'marketingCosts' => [],
        'rent' => [],
    ]);
    $costs = $settings['costs'];
    $marketingCosts = $settings['marketingCosts'];
    $rent = $settings['rent'];

    $report = [];

    // Determine the overall date range.
    $dates = array_map(fn($order) => $order['date'], $orders);
    $startDate = new DateTime(min($dates));
    $endDate = new DateTime(max($dates));

    // Initialize report data for each day.
    for ($date = $startDate; $date <= $endDate; $date->modify('+1 day')) {
        $formattedDate = $date->format('Y-m-d');
        $year = intval($date->format('Y'));
        $month = intval($date->format('m'));

        if (!isset($report[$formattedDate])) {
            $report[$formattedDate] = [
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
                'costs' => 0,
                'marketing_costs' => 0,
                'rent' => 0,
            ];
        }

        // Calculate daily costs.
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $dailyCost = (isset($costs[$year][$month - 1]) ? $costs[$year][$month - 1] : 0) / $daysInMonth;
        $dailyMarketingCost =
            (isset($marketingCosts[$year][$month - 1]) ? $marketingCosts[$year][$month - 1] : 0) / $daysInMonth;
        $dailyRent = (isset($rent[$year][$month - 1]) ? $rent[$year][$month - 1] : 0) / $daysInMonth;

        $report[$formattedDate]['costs'] += $dailyCost;
        $report[$formattedDate]['marketing_costs'] += $dailyMarketingCost;
        $report[$formattedDate]['rent'] += $dailyRent;
    }

    // Process each order to aggregate values.
    foreach ($orders as $order) {
        $date = $order['date'];
        $report[$date]['total'] += $order['total'];
        $report[$date]['discount'] += $order['discount'];
        $report[$date]['shipping'] += $order['shipping'];
        $report[$date]['tax'] += $order['tax'];
        $report[$date]['shipping_tax'] += $order['shipping_tax'];

        foreach ($order['line_items'] as $item) {
            $product_id = $item['product_id'];
            $variation_id = $item['variation_id'];

            // Fetch product data.
            $product = wc_get_product($variation_id ?: $product_id);
            if (!$product) {
                continue;
            }

            $quantity = $item['quantity'];
            $cogs_price = floatval($product->get_meta('_cogs_price')) ?: 0;
            $packing_cost = floatval($product->get_meta('_packing_cost')) ?: 0;
            $work_time_minutes = floatval($product->get_meta('_work_time_minutes')) ?: 0;

            $report[$date]['quantity'] += $quantity;
            $report[$date]['cogs_price'] += $quantity * $cogs_price;
            $report[$date]['packing_cost'] += $quantity * $packing_cost;
            $report[$date]['work_time_minutes'] += (($quantity * $work_time_minutes) / 60) * 40;
        }
    }

    return $report;
}

/**
 * Helper: Generate report data for a specific date.
 */
function generate_order_product_report_for_date($target_date)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'shop_manager_orders';

    // Get orders whose stored date (from order_data) matches $target_date.
    // Assuming the stored JSON has a 'date' key matching the format 'Y-m-d'.
    $results = $wpdb->get_results(
        $wpdb->prepare("SELECT order_data FROM {$table_name} WHERE DATE(date_created) = %s", $target_date),
    );
    if (empty($results)) {
        return [];
    }

    $orders = [];
    foreach ($results as $row) {
        $order = json_decode($row->order_data, true);
        if ($order) {
            $orders[] = $order;
        }
    }
    if (empty($orders)) {
        return [];
    }

    // Load additional settings.
    $option_key = 'bsr_shop_manager_settings_data';
    $settings = get_option($option_key, [
        'costs' => [],
        'marketingCosts' => [],
        'rent' => [],
    ]);
    $costs = $settings['costs'];
    $marketingCosts = $settings['marketingCosts'];
    $rent = $settings['rent'];

    $report = [];
    // Initialize report for $target_date.
    $report[$target_date] = [
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
        'costs' => 0,
        'marketing_costs' => 0,
        'rent' => 0,
    ];

    // Calculate daily costs for $target_date.
    $dt = new DateTime($target_date);
    $year = intval($dt->format('Y'));
    $month = intval($dt->format('m'));
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $dailyCost = (isset($costs[$year][$month - 1]) ? $costs[$year][$month - 1] : 0) / $daysInMonth;
    $dailyMarketingCost =
        (isset($marketingCosts[$year][$month - 1]) ? $marketingCosts[$year][$month - 1] : 0) / $daysInMonth;
    $dailyRent = (isset($rent[$year][$month - 1]) ? $rent[$year][$month - 1] : 0) / $daysInMonth;

    $report[$target_date]['costs'] = $dailyCost;
    $report[$target_date]['marketing_costs'] = $dailyMarketingCost;
    $report[$target_date]['rent'] = $dailyRent;

    // Process each order for $target_date.
    foreach ($orders as $order) {
        $report[$target_date]['total'] += $order['total'];
        $report[$target_date]['discount'] += $order['discount'];
        $report[$target_date]['shipping'] += $order['shipping'];
        $report[$target_date]['tax'] += $order['tax'];
        $report[$target_date]['shipping_tax'] += $order['shipping_tax'];

        foreach ($order['line_items'] as $item) {
            $product_id = $item['product_id'];
            $variation_id = $item['variation_id'];
            $product = wc_get_product($variation_id ?: $product_id);
            if (!$product) {
                continue;
            }
            $quantity = $item['quantity'];
            $cogs_price = floatval($product->get_meta('_cogs_price')) ?: 0;
            $packing_cost = floatval($product->get_meta('_packing_cost')) ?: 0;
            $work_time_minutes = floatval($product->get_meta('_work_time_minutes')) ?: 0;

            $report[$target_date]['quantity'] += $quantity;
            $report[$target_date]['cogs_price'] += $quantity * $cogs_price;
            $report[$target_date]['packing_cost'] += $quantity * $packing_cost;
            $report[$target_date]['work_time_minutes'] += (($quantity * $work_time_minutes) / 60) * 40;
        }
    }
    return $report;
}

function shop_manager_schedule_generate_order_product_report()
{
    if (!wp_next_scheduled('generate_order_product_report')) {
        wp_schedule_event(time(), 'daily', 'generate_order_product_report');
    }
}
add_action('wp', 'shop_manager_schedule_generate_order_product_report');
