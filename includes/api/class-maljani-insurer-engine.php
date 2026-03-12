<?php
/**
 * Maljani Insurer Engine
 * Central dispatcher for routing policy data to external insurer APIs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maljani_Insurer_Engine {

    /**
     * Trigger registration for a specific sale.
     * Generally called after successful payment confirmation.
     */
    public function trigger_registration($sale_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale'; // Verify table name from schema was policy_sale
        
        $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $sale_id));
        if (!$sale) return;

        // Get the insurer for this policy
        $insurer_id = get_post_meta($sale->policy_id, '_policy_insurer', true);
        if (!$insurer_id) {
            $this->log_failure($sale_id, 'No insurer linked to this policy.');
            return;
        }

        $insurer = get_post($insurer_id);
        if (!$insurer) {
            $this->log_failure($sale_id, 'Linked insurer profile does not exist.');
            return;
        }

        // Determine which adapter to use based on insurer slug
        $slug = $insurer->post_name;
        $adapter = $this->load_adapter($slug);

        if (!$adapter) {
            // No API adapter found? Fallback to manual processing.
            $wpdb->update($table, ['workflow_status' => 'pending_review'], ['id' => $sale_id]);
            return;
        }

        // Execute registration
        $result = $adapter->register_traveler((array)$sale);

        if (is_wp_error($result)) {
            $this->log_failure($sale_id, $result->get_error_message());
        } else {
            // Success! Update sale with insurer policy number
            $wpdb->update($table, [
                'policy_number'  => $result['policy_number'] ?? $sale->policy_number,
                'workflow_status' => 'active'
            ], ['id' => $sale_id]);
            
            do_action('maljani_insurer_sync_success', $sale_id, $result);
        }
    }

    /**
     * Dynamically load the correct insurer adapter file.
     */
    private function load_adapter($slug) {
        // First check for a specific adapter file
        $file_name = 'class-insurer-' . str_replace('_', '-', $slug) . '.php';
        $path = plugin_dir_path(__FILE__) . 'adapters/' . $file_name;

        // Sandbox Fallback for testing
        if (!file_exists($path)) {
            $path = plugin_dir_path(__FILE__) . 'adapters/class-insurer-sandbox.php';
        }

        if (file_exists($path)) {
            require_once plugin_dir_path(__FILE__) . 'interface-maljani-insurer-adapter.php';
            require_once $path;
            
            // Class naming convention: Maljani_Insurer_Adapter_Slug
            // We'll try to find the class. If not found, we fallback to Sandbox.
            $class_name = 'Maljani_Insurer_Adapter_' . str_replace(' ', '_', ucwords(str_replace(['-', '_'], ' ', $slug)));
            
            if (!class_exists($class_name)) {
                $class_name = 'Maljani_Insurer_Adapter_Sandbox';
            }

            if (class_exists($class_name)) {
                return new $class_name();
            }
        }

        return null;
    }

    private function log_failure($sale_id, $message) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        
        // Log to audit trail or comments? For now, update status.
        $wpdb->update($table, ['workflow_status' => 'pending_review'], ['id' => $sale_id]);
        
        // Error logging (simplified)
        error_log("Maljani Insurer Sync Failure (Sale ID: $sale_id): $message");
    }
}
