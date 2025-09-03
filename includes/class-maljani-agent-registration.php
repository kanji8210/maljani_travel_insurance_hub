<?php
// filepath: c:\xampp\htdocs\wordpress\wp-content\plugins\maljani_travel_insurance_hub\includes\class-maljani-agent-registration.php

class Maljani_Agent_Registration {
    public function __construct() {
        add_shortcode('maljani_agent_register', [$this, 'render_registration_form']);
        add_action('init', [$this, 'handle_registration']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_style']);
    }

    public function enqueue_style() {
         //Styles gérés par le système d'isolation - pas besoin de CSS externe
         wp_enqueue_style(
           'maljani-register-style',
          plugin_dir_url(__FILE__) . 'css/register.css',
          [],
             null
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
            }
            if ($error_text) {
                $error_message = $isolation->get_isolated_notice($error_text, 'error');
            }
        }

        ob_start();
        
        // Add critical CSS inline
        echo $isolation->get_inline_critical_styles();
        
        echo $success_message;
        echo $error_message;
        ?>
        <div class="maljani-register-container">
            <h2>User Registration</h2>
            <p>Choose your account type and fill in the form below.</p>
            
            <form method="post" class="maljani-register-form" autocomplete="off" id="maljani-register-form">
                <div style="margin-bottom: 20px;">
                    <label style="display:flex;align-items:center;font-size:1em;margin:8px 0;">
                        <input type="radio" name="account_type" value="agent" checked style="margin-right:8px;">
                        Register as Insurance Agent
                    </label>
                    <label style="display:flex;align-items:center;font-size:1em;margin:8px 0;">
                        <input type="radio" name="account_type" value="insured" style="margin-right:8px;">
                        Register as Client/Insured
                    </label>
                </div>
                
                <div id="agent-fields">
                    <input type="email" name="agent_email" placeholder="Email" required>
                    <input type="text" name="agent_phone" placeholder="Kenyan Phone (0XXXXXXXXX)" pattern="^0\d{9}$" required>
                    <input type="text" name="agent_names" placeholder="Legal Names" required>
                    <input type="date" name="agent_dob" placeholder="Date of Birth" required>
                    <input type="text" name="agent_ira_number" placeholder="IRA Number" required>
                    <label style="display:flex;align-items:center;font-size:0.98em;margin:10px 0;">
                        <input type="checkbox" name="agent_terms" required style="margin-right:8px;">
                        I agree to the <a href="<?php echo esc_url(home_url('/terms')); ?>" target="_blank">terms and conditions</a>
                    </label>
                </div>
                
                <div id="insured-fields" style="display:none;">
                    <input type="text" name="insured_names" placeholder="Legal Names" required>
                    <input type="text" name="insured_phone" placeholder="Kenyan Phone (0XXXXXXXXX)" pattern="^0\d{9}$" required>
                    <input type="date" name="insured_dob" placeholder="Date of Birth" required>
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
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const agentFields = document.getElementById('agent-fields');
                const insuredFields = document.getElementById('insured-fields');
                const form = document.getElementById('maljani-register-form');
                
                // Function to toggle fields based on account type
                function toggleFields() {
                    const selectedType = document.querySelector('input[name="account_type"]:checked').value;
                    if (selectedType === 'agent') {
                        agentFields.style.display = '';
                        insuredFields.style.display = 'none';
                        // Set required attributes for agent fields
                        agentFields.querySelectorAll('input[required]').forEach(input => input.disabled = false);
                        insuredFields.querySelectorAll('input[required]').forEach(input => input.disabled = true);
                    } else {
                        agentFields.style.display = 'none';
                        insuredFields.style.display = '';
                        // Set required attributes for insured fields
                        insuredFields.querySelectorAll('input[required]').forEach(input => input.disabled = false);
                        agentFields.querySelectorAll('input[required]').forEach(input => input.disabled = true);
                    }
                }
                
                // Initial toggle
                toggleFields();
                
                // Listen for radio button changes
                document.querySelectorAll('input[name="account_type"]').forEach(radio => {
                    radio.addEventListener('change', toggleFields);
                });
            });
            </script>
        </div>
        <?php
        
        // Get the buffered content
        $form_content = ob_get_clean();
        
        // Wrap with isolation container
        return $isolation->get_isolated_form($form_content, 'registration');
    }

    public function handle_registration() {
        if (isset($_POST['maljani_register_user'])) {
            if ($_POST['account_type'] === 'agent') {
                $this->process_agent_registration();
            } elseif ($_POST['account_type'] === 'insured') {
                $this->process_insured_registration();
            }
        }
    }

    private function process_agent_registration() {
        // Validation
        if (
            empty($_POST['agent_email']) ||
            empty($_POST['agent_phone']) ||
            empty($_POST['agent_names']) ||
            empty($_POST['agent_dob']) ||
            empty($_POST['agent_ira_number']) ||
            empty($_POST['agent_terms'])
        ) {
            wp_redirect(add_query_arg('error', 'missing_fields', wp_get_referer()));
            exit;
        }

        // Phone validation
        if (!preg_match('/^0\d{9}$/', $_POST['agent_phone'])) {
            wp_redirect(add_query_arg('error', 'invalid_phone', wp_get_referer()));
            exit;
        }

        // Check if email exists
        if (email_exists($_POST['agent_email'])) {
            wp_redirect(add_query_arg('error', 'email_exists', wp_get_referer()));
            exit;
        }

        // Create user
        $username = sanitize_user(str_replace(' ', '', strtolower($_POST['agent_names'])) . rand(100,999));
        $password = wp_generate_password(12);
        $user_id = wp_create_user(
            $username,
            $password,
            sanitize_email($_POST['agent_email'])
        );

        if (!is_wp_error($user_id)) {
            // Set user role
            wp_update_user(['ID' => $user_id, 'role' => 'agent']);
            
            // Save user meta
            update_user_meta($user_id, 'agent_phone', sanitize_text_field($_POST['agent_phone']));
            update_user_meta($user_id, 'agent_names', sanitize_text_field($_POST['agent_names']));
            update_user_meta($user_id, 'agent_dob', sanitize_text_field($_POST['agent_dob']));
            update_user_meta($user_id, 'agent_ira_number', sanitize_text_field($_POST['agent_ira_number']));
            update_user_meta($user_id, 'registration_date', current_time('mysql'));
            update_user_meta($user_id, 'account_status', 'pending_approval');

            // Send notification email
            $this->send_registration_email($user_id, $password, 'agent');

            // Redirect with success message
            wp_redirect(add_query_arg('registration', 'success', wp_get_referer()));
            exit;
        } else {
            wp_redirect(add_query_arg('error', 'email_exists', wp_get_referer()));
            exit;
        }
    }

    private function process_insured_registration() {
        // Validation
        if (
            empty($_POST['insured_names']) ||
            empty($_POST['insured_phone']) ||
            empty($_POST['insured_dob']) ||
            empty($_POST['insured_terms'])
        ) {
            wp_redirect(add_query_arg('error', 'missing_fields', wp_get_referer()));
            exit;
        }

        // Phone validation
        if (!preg_match('/^0\d{9}$/', $_POST['insured_phone'])) {
            wp_redirect(add_query_arg('error', 'invalid_phone', wp_get_referer()));
            exit;
        }

        // Check if phone number already exists
        $existing_user = get_users(array(
            'meta_key' => 'insured_phone',
            'meta_value' => $_POST['insured_phone'],
            'number' => 1
        ));
        
        if (!empty($existing_user)) {
            wp_redirect(add_query_arg('error', 'phone_exists', wp_get_referer()));
            exit;
        }

        // Generate email from phone number since email is not provided
        $generated_email = 'insured_' . $_POST['insured_phone'] . '@maljani.local';

        // Create user
        $username = sanitize_user(str_replace(' ', '', strtolower($_POST['insured_names'])) . rand(100,999));
        $password = wp_generate_password(12);
        $user_id = wp_create_user(
            $username,
            $password,
            $generated_email
        );

        if (!is_wp_error($user_id)) {
            // Set user role
            wp_update_user(['ID' => $user_id, 'role' => 'insured']);
            
            // Save user meta
            update_user_meta($user_id, 'insured_phone', sanitize_text_field($_POST['insured_phone']));
            update_user_meta($user_id, 'insured_names', sanitize_text_field($_POST['insured_names']));
            update_user_meta($user_id, 'insured_dob', sanitize_text_field($_POST['insured_dob']));
            update_user_meta($user_id, 'registration_date', current_time('mysql'));
            update_user_meta($user_id, 'account_status', 'active');

            // Send notification (phone-based since no email provided)
            $this->send_insured_notification($user_id, $password);

            // Redirect with success message
            wp_redirect(add_query_arg('registration', 'success', wp_get_referer()));
            exit;
        } else {
            wp_redirect(add_query_arg('error', 'registration_failed', wp_get_referer()));
            exit;
        }
    }

    private function send_registration_email($user_id, $password, $role) {
        $user = get_userdata($user_id);
        $dashboard_url = get_permalink(get_option('maljani_user_dashboard_page'));
        
        $subject = 'Welcome to Maljani Travel Insurance - Your Account Details';
        
        $message = "
        <html>
        <body>
            <h2>Welcome to Maljani Travel Insurance!</h2>
            <p>Dear {$user->display_name},</p>
            
            <p>Thank you for registering as " . ucfirst($role) . " with Maljani Travel Insurance. Your account has been successfully created.</p>
            
            <div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h3>Your Login Details:</h3>
                <p><strong>Username:</strong> {$user->user_login}</p>
                <p><strong>Email:</strong> {$user->user_email}</p>
                <p><strong>Password:</strong> {$password}</p>
            </div>
            
            <p><strong>Important:</strong> Please change your password after your first login for security purposes.</p>
            
            " . ($role === 'agent' ? "<p><strong>Note:</strong> Your agent account is pending approval. You will be notified once it's activated.</p>" : "") . "
            
            <p><a href='{$dashboard_url}' style='background-color: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>Access Your Dashboard</a></p>
            
            <p>If you have any questions, please don't hesitate to contact our support team.</p>
            
            <p>Best regards,<br>Maljani Travel Insurance Team</p>
        </body>
        </html>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($user->user_email, $subject, $message, $headers);
        
        // Send notification to admin for agent registrations
        if ($role === 'agent') {
            $admin_email = get_option('admin_email');
            $admin_subject = 'New Agent Registration - Approval Required';
            $admin_message = "
            <html>
            <body>
                <h3>New Agent Registration</h3>
                <p>A new agent has registered and requires approval:</p>
                <ul>
                    <li><strong>Name:</strong> {$user->display_name}</li>
                    <li><strong>Email:</strong> {$user->user_email}</li>
                    <li><strong>IRA Number:</strong> " . get_user_meta($user_id, 'agent_ira_number', true) . "</li>
                    <li><strong>Phone:</strong> " . get_user_meta($user_id, 'agent_phone', true) . "</li>
                </ul>
                <p>Please review and approve this agent account in the admin dashboard.</p>
            </body>
            </html>
            ";
            wp_mail($admin_email, $admin_subject, $admin_message, $headers);
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
        <style> 
        /* Maljani Registration - Simplistic Dark Blue, Grey & White */
.maljani-register-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 32px 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(20,30,60,0.08);
    text-align: center;
    border: 1px solid #e3e6ee;
}
.maljani-register-container h2 {
    color: #1a2340;
    margin-bottom: 8px;
    font-size: 2em;
    font-weight: 700;
}
.maljani-register-container p {
    color: #4a5568;
    margin-bottom: 18px;
}
.maljani-register-form {
    max-width: 400px;
    margin: 0 auto;
    background: #f7f9fc;
    border-radius: 10px;
    box-shadow: 0 1px 8px rgba(20,30,60,0.04);
    padding: 28px 28px 18px 28px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    border: 1px solid #e3e6ee;
}
.maljani-register-form input[type="text"],
.maljani-register-form input[type="email"],
.maljani-register-form input[type="date"] {
    border: 1px solid #bfc7d1;
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 1em;
    background: #fff;
    color: #1a2340;
    transition: border 0.2s;
}
.maljani-register-form input:focus {
    border-color: #1a2340;
    outline: none;
    background: #f0f4fa;
}
.maljani-register-form label {
    font-size: 1em;
    color: #1a2340;
    margin-bottom: 0;
    font-weight: 500;
}
.maljani-register-form input[type="radio"] {
    accent-color: #1a2340;
    margin-right: 8px;
}
.maljani-register-form input[type="checkbox"] {
    accent-color: #1a2340;
    margin-right: 8px;
}
.maljani-register-form button[type="submit"] {
    background: #1a2340;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 12px 0;
    font-size: 1.08em;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
    margin-top: 8px;
    box-shadow: 0 2px 8px rgba(20,30,60,0.06);
}
.maljani-register-form button[type="submit"]:hover {
    background: #223366;
    color: #fff;
}
.maljani-register-form a {
    color: #223366;
    text-decoration: underline;
    font-size: 0.97em;
    transition: color 0.2s;
}
.maljani-register-form a:hover {
    color: #1a2340;
}
.maljani-success-message {
    background-color: #e6f0fa;
    color: #1a2340;
    border: 1px solid #bfc7d1;
    border-radius: 6px;
    padding: 12px 16px;
    margin: 16px 0;
    font-size: 0.98em;
    line-height: 1.5;
}
.maljani-error-message {
    background-color: #fbeaea;
    color: #a12a2a;
    border: 1px solid #e3bcbc;
    border-radius: 6px;
    padding: 12px 16px;
    margin: 16px 0;
    font-size: 0.98em;
    line-height: 1.5;
}
@media (max-width: 600px) {
    .maljani-register-container {
        padding: 10px 2vw;
    }
    .maljani-register-form {
        padding: 16px 4vw 12px 4vw;
    }
}
    </style>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
    }
}
new Maljani_Agent_Registration();
