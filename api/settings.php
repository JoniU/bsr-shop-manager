<?php
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/bsr-shop-manager-settings', [
        'methods' => ['GET', 'POST'],
        'callback' => 'handle_settings_request',
        'permission_callback' => '__return_true',
    ]);
});

function handle_settings_request(WP_REST_Request $request)
{
    $option_key = 'bsr_shop_manager_settings_data';

    if ($request->get_method() === 'GET') {
        ob_clean(); // Clear the output buffer
        $settings = get_option($option_key, []);
        error_log('Settings retrieved: ' . json_encode($settings));
        return rest_ensure_response($settings);
    }

    if ($request->get_method() === 'POST') {
        // Validate and save settings data
        $data = $request->get_json_params();

        if (!is_array($data)) {
            return new WP_Error('invalid_data', 'Invalid data format.', ['status' => 400]);
        }

        // Save the settings to the database
        update_option($option_key, $data);
        return rest_ensure_response(['success' => true, 'message' => 'Settings saved.']);
    }
}
