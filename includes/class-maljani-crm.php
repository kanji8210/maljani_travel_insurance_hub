<?php

class Maljani_CRM {

    public static function init() {
        $instance = new self();
        return $instance;
    }

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $namespace = 'maljani-crm/v1';

        // Clients
        register_rest_route($namespace, '/clients', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_clients'],
                'permission_callback' => [$this, 'check_agency_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_client'],
                'permission_callback' => [$this, 'check_agency_permission']
            ]
        ]);
        
        register_rest_route($namespace, '/clients/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_client'],
            'permission_callback' => [$this, 'check_agency_permission']
        ]);

        // Policies (Agency specific)
        register_rest_route($namespace, '/policies', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_policies'],
                'permission_callback' => [$this, 'check_agency_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_policy_draft'],
                'permission_callback' => [$this, 'check_agency_permission']
            ]
        ]);

        register_rest_route($namespace, '/policies/(?P<id>\d+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_policy'],
                'permission_callback' => [$this, 'check_agency_permission']
            ]
        ]);
        
        // Workflow transitions
        register_rest_route($namespace, '/policies/(?P<id>\d+)/transition', [
            'methods' => 'POST',
            'callback' => [$this, 'transition_policy'],
            'permission_callback' => [$this, 'check_any_permission'] // Handled internally
        ]);

        // Payments
        register_rest_route($namespace, '/payments', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_payments'],
                'permission_callback' => [$this, 'check_agency_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'record_payment'],
                'permission_callback' => [$this, 'check_agency_permission']
            ]
        ]);

        register_rest_route($namespace, '/commissions/(?P<id>\d+)/dispute', [
            'methods' => 'POST',
            'callback' => [$this, 'dispute_commission'],
            'permission_callback' => [$this, 'check_agency_permission']
        ]);

        register_rest_route($namespace, '/commissions/(?P<id>\d+)/mark-received', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_commission_received'],
            'permission_callback' => [$this, 'check_agency_permission']
        ]);
    }

    public function check_agency_permission() {
        return is_user_logged_in() && (current_user_can('maljani_agency_dashboard') || current_user_can('edit_maljani_policies')); 
    }

    public function check_any_permission() {
        return is_user_logged_in(); 
    }

    /**
     * Get the current agency ID for the logged in user
     */
    private function get_current_agency_id() {
        global $wpdb;
        $user_id = get_current_user_id();
        if (!$user_id) return null;
        
        // If editor/admin, they might not be an agency, allow them override
        if (current_user_can('edit_maljani_policies')) {
            return 'admin';
        }

        $table = $wpdb->prefix . 'maljani_agencies';
        return $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id = %d", $user_id));
    }

    // --- CLIENTS ---

    public function get_clients(WP_REST_Request $request) {
        global $wpdb;
        $agency_id = $this->get_current_agency_id();
        if (!$agency_id) return new WP_REST_Response(['success' => false, 'message' => 'Not an agency'], 403);

        $table = $wpdb->prefix . 'maljani_clients';
        
        // If admin, can see all or filter. Let's simplify for now
        $where = $agency_id === 'admin' ? "1=1" : $wpdb->prepare("agency_id = %d", $agency_id);
        
        $clients = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY id DESC");
        return new WP_REST_Response(['success' => true, 'clients' => $clients], 200);
    }

    public function create_client(WP_REST_Request $request) {
        global $wpdb;
        $agency_id = $this->get_current_agency_id();
        if ($agency_id === 'admin') {
            $agency_id = intval($request->get_param('agency_id')); // Admin specifies agency
        }
        if (!$agency_id) return new WP_REST_Response(['success' => false, 'message' => 'Agency required'], 403);

        $first_name = sanitize_text_field($request->get_param('first_name'));
        $last_name = sanitize_text_field($request->get_param('last_name'));
        
        if (empty($first_name) || empty($last_name)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Name required'], 400);
        }

        $table = $wpdb->prefix . 'maljani_clients';
        $wpdb->insert($table, [
            'agency_id' => $agency_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => sanitize_email($request->get_param('email')),
            'phone' => sanitize_text_field($request->get_param('phone')),
            'dob' => sanitize_text_field($request->get_param('dob')),
            'passport_number' => sanitize_text_field($request->get_param('passport_number')),
            'national_id' => sanitize_text_field($request->get_param('national_id')),
            'created_at' => current_time('mysql', 1),
            'updated_at' => current_time('mysql', 1)
        ]);

        $client_id = $wpdb->insert_id;
        
        if (class_exists('Maljani_Workflow')) {
            Maljani_Workflow::log_audit('client', $client_id, 'created', get_current_user_id(), ['agency_id' => $agency_id]);
        }

        return new WP_REST_Response(['success' => true, 'client_id' => $client_id], 200);
    }

    public function update_client(WP_REST_Request $request) {
        global $wpdb;
        $client_id = intval($request->get_param('id'));
        $table = $wpdb->prefix . 'maljani_clients';
        
        // Verify ownership
        $agency_id = $this->get_current_agency_id();
        if ($agency_id !== 'admin') {
            $owner = $wpdb->get_var($wpdb->prepare("SELECT agency_id FROM $table WHERE id = %d", $client_id));
            if ($owner != $agency_id) {
                return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        $update_data = ['updated_at' => current_time('mysql', 1)];
        $params = ['first_name', 'last_name', 'email', 'phone', 'dob', 'passport_number', 'national_id'];
        
        foreach ($params as $param) {
            if ($request->has_param($param)) {
                $update_data[$param] = sanitize_text_field($request->get_param($param));
            }
        }

        $wpdb->update($table, $update_data, ['id' => $client_id]);
        
        if (class_exists('Maljani_Workflow')) {
            Maljani_Workflow::log_audit('client', $client_id, 'updated', get_current_user_id(), $update_data);
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    // --- POLICIES ---

    public function get_policies(WP_REST_Request $request) {
        global $wpdb;
        $agency_id = $this->get_current_agency_id();
        if (!$agency_id) return new WP_REST_Response(['success' => false, 'message' => 'Not an agency'], 403);
        
        $table = $wpdb->prefix . 'policy_sale';
        $where = $agency_id === 'admin' ? "1=1" : $wpdb->prepare("agency_id = %d", $agency_id);

        // Fetch policies with client info
        $clients_table = $wpdb->prefix . 'maljani_clients';
        $policies = $wpdb->get_results("
            SELECT p.*, c.first_name, c.last_name 
            FROM $table p 
            LEFT JOIN $clients_table c ON p.client_id = c.id 
            WHERE p.$where 
            ORDER BY p.id DESC
        ");

        if (class_exists('Maljani_Invoice')) {
            foreach ($policies as $p) {
                $is_paid = $p->payment_status === 'confirmed';
                $p->doc_buttons = Maljani_Invoice::doc_buttons(intval($p->id), $is_paid, 'crm');
            }
        }

        return new WP_REST_Response(['success' => true, 'policies' => $policies], 200);
    }

    public function create_policy_draft(WP_REST_Request $request) {
        global $wpdb;
        $agency_id = $this->get_current_agency_id();
        if ($agency_id === 'admin') {
            $agency_id = intval($request->get_param('agency_id'));
        }
        if (!$agency_id) return new WP_REST_Response(['success' => false, 'message' => 'Agency required'], 403);

        $client_id = intval($request->get_param('client_id'));
        if (!$client_id) return new WP_REST_Response(['success' => false, 'message' => 'Client ID required'], 400);

        // Calculate commission
        $agencies_table = $wpdb->prefix . 'maljani_agencies';
        $commission_pct = $wpdb->get_var($wpdb->prepare("SELECT commission_percent FROM $agencies_table WHERE id = %d", $agency_id));
        $premium = floatval($request->get_param('premium'));
        $commission_amount = ($premium * floatval($commission_pct)) / 100;

        $table = $wpdb->prefix . 'policy_sale';
        $wpdb->insert($table, [
            'agency_id' => $agency_id,
            'client_id' => $client_id,
            'policy_id' => intval($request->get_param('policy_id')), // Base maljani policy ID
            'premium' => $premium,
            'commission_amount' => $commission_amount,
            'days' => intval($request->get_param('days')),
            'departure' => sanitize_text_field($request->get_param('departure')),
            'return' => sanitize_text_field($request->get_param('return')),
            'insured_names' => sanitize_text_field($request->get_param('insured_names')),
            'insured_email' => sanitize_email($request->get_param('insured_email')),
            'workflow_status' => 'draft',
            'created_at' => current_time('mysql', 1)
        ]);

        $sale_id = $wpdb->insert_id;
        
        if (class_exists('Maljani_Workflow')) {
            Maljani_Workflow::log_audit('policy', $sale_id, 'draft_created', get_current_user_id(), ['client_id' => $client_id]);
        }

        return new WP_REST_Response(['success' => true, 'sale_id' => $sale_id], 200);
    }

    public function update_policy(WP_REST_Request $request) {
        global $wpdb;
        $sale_id = intval($request->get_param('id'));
        $table = $wpdb->prefix . 'policy_sale';
        
        // Ownership check
        $agency_id = $this->get_current_agency_id();
        if ($agency_id !== 'admin') {
            $owner = $wpdb->get_var($wpdb->prepare("SELECT agency_id FROM $table WHERE id = %d", $sale_id));
            if ($owner != $agency_id) {
                return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        // Only allow updating if draft or pending (unless admin)
        if ($agency_id !== 'admin') {
            $status = $wpdb->get_var($wpdb->prepare("SELECT workflow_status FROM $table WHERE id = %d", $sale_id));
            if (!in_array($status, ['draft', 'pending_review'])) {
                return new WP_REST_Response(['success' => false, 'message' => 'Cannot edit policy in current state'], 403);
            }
        }

        $update_data = ['updated_at' => current_time('mysql', 1)];
        $params = ['premium', 'days', 'departure', 'return', 'insured_names', 'insured_email'];
        
        foreach ($params as $param) {
            if ($request->has_param($param)) {
                $update_data[$param] = sanitize_text_field($request->get_param($param));
            }
        }
        
        // Recalculate commission if premium changed
        if (isset($update_data['premium'])) {
            $owner = $wpdb->get_var($wpdb->prepare("SELECT agency_id FROM $table WHERE id = %d", $sale_id));
            $agencies_table = $wpdb->prefix . 'maljani_agencies';
            $commission_pct = $wpdb->get_var($wpdb->prepare("SELECT commission_percent FROM $agencies_table WHERE id = %d", $owner));
            $update_data['commission_amount'] = (floatval($update_data['premium']) * floatval($commission_pct)) / 100;
        }

        $wpdb->update($table, $update_data, ['id' => $sale_id]);
        
        if (class_exists('Maljani_Workflow')) {
            Maljani_Workflow::log_audit('policy', $sale_id, 'updated', get_current_user_id(), $update_data);
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    public function transition_policy(WP_REST_Request $request) {
        if (!class_exists('Maljani_Workflow')) {
            return new WP_REST_Response(['success' => false, 'message' => 'Workflow engine missing'], 500);
        }
        return Maljani_Workflow::handle_transition_request($request);
    }

    // --- PAYMENTS ---

    public function get_payments(WP_REST_Request $request) {
        global $wpdb;
        $agency_id = $this->get_current_agency_id();
        if (!$agency_id) return new WP_REST_Response(['success' => false, 'message' => 'Not an agency'], 403);

        // For agencies, "payments" tab should show their commissions from policy_sale
        $table = $wpdb->prefix . 'policy_sale';
        $where = $agency_id === 'admin' ? "1=1" : $wpdb->prepare("agency_id = %d", $agency_id);
        
        $commissions = $wpdb->get_results("SELECT id, policy_number, insured_names, premium, agent_commission_amount as amount, agent_commission_status as status, created_at FROM $table WHERE $where AND agent_commission_amount > 0 ORDER BY id DESC");
        return new WP_REST_Response(['success' => true, 'payments' => $commissions], 200);
    }
    
    public function record_payment(WP_REST_Request $request) {
        global $wpdb;
        $agency_id = $this->get_current_agency_id();
        if (!$agency_id || $agency_id === 'admin') {
             // Admin shouldn't record agency payments directly this way, they approve them
             return new WP_REST_Response(['success' => false, 'message' => 'Invalid permissions'], 403);
        }

        $policy_id = intval($request->get_param('policy_id'));
        $amount = floatval($request->get_param('amount'));
        $reference = sanitize_text_field($request->get_param('reference'));

        if (!$policy_id || !$amount) {
            return new WP_REST_Response(['success' => false, 'message' => 'Policy ID and amount required'], 400);
        }

        $table = $wpdb->prefix . 'maljani_payments';
        $wpdb->insert($table, [
            'agency_id' => $agency_id,
            'policy_id' => $policy_id,
            'amount' => $amount,
            'reference' => $reference,
            'type' => 'agency_to_maljani',
            'status' => 'pending',
            'created_at' => current_time('mysql', 1)
        ]);
        
        $payment_id = $wpdb->insert_id;
        
        if (class_exists('Maljani_Workflow')) {
            Maljani_Workflow::log_audit('payment', $payment_id, 'reference_submitted', get_current_user_id(), ['reference' => $reference, 'amount' => $amount]);
        }

    }

    public function dispute_commission(WP_REST_Request $request) {
        global $wpdb;
        $sale_id = intval($request->get_param('id'));
        $table = $wpdb->prefix . 'policy_sale';

        $agency_id = $this->get_current_agency_id();
        if (!$agency_id) return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 403);

        if ($agency_id !== 'admin') {
            $owner = $wpdb->get_var($wpdb->prepare("SELECT agency_id FROM $table WHERE id = %d", $sale_id));
            if ($owner != $agency_id) return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $wpdb->update($table, [
            'agent_commission_status'    => 'disputed',
            'agency_comm_disputed_note'  => sanitize_textarea_field($request->get_param('reason')),
        ], ['id' => $sale_id]);

        if (class_exists('Maljani_Workflow')) {
            Maljani_Workflow::log_audit('policy', $sale_id, 'commission_disputed', get_current_user_id(), ['reason' => sanitize_text_field($request->get_param('reason'))]);
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    public function mark_commission_received(WP_REST_Request $request) {
        global $wpdb;
        $sale_id = intval($request->get_param('id'));
        $table   = $wpdb->prefix . 'policy_sale';

        $agency_id = $this->get_current_agency_id();
        if (!$agency_id) return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 403);

        // Verify this sale belongs to the agent's agency
        if ($agency_id !== 'admin') {
            $owner = $wpdb->get_var($wpdb->prepare("SELECT agency_id FROM $table WHERE id = %d", $sale_id));
            if ($owner != $agency_id) return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $current_status = $wpdb->get_var($wpdb->prepare("SELECT agent_commission_status FROM $table WHERE id = %d", $sale_id));
        if ($current_status !== 'paid') {
            return new WP_REST_Response(['success' => false, 'message' => 'Commission must be in paid status to mark as received'], 400);
        }

        $wpdb->update($table, ['agent_commission_status' => 'received'], ['id' => $sale_id]);

        return new WP_REST_Response(['success' => true], 200);
    }
}

if (defined('ABSPATH')) {
    Maljani_CRM::init();
}
