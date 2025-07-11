<?php
// filepath: c:\xampp\htdocs\wordpress\wp-content\plugins\maljani_travel_insurance_hub\includes\class-maljani-agent-reg.php

class Maljani_Agent_Registration {
    public function __construct() {
        add_shortcode('maljani_agent_register', [$this, 'render_registration_form']);
        add_action('init', [$this, 'handle_registration']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_style']);
    }

    public function enqueue_style() {
        wp_enqueue_style(
            'maljani-register-style',
            plugin_dir_url(__FILE__) . 'css/register.css',
            [],
            null
        );
    }

    public function render_registration_form() {
        ob_start();
        ?>
        <div class="maljani-register-container">
            <h2>Register as Agent</h2>
            <p>Fill in the form below to register as an agent.</p>
        <form method="post" class="maljani-register-form" autocomplete="on">
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
        </form>
    </div>
        <?php
        return ob_get_clean();
    }

    public function handle_registration() {
        if (isset($_POST['maljani_register_agent'])) {
            // Validation basique
            if (
                empty($_POST['agent_email']) ||
                empty($_POST['agent_phone']) ||
                empty($_POST['agent_names']) ||
                empty($_POST['agent_dob']) ||
                empty($_POST['agent_regno']) ||
                empty($_POST['agent_terms'])
            ) {
                // Gérer l'erreur ici (ex: stocker dans une variable de session ou afficher via shortcode)
                return;
            }

            // Vérification du numéro au format 0XXXXXXXXX
            if (!preg_match('/^0\d{9}$/', $_POST['agent_phone'])) {
                // Gérer l'erreur ici (ex: numéro invalide)
                return;
            }

            $username = sanitize_user(str_replace(' ', '', strtolower($_POST['agent_names'])) . rand(100,999));
            $password = wp_generate_password(10);
            $user_id = wp_create_user(
                $username,
                $password,
                sanitize_email($_POST['agent_email'])
            );
            if (!is_wp_error($user_id)) {
                wp_update_user(['ID' => $user_id, 'role' => 'insurer']);
                update_user_meta($user_id, 'agent_phone', sanitize_text_field($_POST['agent_phone']));
                update_user_meta($user_id, 'agent_names', sanitize_text_field($_POST['agent_names']));
                update_user_meta($user_id, 'agent_dob', sanitize_text_field($_POST['agent_dob']));
                update_user_meta($user_id, 'agent_regno', sanitize_text_field($_POST['agent_regno']));
                // Message de succès ou redirection
            } else {
                // Gérer l'erreur ici (ex: email déjà utilisé)
            }
        }
    }
}
new Maljani_Agent_Registration();

$page_id = get_option('maljani_agent_page');
if ($page_id) {
    $url = get_permalink($page_id);
    // Utilise $url pour un lien ou une redirection
}