<?php
/**
 * Maljani Insurer Adapter: Sandbox
 * A dummy adapter for testing the API Engine flow.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_Insurer_Adapter_Sandbox implements Maljani_Insurer_Adapter {

    /**
     * Simulates registration with an insurer.
     */
    public function register_traveler($sale_data) {
        // Logic check: simulate a small delay like a real API
        // usleep(500000); // 0.5 seconds

        $sale_id = $sale_data['id'] ?? '??';
        
        // Mock success response
        return [
            'success'       => true,
            'policy_number' => 'SNDBX-' . strtoupper(wp_generate_password(8, false)),
            'external_id'   => 'TRANS-' . time(),
            'raw_response'  => '{"status":"success","message":"Mocked registration successful for Sale ID ' . $sale_id . '"}'
        ];
    }

    public function get_status($external_id) {
        return ['status' => 'active'];
    }

    public function cancel_policy($external_id, $reason) {
        return ['status' => 'cancelled'];
    }
}
