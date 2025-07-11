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
        <form method="post" class="maljani-register-form" autocomplete="off">
            <input type="email" name="agent_email" placeholder="Email" required>
            <input type="text" name="agent_phone" placeholder="Kenyan Phone (0XXXXXXXXX)" pattern="^0\d{9}$" required>
            <input type="text" name="agent_names" placeholder="Legal Names" required>
            <input type="date" name="agent_dob" placeholder="Date of Birth" required>
            <input type="text" name="agent_regno" placeholder="Registration Number" required>
            <label style="display:flex;align-items:center;font-size:0.98em;margin:10px 0;">
                <input type="checkbox" name="agent_terms" required style="margin-right:8px;">
                I agree to the <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank">terms and conditions</a>
            </label>
            <button type="submit" name="maljani_register_agent">Register as Agent</button>
            <div style="margin-top:12px;">
                <a href="<?php echo esc_url( wp_login_url() ); ?>">Already have an account? Log in</a>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_registration() {
        if (isset($_POST['maljani_register_insured'])) {
            $user_id = wp_create_user(
                sanitize_user($_POST['user_login']),
                $_POST['user_pass'],
                sanitize_email($_POST['user_email'])
            );
            if (!is_wp_error($user_id)) {
                wp_update_user(['ID' => $user_id, 'role' => 'insured']);
                // Redirection ou message de succès ici
            }
        }
    }
}
new Maljani_Insured_Registration();