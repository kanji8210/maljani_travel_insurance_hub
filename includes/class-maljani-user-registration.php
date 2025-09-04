<?php
// filepath:\plugins\maljani_travel_insurance_hub\includes\class-maljani-agent-registration.php

class Maljani_User_Registration {
    public function __construct() {
        add_shortcode('maljani_agent_register', [$this, 'render_registration_form']);
        add_action('init', [$this, 'handle_registration']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_style']);
    }
        public function enqueue_style() {
        wp_enqueue_style(
            'maljani-user-registration-style',
            plugin_dir_url(__FILE__) . 'css/user-registration.css',
            array(),
            filemtime(__DIR__ . '/css/user-registration.css')
        );
    }

    public function render_registration_form() {
        // Get isolation manager
        $isolation = Maljani_Style_Isolation::instance();
        
        // Check if there's a success message
        if (isset($_GET['registration']) && $_GET['registration'] === 'success') {
            $success_message = $isolation->get_isolated_notice(
                'Registration successful! Check your email for login credentials (agents) or account has been created (insured). You will be redirected to your dashboard shortly.',
                'success'
            );
        } else {
            $success_message = '';
        }
        
        // Check for error messages
        $error_message = '';
        if (isset($_GET['error'])) {
            $error_text = '';
            switch ($_GET['error']) {
                case 'email_exists':
                    $error_text = 'Email already exists. Please use a different email.';
                    break;
                case 'phone_exists':
                    $error_text = 'Phone number already registered. Please use a different phone number.';
                    break;
                case 'invalid_phone':
                    $error_text = 'Invalid phone number format. Please use Kenyan format: 0XXXXXXXXX';
                    break;
                case 'missing_fields':
                    $error_text = 'Please fill in all required fields.';
                    break;
                case 'registration_failed':
                    $error_text = 'Registration failed. Please try again.';
                    break;
                case 'invalid_email':
                    $error_text = 'Invalid email format. Please enter a valid email address.';
                    break;
                case 'terms_required':
                    $error_text = 'You must agree to the terms and conditions to register.';
                    break;
            }
            if ($error_text) {
                $error_message = $isolation->get_isolated_notice($error_text, 'error');
            }
        }

        ob_start();
        // Add critical CSS inline
        echo $isolation->get_inline_critical_styles();
        // === FORCE INLINE CSS, MAXIMUM SPECIFICITY ===
        echo '<style>\n.maljani-plugin-container .maljani-register-container { background: #eaf6f1 !important; border: 2px solid #17624a !important; max-width: 800px !important; margin: 40px auto !important; padding: 32px 20px !important; border-radius: 12px !important; box-shadow: 0 4px 24px rgba(24,49,83,0.08) !important; text-align: center !important; }\n.maljani-plugin-container .maljani-register-container h2 { color: #183153 !important; margin-bottom: 8px !important; font-size: 2em !important; font-weight: 700 !important; }\n.maljani-plugin-container .maljani-register-container p { color: #222b38 !important; margin-bottom: 18px !important; }\n.maljani-plugin-container .maljani-register-form { max-width: 400px !important; margin: 0 auto !important; background: #fff !important; border-radius: 10px !important; box-shadow: 0 1px 8px rgba(24,49,83,0.04) !important; padding: 28px 28px 18px 28px !important; display: flex !important; flex-direction: column !important; gap: 16px !important; border: 2px solid #183153 !important; }\n.maljani-plugin-container .maljani-register-form input[type="text"],\n.maljani-plugin-container .maljani-register-form input[type="email"],\n.maljani-plugin-container .maljani-register-form input[type="date"] { border: 2px solid #17624a !important; border-radius: 6px !important; padding: 10px 12px !important; font-size: 1em !important; background: #f7f9fa !important; color: #183153 !important; transition: border 0.2s, background 0.2s !important; }\n.maljani-plugin-container .maljani-register-form input:focus { border-color: #17624a !important; background: #eaf6f1 !important; outline: none !important; }\n.maljani-plugin-container .maljani-register-form label { font-size: 1em !important; color: #183153 !important; margin-bottom: 0 !important; font-weight: 500 !important; }\n.maljani-plugin-container .maljani-register-form input[type="radio"],\n.maljani-plugin-container .maljani-register-form input[type="checkbox"] { accent-color: #17624a !important; margin-right: 8px !important; }\n.maljani-plugin-container .maljani-register-form button[type="submit"] { background: #183153 !important; color: #fff !important; border: none !important; border-radius: 6px !important; padding: 12px 0 !important; font-size: 1.08em !important; font-weight: 600 !important; cursor: pointer !important; transition: background 0.2s, color 0.2s !important; margin-top: 8px !important; box-shadow: 0 2px 8px rgba(24,49,83,0.06) !important; }\n.maljani-plugin-container .maljani-register-form button[type="submit"]:hover { background: #17624a !important; color: #fff !important; }\n.maljani-plugin-container .maljani-register-form a { color: #183153 !important; text-decoration: underline !important; font-size: 0.97em !important; transition: color 0.2s !important; }\n.maljani-plugin-container .maljani-register-form a:hover { color: #17624a !important; }\n.maljani-plugin-container .maljani-success-message { background-color: #eaf6f1 !important; color: #17624a !important; border: 1px solid #bfc7d1 !important; border-radius: 6px !important; padding: 12px 16px !important; margin: 16px 0 !important; font-size: 0.98em !important; line-height: 1.5 !important; }\n.maljani-plugin-container .maljani-error-message { background-color: #fbeaea !important; color: #a12a2a !important; border: 1px solid #e3bcbc !important; border-radius: 6px !important; padding: 12px 16px !important; margin: 16px 0 !important; font-size: 0.98em !important; line-height: 1.5 !important; }\n@media (max-width: 600px) { .maljani-plugin-container .maljani-register-container { padding: 10px 2vw !important; } .maljani-plugin-container .maljani-register-form { padding: 16px 4vw 12px 4vw !important; } }\n</style>';
        
        echo $success_message;
        echo $error_message;
        ?>
        <div class="maljani-register-container">
            <h1>REGISTER</h1>
            <p>Fill in the form below to create your account.<br><strong>Note:</strong> All users are registered as <b>insured</b> by default. If you are an agent, the admin can upgrade your account plus privileges plus later.</p>
            <form method="post" class="maljani-register-form" autocomplete="off" id="maljani-register-form">
                <div id="insured-fields">
                    <input type="text" name="insured_names" placeholder="Legal Names" required>
                    <input type="text" name="insured_phone" placeholder="Kenyan Phone (0XXXXXXXXX)" pattern="^0\d{9}$" required>
                    <input type="date" name="insured_dob" placeholder="Date of Birth" required>
                    <input type="email" name="insured_email" placeholder="Email Address" required>
                    <input type="password" name="insured_password" placeholder="Choose a Password" required minlength="6">
                    <label style="display:flex;align-items:center;font-size:0.98em;margin:10px 0;">
                        <input type="checkbox" name="insured_terms" required style="margin-right:8px;">
                        I agree to the <a href="<?php echo esc_url(home_url('/terms')); ?>" target="_blank">terms and conditions</a>
                    </label>
                </div>
                <button type="submit" name="maljani_register_user">Register</button>
                <div style="margin-top:12px;">
                    <a href="<?php echo esc_url( wp_login_url() ); ?>">Already have an account? Log in</a>
                </div>
            </form>
            <?php if (isset($_GET['registration']) && $_GET['registration'] === 'success'): ?>
            <script>
                setTimeout(function() {
                    window.location.href = '<?php echo esc_url(get_permalink(get_option('maljani_user_dashboard_page'))); ?>';
                }, 3000);
            </script>
            <?php endif; ?>
        </div>
        <?php
        
        // Get the buffered content
        $form_content = ob_get_clean();
        
        // Wrap with isolation container
        return $isolation->get_isolated_form($form_content, 'registration');
    }

    public function handle_registration() {
        if (isset($_POST['maljani_register_user'])) {
            $this->process_insured_registration();
        }
    }

    private function process_insured_registration() {
        // Validation
        if (
            empty($_POST['insured_names']) ||
            empty($_POST['insured_phone']) ||
            empty($_POST['insured_dob']) ||
            empty($_POST['insured_email']) ||
            empty($_POST['insured_password'])
        ) {
            wp_redirect(add_query_arg('error', 'missing_fields', wp_get_referer()));
            exit;
        }
        if (empty($_POST['insured_terms'])) {
            wp_redirect(add_query_arg('error', 'terms_required', wp_get_referer()));
            exit;
        }

        // Phone validation
        if (!preg_match('/^0\d{9}$/', $_POST['insured_phone'])) {
            wp_redirect(add_query_arg('error', 'invalid_phone', wp_get_referer()));
            exit;
        }

        // Email validation
        if (!is_email($_POST['insured_email'])) {
            wp_redirect(add_query_arg('error', 'invalid_email', wp_get_referer()));
            exit;
        }

        // Check if phone number already exists
        $existing_user_phone = get_users(array(
            'meta_key' => 'insured_phone',
            'meta_value' => $_POST['insured_phone'],
            'number' => 1
        ));
        if (!empty($existing_user_phone)) {
            wp_redirect(add_query_arg('error', 'phone_exists', wp_get_referer()));
            exit;
        }

        // Check if email already exists
        if (email_exists($_POST['insured_email'])) {
            wp_redirect(add_query_arg('error', 'email_exists', wp_get_referer()));
            exit;
        }

        $username = sanitize_user(str_replace(' ', '', strtolower($_POST['insured_names'])) . rand(100,999));
        $password = $_POST['insured_password'];
        $user_id = wp_create_user($username, $password, sanitize_email($_POST['insured_email']));

        if (!is_wp_error($user_id)) {
            wp_update_user(['ID' => $user_id, 'role' => 'insured']);
            update_user_meta($user_id, 'insured_phone', sanitize_text_field($_POST['insured_phone']));
            update_user_meta($user_id, 'insured_names', sanitize_text_field($_POST['insured_names']));
            update_user_meta($user_id, 'insured_dob', sanitize_text_field($_POST['insured_dob']));
            update_user_meta($user_id, 'registration_date', current_time('mysql'));
            update_user_meta($user_id, 'account_status', 'active');
            // Notifier l'admin (optionnel)
            $this->send_insured_notification($user_id, $password);
            wp_redirect(add_query_arg('registration', 'success', wp_get_referer()));
            exit;
        } else {
            wp_redirect(add_query_arg('error', 'registration_failed', wp_get_referer()));
            exit;
        }
    }

    private function send_insured_notification($user_id, $password) {
        $user = get_userdata($user_id);
        $dashboard_url = get_permalink(get_option('maljani_user_dashboard_page'));
        $phone = get_user_meta($user_id, 'insured_phone', true);
        
        // Since insured users don't provide email, we'll log the details for admin notification
        $admin_email = get_option('admin_email');
        $subject = 'New Insured Registration - Login Details';
        
        $message = "
        <html>
        <body>
            <h3>New Insured User Registration</h3>
            <p>A new insured user has registered without email:</p>
            <div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h4>User Details:</h4>
                <p><strong>Name:</strong> " . get_user_meta($user_id, 'insured_names', true) . "</p>
                <p><strong>Phone:</strong> {$phone}</p>
                <p><strong>Username:</strong> {$user->user_login}</p>
                <p><strong>Password:</strong> {$password}</p>
                <p><strong>Dashboard URL:</strong> <a href='{$dashboard_url}'>{$dashboard_url}</a></p>
            </div>
            <p><strong>Note:</strong> Since no email was provided, please manually communicate the login details to the user via phone or other means.</p>
        </body>
        </html>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
    }


}
new Maljani_User_Registration();
