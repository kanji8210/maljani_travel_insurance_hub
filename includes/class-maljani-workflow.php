<?php

class Maljani_Workflow {

    /**
     * Map of valid transitions and required capabilities
     */
    private static $transitions = [
        'draft' => [
            'pending_review' => ['role' => 'agency'] // Agency submits to Maljani
        ],
        'pending_review' => [
            'draft' => ['role' => 'maljani_editor'], // Editor or Admin rejects back to agency
            'submitted_to_insurer' => ['role' => 'maljani_editor'] // Editor or Admin forwards to insurer
        ],
        'submitted_to_insurer' => [
            'pending_review' => ['role' => 'insurer'], // Insurer asks for clarification
            'approved' => ['role' => 'insurer'] // Insurer approves
        ],
        'approved' => [
            'active' => ['role' => 'maljani_admin'] // Only Admins activate with final docs
        ],
        'active' => [
            'verification_ready' => ['role' => 'maljani_admin'] // Admin generates verification slips
        ]
    ];

    public static function log_audit($entity_type, $entity_id, $action_name, $user_id = null, $details = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'maljani_audit_trail';
        
        $wpdb->insert($table, [
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'action_name' => $action_name,
            'performed_by' => $user_id,
            'details' => json_encode($details),
            'created_at' => current_time('mysql', 1)
        ]);
    }

    public static function handle_transition_request(WP_REST_Request $request) {
        global $wpdb;
        $sale_id = intval($request->get_param('id'));
        $target_status = sanitize_text_field($request->get_param('target_status'));
        $notes = sanitize_textarea_field($request->get_param('notes'));
        
        $table = $wpdb->prefix . 'policy_sale';
        $policy = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $sale_id));
        
        if (!$policy) {
            return new WP_REST_Response(['success' => false, 'message' => 'Policy not found'], 404);
        }
        
        $current_status = $policy->workflow_status;
        
        // Ensure transition is valid
        if (!isset(self::$transitions[$current_status]) || !isset(self::$transitions[$current_status][$target_status])) {
            return new WP_REST_Response(['success' => false, 'message' => "Invalid transition from {$current_status} to {$target_status}"], 400);
        }
        
        // Authorization check
        $required_role = self::$transitions[$current_status][$target_status]['role'];
        if (!self::user_has_role_for_transition($required_role, $policy)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized for this transition'], 403);
        }

        // Perform transition
        $wpdb->update($table, ['workflow_status' => $target_status, 'updated_at' => current_time('mysql', 1)], ['id' => $sale_id]);
        
        // Update regular policy_status for backward compatibility
        $mapped_base_status = self::map_workflow_to_base_status($target_status);
        if ($mapped_base_status) {
            $wpdb->update($table, ['policy_status' => $mapped_base_status], ['id' => $sale_id]);
        }
        
        self::log_audit('policy', $sale_id, "status_change_{$current_status}_to_{$target_status}", get_current_user_id(), ['notes' => $notes]);
        
        // Fire hooks so Notification system can pick it up
        do_action('maljani_workflow_transition', $sale_id, $current_status, $target_status, $policy, $notes);

        return new WP_REST_Response(['success' => true, 'new_status' => $target_status], 200);
    }

    private static function user_has_role_for_transition($role, $policy) {
        if (!is_user_logged_in()) return false;
        
        if ($role === 'maljani_admin' && (current_user_can('activate_maljani_policies') || current_user_can('manage_options'))) return true;
        if ($role === 'maljani_editor' && (current_user_can('edit_maljani_policies') || current_user_can('manage_options'))) return true;
        
        if ($role === 'agency') {
            global $wpdb;
            $agencies_table = $wpdb->prefix . 'maljani_agencies';
            $agency_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $agencies_table WHERE user_id = %d", get_current_user_id()));
            return ($agency_id && $agency_id == $policy->agency_id);
        }
        
        if ($role === 'insurer' && current_user_can('insurer')) { // Assumes custom role 'insurer' exists
            // Add extra logic if an insurer should only approve specific policies
            return true;
        }
        
        return false;
    }
    
    private static function map_workflow_to_base_status($workflow_status) {
        $map = [
            'draft' => 'unconfirmed',
            'pending_review' => 'unconfirmed',
            'submitted_to_insurer' => 'pending',
            'approved' => 'approved',
            'active' => 'active',
            'verification_ready' => 'active'
        ];
        return isset($map[$workflow_status]) ? $map[$workflow_status] : null;
    }
}
