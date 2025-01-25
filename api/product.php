<?php
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
    register_rest_route('custom/v1', '/update-product/(?P<id>\d+)', [
        'methods' => 'POST',
        'callback' => 'custom_update_product',
        'permission_callback' => function () {
            // Check if the current user has permission to edit products
            return current_user_can('edit_products');
        },
    ]);
});

function custom_update_product($request)
{
    $product_id = $request->get_param('id');
    $updates = $request->get_json_params();

    if (!$product_id || empty($updates)) {
        return new WP_Error('invalid_request', 'Product ID and updates are required.', ['status' => 400]);
    }

    // Fetch the product or variation
    $product = wc_get_product($product_id);

    if (!$product) {
        return new WP_Error('product_not_found', 'Product not found.', ['status' => 404]);
    }

    // Check if it's a variation
    $is_variation = $product->is_type('variation');

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
        $response_data['parent_id'] = $product->get_parent_id(); // Add parent product ID for variations
    }

    return new WP_REST_Response(['success' => true, 'product' => $response_data], 200);
}
