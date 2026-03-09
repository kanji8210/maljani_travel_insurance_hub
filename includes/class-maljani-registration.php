<?php
/**
 * Maljani Unified Registration Portal
 * Handles self-registration for both Clients (Insured) and Agencies (Agents).
 */

class Maljani_Registration {

    public static function init() {
        return new self();
    }

    public function __construct() {
        add_shortcode('maljani_registration_portal', [$this, 'render_portal']);
        add_action('init', [$this, 'handle_registration_submission']);
    }

    public function render_portal() {
        if (is_user_logged_in()) {
            return '<div class="mj-reg-notice">You are already logged in. <a href="' . home_url('/dashboard') . '">Go to Dashboard</a></div>';
        }

        ob_start();
        ?>
        <div class="maljani-registration-portal">
            <div class="registration-card glass-morphism">
                <div class="registration-header">
                    <h2>Join Maljani Travel Hub</h2>
                    <p>Select your account type to continue</p>
                </div>

                <div class="role-selector">
                    <div class="role-option active" data-role="insured">
                        <span class="role-icon">🛡️</span>
                        <div class="role-desc">
                            <strong>Traveler / Client</strong>
                            <span>Protect your trip today</span>
                        </div>
                    </div>
                    <div class="role-option" data-role="agent">
                        <span class="role-icon">🏢</span>
                        <div class="role-desc">
                            <strong>Travel Agency / Partner</strong>
                            <span>Manage policies for your clients</span>
                        </div>
                    </div>
                </div>

                <form id="mj-unified-reg-form" method="POST" action="">
                    <?php wp_nonce_field('maljani_registration', 'mj_reg_nonce'); ?>
                    <input type="hidden" name="account_type" id="mj-selected-role" value="insured">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Legal Name</label>
                            <input type="text" name="full_name" required placeholder="John Doe">
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="user_email" required placeholder="john@example.com">
                        </div>
                        <div class="form-group" id="agency-name-group" style="display:none;">
                            <label>Agency Name</label>
                            <input type="text" name="agency_name" placeholder="ABC Travels Ltd">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" required placeholder="+254...">
                        </div>
                        <div class="form-group">
                            <label>Create Password</label>
                            <input type="password" name="user_pass" required>
                        </div>
                    </div>

                    <div class="form-footer">
                        <label class="terms-label">
                            <input type="checkbox" required> I agree to the <a href="#">Terms of Service</a>
                        </label>
                        <button type="submit" name="mj_submit_reg" class="mj-btn-primary">Create Account</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.role-option').click(function() {
                $('.role-option').removeClass('active');
                $(this).addClass('active');
                var role = $(this).data('role');
                $('#mj-selected-role').val(role);
                
                if (role === 'agent') {
                    $('#agency-name-group').slideDown();
                } else {
                    $('#agency-name-group').slideUp();
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_registration_submission() {
        if (!isset($_POST['mj_submit_reg']) || !isset($_POST['mj_reg_nonce'])) return;
        if (!wp_verify_nonce($_POST['mj_reg_nonce'], 'maljani_registration')) return;

        $email = sanitize_email($_POST['user_email']);
        $pass = $_POST['user_pass'];
        $name = sanitize_text_field($_POST['full_name']);
        $role = sanitize_text_field($_POST['account_type']);
        
        if (email_exists($email)) {
             wp_die("Email already exists. <a href='javascript:history.back()'>Go back</a>");
        }

        $user_id = wp_create_user($email, $pass, $email);
        if (is_wp_error($user_id)) {
            wp_die($user_id->get_error_message());
        }

        wp_update_user([
            'ID' => $user_id,
            'first_name' => $name,
            'role' => $role === 'agent' ? 'agent' : 'insured'
        ]);

        if ($role === 'agent') {
            // Create agency profile
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'maljani_agencies', [
                'name' => sanitize_text_field($_POST['agency_name'] ?: $name . " Agency"),
                'user_id' => $user_id,
                'commission_rate' => 10.00,
                'status' => 'pending'
            ]);
        }

        // Auto login and redirect
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        wp_redirect(home_url('/dashboard'));
        exit;
    }
}
 Maljani_Registration::init();
