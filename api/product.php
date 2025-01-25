<?php
if (!defined('ABSPATH')) {
    exit(); // Prevent direct access.
}

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/get-products', [
        'methods' => 'GET',
        'callback' => 'get_products_directly',
        'permission_callback' => '__return_true',
    ]);
});

function get_products_directly()
{
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
    ];

    $query = new WP_Query($args);
    $products = [];

    foreach ($query->posts as $post) {
        $product = wc_get_product($post->ID);

        // Prepare variations array
        $variations = [];
        if ($product->is_type('variable')) {
            $variation_ids = $product->get_children();
            foreach ($variation_ids as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    // Extract meta fields for variations
                    $variation_meta_data = get_post_meta($variation_id);
                    $variations[] = [
                        'id' => $variation->get_id(),
                        'name' => $variation->get_name(),
                        'regular_price' => $variation->get_regular_price(),
                        'sale_price' => $variation->get_sale_price(),
                        'price' => $variation->get_price(),
                        'price_excl_tax' => wc_get_price_excluding_tax($variation),
                        'sku' => $variation->get_sku(),
                        'stock_quantity' => $variation->get_stock_quantity(),
                        'attributes' => $variation->get_attributes(),
                        'meta_data' => [
                            '_cogs_price' => $variation_meta_data['_cogs_price'][0] ?? '',
                            '_packing_cost' => $variation_meta_data['_packing_cost'][0] ?? '',
                            '_work_time_minutes' => $variation_meta_data['_work_time_minutes'][0] ?? '',
                            '_development_cost' => $variation_meta_data['_development_cost'][0] ?? '',
                            '_development_months' => $variation_meta_data['_development_months'][0] ?? '',
                        ],
                        'manage_stock' =>
                            isset($variation_meta_data['_manage_stock'][0]) &&
                            $variation_meta_data['_manage_stock'][0] === 'yes',
                    ];
                }
            }
        }

        // Extract meta fields for the main product
        $meta_data = get_post_meta($product->get_id());
        $products[] = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'price' => $product->get_price(),
            'price_excl_tax' => wc_get_price_excluding_tax($product),
            'sku' => $product->get_sku(),
            'type' => ucfirst($product->get_type()),
            'stock_quantity' => $product->get_stock_quantity(),
            'meta_data' => [
                '_cogs_price' => $meta_data['_cogs_price'][0] ?? '',
                '_packing_cost' => $meta_data['_packing_cost'][0] ?? '',
                '_work_time_minutes' => $meta_data['_work_time_minutes'][0] ?? '',
                '_development_cost' => $meta_data['_development_cost'][0] ?? '',
                '_development_months' => $meta_data['_development_months'][0] ?? '',
            ],
            'manage_stock' => isset($meta_data['_manage_stock'][0]) && $meta_data['_manage_stock'][0] === 'yes',
            'variations' => $variations,
        ];
    }

    return $products;
}

add_action('rest_api_init', function () {
    // Register endpoint for products
    register_rest_route('custom/v1', '/products/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'custom_update_product',
        'permission_callback' => '__return_true',
    ]);

    // Register endpoint for variations
    register_rest_route('custom/v1', '/products/(?P<parent_id>\d+)/variations/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'custom_update_variation',
        'permission_callback' => '__return_true',
    ]);
});

// Callback for updating parent products
function custom_update_product($request)
{
    return custom_handle_product_update($request, false);
}

// Callback for updating variations
function custom_update_variation($request)
{
    return custom_handle_product_update($request, true);
}

// Shared logic for updating products and variations
function custom_handle_product_update($request, $is_variation)
{
    $product_id = $request->get_param('id');
    $parent_id = $is_variation ? $request->get_param('parent_id') : null;
    $updates = $request->get_json_params();

    if (!$product_id || empty($updates)) {
        return new WP_Error('invalid_request', 'Product ID and updates are required.', ['status' => 400]);
    }

    // Fetch the product or variation
    $product = wc_get_product($product_id);

    if (!$product) {
        return new WP_Error('product_not_found', 'Product not found.', ['status' => 404]);
    }

    // Ensure it's a variation if updating a variation
    if ($is_variation && !$product->is_type('variation')) {
        return new WP_Error('invalid_request', 'Specified ID is not a variation.', ['status' => 400]);
    }

    // Ensure it's not a variation if updating a parent product
    if (!$is_variation && $product->is_type('variation')) {
        return new WP_Error('invalid_request', 'Specified ID is a variation, not a parent product.', [
            'status' => 400,
        ]);
    }

    // Apply updates
    foreach ($updates as $key => $value) {
        if (is_callable([$product, "set_$key"])) {
            $product->{"set_$key"}($value);
        }
    }

    $product->save();

    // Prepare the response
    $response_data = $product->get_data();
    if ($is_variation) {
        $response_data['parent_id'] = $parent_id; // Add parent product ID for variations
    }

    return new WP_REST_Response(['success' => true, 'product' => $response_data], 200);
}
