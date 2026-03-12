<?php
/**
 * Maljani API Endpoints
 * Registers REST API routes for Webhooks and Integrations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_API_Endpoints {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('maljani/v1', '/pesapal/callback', [
            'methods'  => 'GET',
            'callback' => [$this, 'handle_pesapal_ipn'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Handle Pesapal IPN (Instant Payment Notification)
     */
    public function handle_pesapal_ipn($request) {
        $tracking_id = $request->get_param('OrderTrackingId');
        $merchant_ref = $request->get_param('OrderMerchantReference'); // Our internal ID

        if (!$tracking_id || !$merchant_ref) {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Missing parameters'], 400);
        }

        require_once plugin_dir_path(__FILE__) . 'class-maljani-pesapal-gateway.php';
        $pesapal = new Maljani_Pesapal_Gateway();
        $status_data = $pesapal->get_transaction_status($tracking_id);

        if (is_wp_error($status_data)) {
            return new WP_REST_Response(['status' => 'error', 'message' => $status_data->get_error_message()], 500);
        }

        // Logic to update DB
        // Format of $merchant_ref should be "{sale_id}-{timestamp}"
        $ref_parts = explode('-', $merchant_ref);
        $sale_id = (int)$ref_parts[0];

        if ($sale_id > 0) {
            if ($status_data->status_code === 1) { // 1 = Completed
                $this->activate_policy($sale_id, $tracking_id);
            }
        }

        return new WP_REST_Response(['status' => 'success', 'pesapal_status' => $status_data->payment_status_description], 200);
    }

    private function activate_policy($sale_id, $tracking_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        
        // 1. Update Payment Status
        $wpdb->update($table, 
            ['payment_status' => 'confirmed', 'payment_reference' => $tracking_id],
            ['id' => $sale_id]
        );

        // 2. Trigger the Insurer API Engine
        if (!class_exists('Maljani_Insurer_Engine')) {
            require_once plugin_dir_path(__FILE__) . 'class-maljani-insurer-engine.php';
        }

        if (class_exists('Maljani_Insurer_Engine')) {
            $engine = new Maljani_Insurer_Engine();
            $engine->trigger_registration($sale_id);
        }

        // 3. Notify Admin (To be implemented in notification class)
        do_action('maljani_policy_activated', $sale_id);
    }
}
new Maljani_API_Endpoints();
