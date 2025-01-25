<?php
if (!defined('ABSPATH')) {
    exit(); // Prevent direct access.
}

if (!defined('ABSPATH')) {
    exit(); // Prevent direct access.
}

function shop_manager_store_all_orders_to_json()
{
    // Define the JSON file path
    $upload_dir = wp_upload_dir();
    $cache_file = trailingslashit($upload_dir['basedir']) . 'bsr-shop-manager/all_orders.json';

    // Ensure the directory exists
    wp_mkdir_p(dirname($cache_file));

    $per_page = 100; // Number of orders to fetch per batch
    $current_page = 1;
    $total_orders_data = [];

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

            // Use _order_total_base_currency if available; otherwise, calculate using exchange rate
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

            // Initialize order data
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
                    'meta_data' => $line_item_meta_data,
                ];
            }

            $total_orders_data[] = $order_data;
        }

        $current_page++; // Move to the next page
    } while (count($orders) === $per_page); // Continue until no more orders are fetched

    // Write the data to a JSON file
    $result = file_put_contents($cache_file, json_encode(['orders' => $total_orders_data], JSON_PRETTY_PRINT));

    if ($result === false) {
        error_log("Failed to write all orders to: $cache_file");
        return false;
    }

    error_log("All orders stored successfully at: $cache_file");
    return true;
}

/**
 * Add a manual button in the WordPress admin to generate orders JSON.
 *
 * @param string $menu_slug The menu slug for the button.
 * @param string $page_title The page title.
 * @param string $menu_title The menu title.
 * @param string $nonce_action The nonce action.
 * @param callable $callback The function to call for JSON generation.
 */
function shop_manager_add_generate_json_button($menu_slug, $page_title, $menu_title, $nonce_action, $callback)
{
    add_submenu_page('tools.php', $page_title, $menu_title, 'manage_options', $menu_slug, function () use (
        $nonce_action,
        $callback,
    ) {
        if (isset($_POST['generate_json']) && check_admin_referer($nonce_action)) {
            $success = $callback();
            echo $success
                ? '<div class="updated"><p>JSON file generated successfully.</p></div>'
                : '<div class="error"><p>Failed to generate JSON file.</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html($page_title) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field($nonce_action);
        echo '<p><input type="submit" name="generate_json" class="button-primary" value="Generate JSON"></p>';
        echo '</form>';
        echo '</div>';
    });
}

/**
 * Register admin menu items for JSON generation.
 */
function shop_manager_register_json_generation_menus()
{
    shop_manager_add_generate_json_button(
        'generate-all-orders-json',
        'Generate All Orders JSON',
        'All Orders JSON',
        'generate_all_orders_json',
        'shop_manager_store_all_orders_to_json',
    );
}
add_action('admin_menu', 'shop_manager_register_json_generation_menus');
