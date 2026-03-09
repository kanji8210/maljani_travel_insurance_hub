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
        $agency_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}maljani_agencies WHERE user_id = %d", get_current_user_id()));
        
        if (!$agency_id) {
             return '<div class="maljani-crm-msg">Your account is not linked to an agency profile. Please contact support.</div>';
        }

        ob_start();
        ?>
        <div class="maljani-crm-dashboard" id="maljani-crm-app">
            <header class="crm-header">
                <h2>Agency Portal</h2>
                <nav class="crm-tabs">
                    <button class="crm-tab active" data-target="clients">Clients</button>
                    <button class="crm-tab" data-target="policies">Policies Workflow</button>
                    <button class="crm-tab" data-target="payments">Payments</button>
                </nav>
            </header>
            
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
                        <h3>Policy Drafts & Workflow</h3>
                        <button class="crm-btn crm-btn-primary" onclick="showModal('crm-create-policy-modal')">+ Create Policy Draft</button>
                    </div>
                    <div id="crm-policies-list" class="crm-list">Loading...</div>
                </section>

                <!-- PAYMENTS VIEW -->
                <section id="crm-payments" class="crm-section">
                    <div class="crm-toolbar">
                        <h3>Payment References</h3>
                        <button class="crm-btn crm-btn-primary" onclick="showModal('crm-add-payment-modal')">Submit Payment Ref</button>
                    </div>
                    <div id="crm-payments-list" class="crm-list">Loading...</div>
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
            function hideAllModals() { document.querySelectorAll('.crm-modal').forEach(m => m.classList.remove('active')); }
        </script>
        <?php
        return ob_get_clean();
    }
}

if (defined('ABSPATH')) { Maljani_CRM_Dashboard::init(); }
