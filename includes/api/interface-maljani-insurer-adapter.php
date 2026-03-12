<?php
/**
 * Maljani Insurer Adapter Interface
 * All insurer-specific API adapters must implement this interface.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Maljani_Insurer_Adapter {

    /**
     * Register a traveler with the insurer's API.
     *
     * @param array $sale_data Full row from maljani_policy_sales.
     * @return array|WP_Error Returns ['policy_number' => 'xxx', 'raw_response' => '...'] on success.
     */
    public function register_traveler($sale_data);

    /**
     * Check the status of a previously submitted policy (if needed).
     *
     * @param string $external_id The ID returned by the insurer during registration.
     * @return array|WP_Error
     */
    public function get_status($external_id);

    /**
     * Cancellation or Void request
     */
    public function cancel_policy($external_id, $reason);

}
