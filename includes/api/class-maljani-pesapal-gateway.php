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
        $this->consumer_key    = trim((string) get_option('maljani_pesapal_consumer_key'));
        $this->consumer_secret = trim((string) get_option('maljani_pesapal_consumer_secret'));
        $this->is_sandbox      = get_option('maljani_pesapal_mode', 'sandbox') === 'sandbox';
        $this->api_base        = $this->is_sandbox ? 'https://cybqa.pesapal.com/pesapalv3' : 'https://pay.pesapal.com/v3';
    }

    private function response_error_message($response, $fallback = 'Unknown error') {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $payload = trim(wp_strip_all_tags((string) $raw_body));
        if (strlen($payload) > 500) {
            $payload = substr($payload, 0, 500) . '...';
        }

        $body = json_decode($raw_body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return trim('HTTP ' . $code . ': ' . ($payload ?: $fallback));
        }

        $candidates = [
            $body->error->message ?? null,
            $body->error_description ?? null,
            $body->message ?? null,
            $body->error ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim('HTTP ' . $code . ': ' . $candidate);
            }
        }

        $status = isset($body->status) ? ' Pesapal status: ' . sanitize_text_field((string) $body->status) . '.' : '';
        $payload_message = $payload ? ' Response: ' . $payload : '';

        return trim('HTTP ' . $code . ':' . $status . ' ' . $fallback . $payload_message);
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
            'body'    => wp_json_encode([
                'consumer_key'    => $this->consumer_key,
                'consumer_secret' => $this->consumer_secret
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));
        if ($code >= 200 && $code < 300 && isset($body->token)) {
            return $body->token;
        }

        $message = $this->response_error_message($response, 'No token returned. Check Pesapal environment and API credentials.');
        error_log('Maljani Pesapal token request failed (' . ($this->is_sandbox ? 'sandbox' : 'live') . '): ' . $message);

        return new WP_Error('token_failed', 'Failed to retrieve Pesapal token: ' . $message);
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
            'body' => wp_json_encode([
                'url'                 => $ipn_url,
                'ipn_notification_type' => 'GET'
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));
        if ($code >= 200 && $code < 300 && isset($body->ipn_id)) {
            update_option('maljani_pesapal_ipn_id', $body->ipn_id);
            return $body->ipn_id;
        }

        return new WP_Error('ipn_failed', 'Failed to register IPN: ' . $this->response_error_message($response));
    }

    /**
     * Create Order and Return Payment URL
     */
    public function create_order($sale_id, $amount, $description, $billing_info = []) {
        $token = $this->get_token();
        if (is_wp_error($token)) return $token;

        $ipn_id = get_option('maljani_pesapal_ipn_id');
        if (!$ipn_id) {
            $ipn_id = $this->register_ipn();
            if (is_wp_error($ipn_id)) return $ipn_id;
        }

        $endpoint = $this->api_base . '/api/Transactions/SubmitOrderRequest';

        $merchant_reference = (string)$sale_id . '-' . time();
        $currency = strtoupper(get_option('maljani_inv_currency', 'KES'));
        if ($currency === 'KSH') {
            $currency = 'KES';
        }

        $body_array = [
            'id'               => $merchant_reference,
            'currency'         => $currency,
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

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ],
            'body'    => wp_json_encode($body_array),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));
        if ($code < 200 || $code >= 300) {
            return new WP_Error('order_failed', 'Order creation failed: ' . $this->response_error_message($response));
        }

        if (isset($body->redirect_url)) {
            return [
                'redirect_url'        => $body->redirect_url,
                'order_tracking_id'  => $body->order_tracking_id ?? '',
                'merchant_reference' => $body->merchant_reference ?? $merchant_reference,
            ];
        }

        return new WP_Error('order_failed', 'Order creation failed: ' . $this->response_error_message($response, 'No redirect URL returned.'));
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
