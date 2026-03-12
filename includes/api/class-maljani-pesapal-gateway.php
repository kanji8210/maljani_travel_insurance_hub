<?php
/**
 * Maljani Pesapal Gateway
 * Handles interaction with Pesapal v3.0 API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_Pesapal_Gateway {

    private $consumer_key;
    private $consumer_secret;
    private $is_sandbox;
    private $api_base;

    public function __construct() {
        $this->consumer_key    = get_option('maljani_pesapal_consumer_key');
        $this->consumer_secret = get_option('maljani_pesapal_consumer_secret');
        $this->is_sandbox      = get_option('maljani_pesapal_mode', 'sandbox') === 'sandbox';
        $this->api_base        = $this->is_sandbox ? 'https://cybqa.pesapal.com/pesapalv3' : 'https://pay.pesapal.com/v3';
    }

    /**
     * Get OAuth Token
     */
    public function get_token() {
        if (!$this->consumer_key || !$this->consumer_secret) {
            return new WP_Error('missing_keys', 'Pesapal API keys are not configured.');
        }

        $endpoint = $this->api_base . '/api/Auth/RequestToken';
        
        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => json_encode([
                'consumer_key'    => $this->consumer_key,
                'consumer_secret' => $this->consumer_secret
            ])
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body->token)) {
            return $body->token;
        }

        return new WP_Error('token_failed', 'Failed to retrieve Pesapal token: ' . ($body->error->message ?? 'Unknown error'));
    }

    /**
     * Register IPN URL
     */
    public function register_ipn() {
        $token = $this->get_token();
        if (is_wp_error($token)) return $token;

        $endpoint = $this->api_base . '/api/URLSetup/RegisterIPN';
        $ipn_url  = get_rest_url(null, 'maljani/v1/pesapal/callback');

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ],
            'body' => json_encode([
                'url'                 => $ipn_url,
                'ipn_notification_type' => 'GET'
            ])
        ]);

        if (is_wp_error($response)) return $response;

        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body->ipn_id)) {
            update_option('maljani_pesapal_ipn_id', $body->ipn_id);
            return $body->ipn_id;
        }

        return new WP_Error('ipn_failed', 'Failed to register IPN.');
    }

    /**
     * Create Order and Return Payment URL
     */
    public function create_order($sale_id, $amount, $description, $billing_info = [], $split_payload = []) {
        $token = $this->get_token();
        if (is_wp_error($token)) return $token;

        $ipn_id = get_option('maljani_pesapal_ipn_id');
        if (!$ipn_id) {
            $ipn_id = $this->register_ipn();
            if (is_wp_error($ipn_id)) return $ipn_id;
        }

        $endpoint = $this->api_base . '/api/Transactions/SubmitOrderRequest';

        $body_array = [
            'id'               => (string)$sale_id . '-' . time(), // Unique internal ID
            'currency'         => get_option('maljani_inv_currency', 'KSH'),
            'amount'           => (float)$amount,
            'description'      => $description,
            'callback_url'     => home_url('/checkout-thank-you/'), // Fallback return URL
            'notification_id'  => $ipn_id,
            'billing_address'  => array_merge([
                'email_address' => '',
                'phone_number'  => '',
                'country_code'  => 'KE',
                'first_name'    => 'Maljani',
                'last_name'     => 'Client',
                'line_1'        => '',
                'city'          => 'Nairobi'
            ], $billing_info)
        ];

        // Handle Split Payment if payload provided
        if (!empty($split_payload)) {
            $body_array['split_claims'] = $split_payload;
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ],
            'body' => json_encode($body_array)
        ]);

        if (is_wp_error($response)) return $response;

        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body->redirect_url)) {
            return $body->redirect_url;
        }

        return new WP_Error('order_failed', 'Order creation failed: ' . ($body->error->message ?? 'Unknown error'));
    }

    /**
     * Get Transaction Status
     */
    public function get_transaction_status($order_tracking_id) {
        $token = $this->get_token();
        if (is_wp_error($token)) return $token;

        $endpoint = $this->api_base . '/api/Transactions/GetTransactionStatus?orderTrackingId=' . $order_tracking_id;

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        if (is_wp_error($response)) return $response;

        return json_decode(wp_remote_retrieve_body($response));
    }
}
