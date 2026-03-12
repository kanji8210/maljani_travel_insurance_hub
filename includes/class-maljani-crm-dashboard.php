<?php

class Maljani_CRM_Dashboard {

    public static function init() {
        return new self();
    }

    public function __construct() {
        add_shortcode('maljani_crm_dashboard', [$this, 'render_dashboard']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'maljani_crm_dashboard')) {
            wp_enqueue_style('maljani-crm-dashboard', plugin_dir_url(__FILE__) . 'css/maljani-crm-dashboard.css', [], time());
            wp_enqueue_script('maljani-crm-dashboard', plugin_dir_url(__FILE__) . 'js/maljani-crm-dashboard.js', ['jquery'], time(), true);
            wp_localize_script('maljani-crm-dashboard', 'maljaniCrmParams', [
                'rest_url' => esc_url_raw(rest_url('maljani-crm/v1')),
                'nonce' => wp_create_nonce('wp_rest')
            ]);
        }
    }

    public function render_dashboard() {
        if (!is_user_logged_in()) {
            return '<div class="maljani-crm-msg">Please log in to access the CRM dashboard.</div>';
        }

        if (!current_user_can('read')) { // Basic check, ideally checking for 'agent' role
            return '<div class="maljani-crm-msg">You do not have permission to access the CRM.</div>';
        }

        // We fetch basic info directly for initial render to avoid too many loading spinners
        global $wpdb;
        // Multi-tier agency lookup — handles all legacy and new associations
        $uid = get_current_user_id();
        // 1. By user_id column (new agencies admin creates this)
        $agency_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}maljani_agencies WHERE user_id = %d LIMIT 1",
            $uid
        ));
        // 2. Fallback: user meta 'agency_id' (may be set on agent registration)
        if (!$agency_id) {
            $meta_agency_id = get_user_meta($uid, 'agency_id', true);
            if ($meta_agency_id) {
                $agency_id = intval($meta_agency_id);
            }
        }
        // 3. Fallback: user meta 'maljani_agency_id'
        if (!$agency_id) {
            $meta_agency_id = get_user_meta($uid, 'maljani_agency_id', true);
            if ($meta_agency_id) {
                $agency_id = intval($meta_agency_id);
            }
        }
        // 4. Fallback: agent's user login matches agency name (loose match for old data)
        if (!$agency_id) {
            $user = get_userdata($uid);
            if ($user) {
                $agency_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}maljani_agencies WHERE name = %s OR agency_name = %s LIMIT 1",
                    $user->display_name, $user->display_name
                ));
            }
        }

        if (!$agency_id) {
             return '<div class="maljani-crm-msg">Your account is not linked to an agency profile. Please contact your Maljani administrator.</div>';
        }

        $agency = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}maljani_agencies WHERE id = %d", $agency_id));
        $stats = $this->get_agency_stats($agency_id);

        ob_start();
        ?>
        <div class="maljani-crm-dashboard" id="maljani-crm-app">
            <header class="crm-header">
                <div class="crm-header-info">
                    <h2>Agency Portal</h2>
                    <p class="agency-name">🏢 <?php echo esc_html($agency->name); ?> (<?php echo esc_html($agency->commission_rate); ?>% Comms)</p>
                </div>
                <nav class="crm-tabs">
                    <button class="crm-tab active" data-target="clients">Clients</button>
                    <button class="crm-tab" data-target="policies">Policies</button>
                    <button class="crm-tab" data-target="payments">Commissions</button>
                </nav>
            </header>

            <div class="crm-stats-grid">
                <div class="crm-stat-card">
                    <span class="stat-icon">💰</span>
                    <div class="stat-data">
                        <span class="stat-label">Total Commission</span>
                        <span class="stat-value">$<?php echo number_format($stats['total_commission'], 2); ?></span>
                    </div>
                </div>
                <div class="crm-stat-card">
                    <span class="stat-icon">📄</span>
                    <div class="stat-data">
                        <span class="stat-label">Active Policies</span>
                        <span class="stat-value"><?php echo $stats['active_count']; ?></span>
                    </div>
                </div>
                <div class="crm-stat-card">
                    <span class="stat-icon">⏳</span>
                    <div class="stat-data">
                        <span class="stat-label">Pending Review</span>
                        <span class="stat-value"><?php echo $stats['pending_count']; ?></span>
                    </div>
                </div>
            </div>
            
            <main class="crm-content">
                <!-- CLIENTS VIEW -->
                <section id="crm-clients" class="crm-section active">
                    <div class="crm-toolbar">
                        <h3>Your Clients</h3>
                        <button class="crm-btn crm-btn-primary" onclick="showModal('crm-add-client-modal')">+ Add New Client</button>
                    </div>
                    <div id="crm-clients-list" class="crm-list">Loading...</div>
                </section>

                <!-- POLICIES VIEW -->
                <section id="crm-policies" class="crm-section">
                    <div class="crm-toolbar">
                        <h3>Policy Workflow</h3>
                        <button class="crm-btn crm-btn-primary" onclick="showModal('crm-create-policy-modal')">+ Create Policy Draft</button>
                    </div>
                    <div id="crm-policies-list" class="crm-list">Loading...</div>
                </section>

                <!-- PAYMENTS/COMMISSIONS VIEW -->
                <section id="crm-payments" class="crm-section">
                    <div class="crm-toolbar">
                        <h3>Commission Ledger</h3>
                        <p>Track your earnings for all confirmed policies.</p>
                    </div>
                    <div id="crm-commissions-list" class="crm-list">Loading...</div>
                </section>
            </main>

            <!-- MODALS -->
            <div id="crm-add-client-modal" class="crm-modal">
                <div class="crm-modal-content">
                    <h3>Add New Client</h3>
                    <form id="crm-add-client-form">
                        <div class="crm-form-group">
                            <label>First Name</label><input type="text" name="first_name" required>
                        </div>
                        <div class="crm-form-group">
                            <label>Last Name</label><input type="text" name="last_name" required>
                        </div>
                        <div class="crm-form-group">
                            <label>Email</label><input type="email" name="email">
                        </div>
                        <div class="crm-form-group">
                            <label>Passport</label><input type="text" name="passport_number">
                        </div>
                        <div class="crm-form-actions">
                            <button type="button" class="crm-btn" onclick="hideAllModals()">Cancel</button>
                            <button type="submit" class="crm-btn crm-btn-primary">Save Client</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="crm-create-policy-modal" class="crm-modal">
                <div class="crm-modal-content">
                    <h3>Create Policy Draft</h3>
                    <form id="crm-create-policy-form">
                        <div class="crm-form-group">
                            <label>Select Client</label>
                            <select name="client_id" id="crm-client-select" required></select>
                        </div>
                        <div class="crm-form-group">
                            <label>Insurance Product</label>
                            <?php
                            $products = get_posts(['post_type' => 'maljani_policy', 'posts_per_page' => -1]);
                            echo '<select name="policy_id" required>';
                            foreach($products as $prod) echo "<option value='{$prod->ID}'>" . esc_html($prod->post_title) . "</option>";
                            echo '</select>';
                            ?>
                        </div>
                        <div class="crm-form-group">
                            <label>Premium ($)</label><input type="number" step="0.01" name="premium" required>
                        </div>
                        <div class="crm-form-group">
                            <label>Duration (Days)</label><input type="number" name="days" value="7" required>
                        </div>
                        <div class="crm-form-group">
                            <label>Insured Party Name</label><input type="text" name="insured_names" required>
                        </div>
                        <div class="crm-form-actions">
                            <button type="button" class="crm-btn" onclick="hideAllModals()">Cancel</button>
                            <button type="submit" class="crm-btn crm-btn-primary">Save Draft</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- More modals map to actions -->
        </div>

        <script>
            function showModal(id) { document.getElementById(id).classList.add('active'); }
        </script>
        <?php
        if (class_exists('Maljani_Invoice')) {
            Maljani_Invoice::print_email_js();
        }
    }

    private function get_agency_stats($agency_id) {
        global $wpdb;
        $agency = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}maljani_agencies WHERE id = %d", $agency_id));
        $comm_rate = $agency ? floatval($agency->commission_rate) / 100 : 0;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT workflow_status, SUM(premium) as total_premium, COUNT(*) as count 
             FROM {$wpdb->prefix}policy_sale 
             WHERE agency_id = %d 
             GROUP BY workflow_status",
            $agency_id
        ));

        $stats = [
            'total_commission' => 0,
            'active_count' => 0,
            'pending_count' => 0
        ];

        foreach ($results as $row) {
            if ($row->workflow_status === 'active') {
                $stats['active_count'] = $row->count;
                $stats['total_commission'] = $row->total_premium * $comm_rate;
            } elseif ($row->workflow_status === 'pending_review') {
                $stats['pending_count'] = $row->count;
            }
        }

        return $stats;
    }
}


if (defined('ABSPATH')) { Maljani_CRM_Dashboard::init(); }
