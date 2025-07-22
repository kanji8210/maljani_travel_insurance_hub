<?php
// filepath: c:\xampp\htdocs\wordpress\wp-content\plugins\maljani_travel_insurance_hub\includes\class-maljani-insured-reg.php

class Maljani_Insured_Registration {
    public function __construct() {
        add_shortcode('maljani_insured_register', [$this, 'render_registration_form']);
        add_action('init', [$this, 'handle_registration']);
    }

    public function render_registration_form() {
        ob_start();
        ?>
        <form method="post" class="maljani-register-form" autocomplete="off" id="maljani-register-form">
            <label>
                <input type="radio" name="account_type" value="insured" checked>
                Register as Client/Insured
            </label>
            <label>
                <input type="radio" name="account_type" value="agent">
                Register as Insurance Agent
            </label>
            <div id="insured-fields">
                <input type="text" name="insured_names" placeholder="Full Name" required>
                <input type="email" name="insured_email" placeholder="Email" required>
                <input type="text" name="insured_phone" placeholder="Phone" required>
                <input type="date" name="insured_dob" placeholder="Date of Birth" required>
                <label style="display:flex;align-items:center;font-size:0.98em;margin:10px 0;">
                    <input type="checkbox" name="insured_terms" required style="margin-right:8px;">
                    I agree to the <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank">terms and conditions</a>
                </label>
            </div>
            <div id="agent-fields" style="display:none;">
                <input type="email" name="agent_email" placeholder="Email" required>
                <input type="text" name="agent_phone" placeholder="Kenyan Phone (0XXXXXXXXX)" pattern="^0\d{9}$" required>
                <input type="text" name="agent_names" placeholder="Legal Names" required>
                <input type="date" name="agent_dob" placeholder="Date of Birth" required>
                <input type="text" name="agent_regno" placeholder="Registration Number" required>
                <label style="display:flex;align-items:center;font-size:0.98em;margin:10px 0;">
                    <input type="checkbox" name="agent_terms" required style="margin-right:8px;">
                    I agree to the <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank">terms and conditions</a>
                </label>
            </div>
            <button type="submit" name="maljani_register_user">Register</button>
            <div style="margin-top:12px;">
                <a href="<?php echo esc_url( wp_login_url() ); ?>">Already have an account? Log in</a>
            </div>
        </form>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const insuredFields = document.getElementById('insured-fields');
            const agentFields = document.getElementById('agent-fields');
            document.querySelectorAll('input[name="account_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'insured') {
                        insuredFields.style.display = '';
                        agentFields.style.display = 'none';
                    } else {
                        insuredFields.style.display = 'none';
                        agentFields.style.display = '';
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_registration() {
        if (isset($_POST['maljani_register_user'])) {
            if ($_POST['account_type'] === 'insured') {
                $user_id = wp_create_user(
                    sanitize_user($_POST['insured_email']),
                    wp_generate_password(),
                    sanitize_email($_POST['insured_email'])
                );
                if (!is_wp_error($user_id)) {
                    wp_update_user(['ID' => $user_id, 'role' => 'insured']);
                    update_user_meta($user_id, 'full_name', sanitize_text_field($_POST['insured_names']));
                    update_user_meta($user_id, 'phone', sanitize_text_field($_POST['insured_phone']));
                    update_user_meta($user_id, 'dob', sanitize_text_field($_POST['insured_dob']));
                    // Message ou redirection ici
                }
            } elseif ($_POST['account_type'] === 'agent') {
                $user_id = wp_create_user(
                    sanitize_user($_POST['agent_email']),
                    wp_generate_password(),
                    sanitize_email($_POST['agent_email'])
                );
                if (!is_wp_error($user_id)) {
                    wp_update_user(['ID' => $user_id, 'role' => 'insurer']);
                    update_user_meta($user_id, 'full_name', sanitize_text_field($_POST['agent_names']));
                    update_user_meta($user_id, 'phone', sanitize_text_field($_POST['agent_phone']));
                    update_user_meta($user_id, 'dob', sanitize_text_field($_POST['agent_dob']));
                    update_user_meta($user_id, 'regno', sanitize_text_field($_POST['agent_regno']));
                    // Message ou redirection ici
                }
            }
        }
    }
}
new Maljani_Insured_Registration();