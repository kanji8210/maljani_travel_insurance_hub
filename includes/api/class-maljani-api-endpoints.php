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

        register_rest_route('maljani/v1', '/certificate/(?P<sale_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'serve_certificate'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'sale_id' => [
                    'required'          => true,
                    'validate_callback' => function($v) { return is_numeric($v) && $v > 0; },
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('maljani/v1', '/policy-document/(?P<sale_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'serve_policy_document'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'sale_id' => [
                    'required'          => true,
                    'validate_callback' => function($v) { return is_numeric($v) && $v > 0; },
                    'sanitize_callback' => 'absint',
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

        register_rest_route('maljani/v1', '/payment-status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_payment_status'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'OrderTrackingId' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'OrderMerchantReference' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
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
            global $wpdb;
            $stored_reference = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT payment_reference FROM {$wpdb->prefix}policy_sale WHERE id = %d LIMIT 1",
                $sale_id
            ));
            if ($stored_reference === '' || !hash_equals($stored_reference, (string) $tracking_id)) {
                return new WP_REST_Response(['status' => 'error', 'message' => 'Payment reference mismatch'], 409);
            }

            $status_code = (int)($status_data->status_code ?? -1);
            if ($status_code === 1) { // 1 = Completed
                $this->confirm_pesapal_payment($sale_id, $tracking_id, $status_data);
            } elseif (in_array($status_code, [2, 3], true)) { // Failed / reversed
                $this->mark_pesapal_payment_failed($sale_id, $tracking_id);
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

        if ($doc_type === 'receipt' && $sale->payment_status !== 'confirmed') {
            return new WP_REST_Response(['error' => 'Receipt available after payment confirmation'], 409);
        }

        $html = ($doc_type === 'receipt')
            ? Maljani_Invoice::build_receipt_html($sale)
            : Maljani_Invoice::build_invoice_html($sale);

        return new WP_REST_Response(['html' => $html], 200);
    }

    public function serve_certificate(WP_REST_Request $request) {
        $sale_id = (int) $request->get_param('sale_id');

        require_once plugin_dir_path( dirname( __FILE__ ) ) . '../admin/class-maljani-verification-certificate.php';

        $sale = Maljani_Verification_Certificate::get_sale( $sale_id );
        if ( ! $sale ) {
            return new WP_REST_Response( [ 'error' => 'Sale not found' ], 404 );
        }

        if ( ! Maljani_Verification_Certificate::user_can_view_sale( $sale ) ) {
            return new WP_REST_Response( [ 'error' => 'Unauthorized' ], 403 );
        }

        if ( $sale->payment_status !== 'confirmed' ) {
            return new WP_REST_Response( [ 'error' => 'Certificate available after payment confirmation' ], 409 );
        }

        $html = Maljani_Verification_Certificate::build_certificate_html( $sale );
        if ( '' === $html ) {
            return new WP_REST_Response( [ 'error' => 'Certificate unavailable' ], 500 );
        }

        return new WP_REST_Response( [ 'html' => $html ], 200 );
    }

    public function serve_policy_document(WP_REST_Request $request) {
        $sale_id = (int) $request->get_param('sale_id');
        $user_id = get_current_user_id();

        global $wpdb;
        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT id, agent_id, payment_status, policy_status FROM {$wpdb->prefix}policy_sale WHERE id = %d LIMIT 1",
            $sale_id
        ));

        if (!$sale) {
            return new WP_REST_Response(['error' => 'Sale not found'], 404);
        }
        if ((int) $sale->agent_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_REST_Response(['error' => 'Unauthorized'], 403);
        }
        if ($sale->payment_status !== 'confirmed' || !in_array($sale->policy_status, ['active', 'verification_ready'], true)) {
            return new WP_REST_Response(['error' => 'Insurer document is still being prepared'], 409);
        }

        $document_url = $wpdb->get_var($wpdb->prepare(
            "SELECT file_path FROM {$wpdb->prefix}maljani_documents WHERE policy_id = %d AND type = %s ORDER BY id DESC LIMIT 1",
            $sale_id,
            'policy_doc'
        ));

        if (!$document_url) {
            return new WP_REST_Response(['error' => 'Insurer document is not available yet'], 404);
        }

        return new WP_REST_Response(['url' => esc_url_raw($document_url)], 200);
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

        $callback_url = esc_url_raw((string) $request->get_param('callbackUrl'));
        $callback_parts = wp_parse_url($callback_url);
        if (
            !$callback_url ||
            empty($callback_parts['host']) ||
            empty($callback_parts['scheme']) ||
            !in_array(strtolower($callback_parts['scheme']), ['http', 'https'], true)
        ) {
            return new WP_REST_Response(['error' => 'Invalid payment return URL'], 400);
        }

        $name_parts = explode(' ', $sale->insured_names, 2);
        $order = $pesapal->create_order(
            $sale_id,
            (float) $sale->amount_paid,
            'Travel Insurance - ' . $sale->policy_number,
            [
                'email_address' => $sale->insured_email,
                'phone_number'  => $sale->insured_phone ?: '',
                'first_name'    => $name_parts[0] ?? '',
                'last_name'     => $name_parts[1] ?? '',
                'country_code'  => 'KE',
            ],
            $callback_url
        );

        if (is_wp_error($order)) {
            error_log('Maljani Pesapal initiate-payment failed for Sale ID ' . $sale_id . ': ' . $order->get_error_message());
            $error_data = $order->get_error_data();
            $status = is_array($error_data) && isset($error_data['status'])
                ? (int) $error_data['status']
                : 502;

            return new WP_REST_Response([
                'error' => $order->get_error_message(),
                'code'  => $order->get_error_code(),
            ], $status);
        }

        $wpdb->update($table,
            [
                'payment_reference' => $order['order_tracking_id'] ?: $order['merchant_reference'],
                'payment_status'    => 'pending',
            ],
            ['id' => $sale_id]
        );

        return new WP_REST_Response([
            'paymentUrl'        => $order['redirect_url'],
            'orderTrackingId'   => $order['order_tracking_id'],
            'merchantReference' => $order['merchant_reference'],
        ], 200);
    }

    public function get_payment_status(WP_REST_Request $request) {
        $tracking_id = sanitize_text_field((string) $request->get_param('OrderTrackingId'));
        $merchant_ref = sanitize_text_field((string) $request->get_param('OrderMerchantReference'));
        $reference_parts = explode('-', $merchant_ref);
        $sale_id = (int) ($reference_parts[0] ?? 0);

        if ($sale_id <= 0) {
            return new WP_REST_Response(['error' => 'Invalid payment reference'], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            $sale_id
        ));

        if (!$sale) {
            return new WP_REST_Response(['error' => 'Sale not found'], 404);
        }

        if ((int) $sale->agent_id !== get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_REST_Response(['error' => 'Unauthorized'], 403);
        }

        $stored_reference = (string) ($sale->payment_reference ?? '');
        if ($stored_reference === '' || !hash_equals($stored_reference, $tracking_id)) {
            return new WP_REST_Response(['error' => 'Payment reference mismatch'], 409);
        }

        require_once plugin_dir_path(__FILE__) . 'class-maljani-pesapal-gateway.php';
        $pesapal = new Maljani_Pesapal_Gateway();
        $status_data = $pesapal->get_transaction_status($tracking_id);

        if (is_wp_error($status_data)) {
            return new WP_REST_Response(['error' => $status_data->get_error_message()], 502);
        }

        $status_code = (int) ($status_data->status_code ?? -1);
        $provider_reference = (string) ($status_data->merchant_reference ?? '');
        if ($provider_reference !== '' && !hash_equals($merchant_ref, $provider_reference)) {
            return new WP_REST_Response(['error' => 'Merchant reference mismatch'], 409);
        }
        if ($status_code === 1) {
            $this->confirm_pesapal_payment($sale_id, $tracking_id, $status_data);
        } elseif (in_array($status_code, [2, 3], true)) {
            $this->mark_pesapal_payment_failed($sale_id, $tracking_id);
        }

        return new WP_REST_Response([
            'saleId'        => $sale_id,
            'confirmed'     => $status_code === 1,
            'failed'        => in_array($status_code, [2, 3], true),
            'paymentStatus' => sanitize_text_field((string) ($status_data->payment_status_description ?? 'Pending')),
        ], 200);
    }

    private function confirm_pesapal_payment($sale_id, $tracking_id, $status_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';

        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT payment_status FROM {$table} WHERE id = %d LIMIT 1",
            $sale_id
        ));

        $payment_date = null;
        if (!empty($status_data->created_date)) {
            $timestamp = strtotime((string) $status_data->created_date);
            if ($timestamp !== false) {
                $payment_date = gmdate('Y-m-d H:i:s', $timestamp);
            }
        }

        $payment_data = [
            'payment_status'            => 'confirmed',
            'payment_reference'         => sanitize_text_field((string) $tracking_id),
            'payment_method'            => sanitize_text_field((string) ($status_data->payment_method ?? '')),
            'payment_account'           => sanitize_text_field((string) ($status_data->payment_account ?? '')),
            'payment_confirmation_code' => sanitize_text_field((string) ($status_data->confirmation_code ?? '')),
            'payment_currency'          => sanitize_text_field((string) ($status_data->currency ?? '')),
            'payment_received_amount'   => isset($status_data->amount) ? (float) $status_data->amount : null,
            'payment_confirmed_at'       => $payment_date ?: current_time('mysql', true),
        ];

        if ($current_status !== 'confirmed') {
            $payment_data['insurer_payment_status'] = 'due';
            $payment_data['policy_status'] = 'pending_review';
            $payment_data['workflow_status'] = 'pending_review';
        }

        $wpdb->update($table, $payment_data, ['id' => $sale_id]);

        if ($current_status !== 'confirmed') {
            do_action('maljani_payment_confirmed', $sale_id);
        }
    }

    private function mark_pesapal_payment_failed($sale_id, $tracking_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';

        $wpdb->update($table,
            [
                'payment_status'    => 'failed',
                'payment_reference' => $tracking_id,
            ],
            ['id' => $sale_id]
        );

        do_action('maljani_payment_failed', $sale_id);
    }
}
new Maljani_API_Endpoints();
