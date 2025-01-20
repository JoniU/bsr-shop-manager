<?php
// Ensuring ABSPATH for security
if (!defined('ABSPATH')) {
    exit;
}

function shop_manager_fetch_orders_data_callback() {
    check_ajax_referer('shop_manager_nonce', 'security');

    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;

    $args = array(
        'post_type' => 'shop_order',
        'post_status' => ['wc-completed', 'wc-processing'],
        'posts_per_page' => 200,
        'paged' => $paged,
    );

    // Add date query if provided
    if ($start_date && $end_date) {
        $args['date_query'] = array(
            array(
                'after'     => $start_date,
                'before'    => $end_date,
                'inclusive' => true,
            ),
        );
    }

    $orders_query = new WP_Query($args);
    $orders_data = [];

    // Get the store's base currency (Euro in this case)
    $base_currency = get_option('woocommerce_currency');

    if ($orders_query->have_posts()) {
        while ($orders_query->have_posts()) {
            $orders_query->the_post();
            $order = wc_get_order(get_the_ID());

            // Get the order currency
            $order_currency = $order->get_currency();

            // Retrieve billed amount in base currency
            $order_total_base = get_post_meta($order->get_id(), '_order_total_base_currency', true);
            $order_total_base = $order_total_base !== '' ? floatval($order_total_base) : floatval($order->get_total());

            $order_shipping_base = get_post_meta($order->get_id(), '_order_shipping_base_currency', true);
            $order_shipping_base = $order_shipping_base !== '' ? floatval($order_shipping_base) : floatval($order->get_shipping_total());

            $order_tax_base = get_post_meta($order->get_id(), '_order_tax_base_currency', true);
            $order_tax_base = $order_tax_base !== '' ? floatval($order_tax_base) : floatval($order->get_total_tax());

            $order_shipping_tax_base = get_post_meta($order->get_id(), '_order_shipping_tax_base_currency', true);
            $order_shipping_tax_base = $order_shipping_tax_base !== '' ? floatval($order_shipping_tax_base) : floatval($order->get_shipping_tax());

            $order_discount_base = get_post_meta($order->get_id(), '_order_discount_base_currency', true);
            $order_discount_base = $order_discount_base !== '' ? floatval($order_discount_base) : floatval($order->get_discount_total());

            $order_data = [
                'date' => $order->get_date_created()->date('Y-m-d'),
                'billed' => $order_total_base,
                'revenue' => $order_total_base - $order_tax_base - $order_discount_base, // Adjusted to include discount
                'shipping' => $order_shipping_base,
                'tax' => $order_tax_base,
                'shipping_tax' => $order_shipping_tax_base,
                'discount' => $order_discount_base,
                'cogs' => 0,
                'packing_cost' => 0,
            ];

            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();

                $cogs = floatval(get_post_meta($product_id, '_cogs_price', true)) * $quantity;
                $packing_cost_meta = get_post_meta($product_id, '_packing_cost', true);
                $packing_cost = (isset($packing_cost_meta) && $packing_cost_meta !== '') ? floatval($packing_cost_meta) * $quantity : 5 * $quantity;

                // Add COGS and packing cost to order data (stored in EUR)
                $order_data['cogs'] += $cogs;
                $order_data['packing_cost'] += $packing_cost;

                // Debugging for individual items
                error_log("Order ID: {$order->get_id()} | Product ID: $product_id | Quantity: $quantity | COGS: $cogs | Packing Cost: $packing_cost");
            }

            // Debugging to check all fields for the order
            error_log(json_encode([
                'order_id' => $order->get_id(),
                'billed' => $order_data['billed'],
                'revenue' => $order_data['revenue'],
                'shipping' => $order_data['shipping'],
                'tax' => $order_data['tax'],
                'discount' => $order_data['discount'],
                'cogs' => $order_data['cogs'],
                'packing_cost' => $order_data['packing_cost'],
            ]));

            $orders_data[] = $order_data;
        }
    }

    wp_reset_postdata();

    wp_send_json_success([
        'orders' => $orders_data,
        'max_pages' => $orders_query->max_num_pages,
    ]);
}
add_action('wp_ajax_shop_manager_fetch_orders_data', 'shop_manager_fetch_orders_data_callback');

/**
 * Convert an amount to the store's base currency using Aelia Currency Switcher.
 *
 * @param float $amount Amount to convert.
 * @param string $currency Currency of the amount.
 * @param string $base_currency The base currency (e.g., EUR).
 *
 * @return float The converted amount in the base currency.
 */
function shop_manager_convert_to_base_currency($amount, $currency, $base_currency) {
    if (class_exists('Aelia_CurrencySwitcher')) {
        $currency_switcher = WC_Aelia_CurrencySwitcher::instance();
        if ($currency !== $base_currency) {
            return $currency_switcher->convert($amount, $currency, $base_currency);
        }
    }
    return $amount; // Return unchanged if conversion is not needed or Currency Switcher is unavailable
}
