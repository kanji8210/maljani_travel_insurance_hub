<?php
// filepath: \includes\class-maljani-insured-reg.php

class Maljani_Insured_Registration {
    public function __construct() {
        add_shortcode('maljani_insured_register', [$this, 'render_registration_form']);
        add_action('init', [$this, 'handle_registration']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_style']);
    }

    public function render_registration_form() {
        if (is_user_logged_in()) {
            $dashboard_url = home_url('/dashboard');
            return '<div class="maljani-register-form" style="text-align:center;padding:32px 16px;">'
                . '<h3>You are already logged in.</h3>'
                . '<p><a href="' . esc_url($dashboard_url) . '" style="font-weight:bold;">Go to your dashboard</a></p>'
                . '</div>';
        }
        ob_start();
        ?>
        <form method="post" class="maljani-register-form" autocomplete="on" id="maljani-register-form">
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
            function setFieldsState(type) {
                insuredFields.querySelectorAll('input,select,textarea').forEach(el => {
                    el.disabled = (type !== 'insured');
                });
                agentFields.querySelectorAll('input,select,textarea').forEach(el => {
                    el.disabled = (type !== 'agent');
                });
            }
            document.querySelectorAll('input[name="account_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'insured') {
                        insuredFields.style.display = '';
                        agentFields.style.display = 'none';
                        setFieldsState('insured');
                    } else {
                        insuredFields.style.display = 'none';
                        agentFields.style.display = '';
                        setFieldsState('agent');
                    }
                });
            });
            // Initial state
            setFieldsState(document.querySelector('input[name="account_type"]:checked').value);
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_registration() {
        if (isset($_POST['maljani_register_user'])) {
            if ($_POST['account_type'] === 'insured') {
                // Validation stricte
                $required = ['insured_names','insured_email','insured_phone','insured_dob','insured_terms'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        wp_die('All fields are required. <a href="javascript:history.back()">Go back</a>');
                    }
                }
                if (!is_email($_POST['insured_email'])) {
                    wp_die('Invalid email address. <a href="javascript:history.back()">Go back</a>');
                }
                if (email_exists($_POST['insured_email'])) {
                    wp_die('This email is already registered. <a href="javascript:history.back()">Go back</a>');
                }
                $password = wp_generate_password();
                $user_id = wp_create_user(
                    sanitize_user($_POST['insured_email']),
                    $password,
                    sanitize_email($_POST['insured_email'])
                );
                if (!is_wp_error($user_id)) {
                    wp_update_user(['ID' => $user_id, 'role' => 'insured']);
                    update_user_meta($user_id, 'insured_names', sanitize_text_field($_POST['insured_names']));
                    update_user_meta($user_id, 'insured_phone', sanitize_text_field($_POST['insured_phone']));
                    update_user_meta($user_id, 'insured_dob', sanitize_text_field($_POST['insured_dob']));
                    update_user_meta($user_id, 'registration_date', current_time('mysql'));
                    update_user_meta($user_id, 'account_status', 'active');
                    // Envoyer le mot de passe par email
                    wp_mail(
                        sanitize_email($_POST['insured_email']),
                        'Your Account Details',
                        'Your account has been created.\nUsername: ' . sanitize_user($_POST['insured_email']) . '\nPassword: ' . $password . '\nLogin: ' . wp_login_url()
                    );
                    wp_redirect(home_url('/user-dashboard'));
                    exit;
                } else {
                    wp_die('Registration failed: ' . $user_id->get_error_message() . ' <a href="javascript:history.back()">Go back</a>');
                }
            }
        }
    }

    public function enqueue_style() {
        wp_enqueue_style(
            'maljani-register-user-style',
            plugin_dir_url(__FILE__) . 'css/register-user.css',
            array(),
            filemtime(__DIR__ . '/css/register-user.css')
        );
    }
}
new Maljani_Insured_Registration();