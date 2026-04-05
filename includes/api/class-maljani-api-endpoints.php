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
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_pesapal_ipn'],
            'permission_callback' => '__return_true',
        ]);

        // ── Section 15.2: Public policy verification endpoint ─────────────────
        register_rest_route('maljani/v1', '/verify', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'args'                => [
                'policy_no' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'passport'  => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
            'callback'            => [$this, 'verify_policy'],
        ]);
        register_rest_route('maljani/v1', '/my-policies', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_my_policies'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);

        // ── Invoice / Receipt viewer ──────────────────────────────────────────
        register_rest_route('maljani/v1', '/invoice/(?P<sale_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'serve_invoice'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'sale_id' => [
                    'required'          => true,
                    'validate_callback' => function($v) { return is_numeric($v) && $v > 0; },
                    'sanitize_callback' => 'absint',
                ],
                'doc_type' => [
                    'default'           => 'invoice',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        // ── Initiate Pesapal payment ──────────────────────────────────────────
        register_rest_route('maljani/v1', '/initiate-payment', [
            'methods'             => 'POST',
            'callback'            => [$this, 'initiate_payment'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'saleId' => [
                    'required'          => true,
                    'validate_callback' => function($v) { return is_numeric($v) && $v > 0; },
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Flush once if the route was just added
        if (get_option('maljani_rest_flushed_v2') !== '1') {
            flush_rewrite_rules();
            update_option('maljani_rest_flushed_v2', '1');
        }
    }

    /**
     * Get policies for the current logged-in user.
     */
    public function get_my_policies(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['error' => 'Unauthorized'], 401);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        
        // We use agent_id for both agents and regular insured users as the owner id
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE agent_id = %d ORDER BY created_at DESC",
            $user_id
        ));

        $policies = [];
        foreach ($results as $p) {
            $policies[] = [
                'id'            => (int)$p->id,
                'policyId'      => (int)$p->policy_id,
                'policyTitle'   => get_the_title($p->policy_id),
                'policyNumber'  => $p->policy_number,
                'region'        => $p->region,
                'premium'       => (float)$p->premium,
                'days'          => (int)$p->days,
                'departure'     => $p->departure,
                'return'        => $p->return,
                'passengers'    => (int)($p->passengers ?? 1),
                'amountPaid'    => (float)$p->amount_paid,
                'paymentStatus' => $p->payment_status,
                'policyStatus'  => $p->policy_status,
                'createdAt'     => $p->created_at,
            ];
        }

        return new WP_REST_Response(['policies' => $policies], 200);
    }

    /**
     * Public policy verification.
     * GET /wp-json/maljani/v1/verify?policy_no=MAL-1234&passport=AB123456
     */
    public function verify_policy( WP_REST_Request $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';

        $sale = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE policy_number = %s AND passport_number = %s LIMIT 1",
            $request->get_param('policy_no'),
            $request->get_param('passport')
        ) );

        if ( ! $sale ) {
            return new WP_REST_Response(
                [ 'valid' => false, 'message' => 'No matching policy found.' ],
                404
            );
        }

        return new WP_REST_Response( [
            'valid'        => true,
            'insuredNames' => $sale->insured_names,
            'departure'    => $sale->departure,
            'return'       => $sale->return,
            'region'       => $sale->region,
            'policyTitle'  => get_the_title( intval( $sale->policy_id ) ),
            'status'       => $sale->policy_status,
        ], 200 );
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

    /**
     * Serve invoice or receipt HTML for a sale.
     * GET /wp-json/maljani/v1/invoice/{sale_id}?doc_type=invoice|receipt
     */
    public function serve_invoice(WP_REST_Request $request) {
        $sale_id  = (int) $request->get_param('sale_id');
        $doc_type = $request->get_param('doc_type') ?: 'invoice';
        $user_id  = get_current_user_id();

        if (!class_exists('Maljani_Invoice')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'class-maljani-invoice.php';
        }

        $sale = Maljani_Invoice::get_sale($sale_id);
        if (!$sale) {
            return new WP_REST_Response(['error' => 'Sale not found'], 404);
        }

        // Ownership check: user must own the sale or be admin
        if ((int) $sale->agent_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_REST_Response(['error' => 'Unauthorized'], 403);
        }

        $html = ($doc_type === 'receipt')
            ? Maljani_Invoice::build_receipt_html($sale)
            : Maljani_Invoice::build_invoice_html($sale);

        return new WP_REST_Response(['html' => $html], 200);
    }

    /**
     * Initiate Pesapal payment for a pending sale.
     * POST /wp-json/maljani/v1/initiate-payment  { saleId: 123 }
     */
    public function initiate_payment(WP_REST_Request $request) {
        $sale_id = (int) $request->get_param('saleId');
        $user_id = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        $sale  = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $sale_id
        ));

        if (!$sale) {
            return new WP_REST_Response(['error' => 'Sale not found'], 404);
        }

        // Ownership check
        if ((int) $sale->agent_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_REST_Response(['error' => 'Unauthorized'], 403);
        }

        // Only pending sales can initiate payment
        if ($sale->payment_status === 'confirmed') {
            return new WP_REST_Response(['error' => 'Payment already confirmed'], 400);
        }

        require_once plugin_dir_path(__FILE__) . 'class-maljani-pesapal-gateway.php';
        $pesapal = new Maljani_Pesapal_Gateway();

        $name_parts = explode(' ', $sale->insured_names, 2);
        $payment_url = $pesapal->create_order(
            $sale_id,
            (float) $sale->amount_paid,
            'Travel Insurance - ' . $sale->policy_number,
            [
                'email_address' => $sale->insured_email,
                'phone_number'  => $sale->insured_phone ?: '',
                'first_name'    => $name_parts[0] ?? '',
                'last_name'     => $name_parts[1] ?? '',
                'country_code'  => 'KE',
            ]
        );

        if (is_wp_error($payment_url)) {
            return new WP_REST_Response(['error' => $payment_url->get_error_message()], 500);
        }

        return new WP_REST_Response(['paymentUrl' => $payment_url], 200);
    }

    private function activate_policy($sale_id, $tracking_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        
        // 1. Update Payment and Policy Status
        $wpdb->update($table, 
            [
                'payment_status'    => 'confirmed', 
                'payment_reference' => $tracking_id,
                'policy_status'     => 'active'
            ],
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
