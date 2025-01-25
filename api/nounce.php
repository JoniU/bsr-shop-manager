<?php
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/get-nonce', [
        'methods' => 'GET',
        'callback' => function () {
            return [
                'nonce' => wp_create_nonce('wp_rest'),
            ];
        },
        'permission_callback' => '__return_true', // Allow public access to this route
    ]);
});
