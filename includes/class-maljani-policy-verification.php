<?php

class Maljani_Policy_Verification {
    
    public function __construct() {
        add_action('init', [$this, 'add_verification_endpoint']);
        add_action('template_redirect', [$this, 'handle_verification_request']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_shortcode('maljani_verify_policy', [$this, 'render_verification_form']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Register the public JSON verification endpoint.
     * GET /wp-json/maljani/v1/verify?policy_no=MAL-XXXXX&passport=XXXXXXXX
     */
    public function register_rest_routes() {
        register_rest_route('maljani/v1', '/verify', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'rest_verify_policy'],
            'permission_callback' => '__return_true', // Public endpoint — read-only, no PII beyond what caller already knows
            'args' => [
                'policy_no' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => fn($v) => !empty(trim($v)),
                ],
                'passport'  => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => fn($v) => !empty(trim($v)),
                ],
            ],
        ]);

        /**
         * GET /wp-json/maljani/v1/my-policies
         * Returns the authenticated user's own policy purchases.
         */
        register_rest_route('maljani/v1', '/my-policies', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'rest_get_my_policies'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);
    }

    public function rest_verify_policy(\WP_REST_Request $request) {
        $policy_no = strtoupper(trim($request->get_param('policy_no')));
        $passport  = strtoupper(trim($request->get_param('passport')));

        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        $sale  = $wpdb->get_row($wpdb->prepare(
            "SELECT id, policy_number, insured_names, passport_number,
                    departure, `return`, region, policy_status, amount, policy_id
             FROM {$table}
             WHERE policy_number = %s AND UPPER(passport_number) = %s
             LIMIT 1",
            $policy_no, $passport
        ));

        if (!$sale) {
            return new \WP_REST_Response([
                'valid'   => false,
                'message' => 'No matching policy found. Please check the policy number and passport number.',
            ], 200);
        }

        $policy_title = get_the_title($sale->policy_id);

        // Resolve insurer name from post meta
        $insurer_name = get_post_meta($sale->policy_id, '_policy_insurer_name', true);
        if (empty($insurer_name)) {
            $insurer_id = get_post_meta($sale->policy_id, '_policy_insurer', true);
            if ($insurer_id) {
                $insurer_name = get_post_meta($insurer_id, '_insurer_name', true);
            }
        }

        return new \WP_REST_Response([
            'valid'          => true,
            'policyNumber'   => $sale->policy_number,
            'insuredName'    => $sale->insured_names,
            'passportNumber' => $sale->passport_number,
            'departure'      => $sale->departure,
            'return'         => $sale->return,
            'region'         => $sale->region,
            'policyTitle'    => $policy_title ?: 'Travel Insurance Policy',
            'insurer'        => $insurer_name ?: 'Authorized Insurer',
            'policyStatus'   => $sale->policy_status,
            'verifiedAt'     => gmdate('c'),
        ], 200);
    }

    /**
     * GET /wp-json/maljani/v1/my-policies
     * Returns the logged-in traveler's own policy purchase history.
     */
    public function rest_get_my_policies(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_REST_Response(['code' => 'not_logged_in', 'message' => 'You must be logged in.'], 401);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, policy_number, policy_id, departure, `return`, region,
                    policy_status, payment_status, amount_paid,
                    insured_names, passengers, created_at
             FROM {$table}
             WHERE agent_id = %d
             ORDER BY created_at DESC
             LIMIT 50",
            $user_id
        ));

        $policies = array_map(function($row) {
            return [
                'id'            => (int) $row->id,
                'policyNumber'  => $row->policy_number,
                'policyTitle'   => get_the_title($row->policy_id) ?: 'Travel Insurance',
                'departure'     => $row->departure,
                'return'        => $row->return,
                'region'        => $row->region,
                'policyStatus'  => $row->policy_status,
                'paymentStatus' => $row->payment_status,
                'amountPaid'    => (float) $row->amount_paid,
                'insuredName'   => $row->insured_names,
                'passengers'    => (int) $row->passengers,
                'createdAt'     => $row->created_at,
            ];
        }, $rows ?: []);

        return new \WP_REST_Response(['policies' => $policies], 200);
    }

    public function render_verification_form() {
        ob_start();
        ?>
        <div class="maljani-public-verification">
            <h2>Verify Insurance Policy</h2>
            <p>Enter the policy number and the insured's passport number to verify validity.</p>
            <form action="" method="get" class="verification-mini-form">
                <input type="hidden" name="verify_policy" value="1">
                <div class="form-row" style="display:flex;gap:15px;margin-bottom:15px;">
                    <input type="text" name="policy_no" placeholder="Policy Number (e.g. MAL-1234)" required style="flex:1;padding:12px;border:1px solid #ddd;border-radius:8px;">
                    <input type="text" name="passport" placeholder="Passport Number" required style="flex:1;padding:12px;border:1px solid #ddd;border-radius:8px;">
                </div>
                <button type="submit" class="maljani-btn-premium" style="width:100%;cursor:pointer;background:#4f46e5;color:white;border:none;padding:15px;border-radius:8px;font-weight:700;">Verify Authenticity</button>
            </form>
        </div>
        
        <?php
        // Handle the submission if params are present in GET
        if (isset($_GET['policy_no']) && isset($_GET['passport'])) {
            $this->handle_manual_verification(sanitize_text_field($_GET['policy_no']), sanitize_text_field($_GET['passport']));
        }
        
        return ob_get_clean();
    }

    private function handle_manual_verification($policy_no, $passport) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE policy_number = %s AND passport_number = %s",
            $policy_no, $passport
        ));

        if ($sale) {
            echo '<div style="margin-top:30px;border-top:2px solid #eee;padding-top:20px;">';
            $this->show_policy_verification($sale);
            echo '</div>';
        } else {
            echo '<div style="margin-top:30px;">';
            $this->show_error_message('No active policy found matching those credentials. Please check for typos.');
            echo '</div>';
        }
    }
    
    /**
     * Ajouter l'endpoint de vérification aux rewrite rules
     */
    public function add_verification_endpoint() {
        add_rewrite_rule(
            '^verify-policy/?$',
            'index.php?verify_policy=1',
            'top'
        );
        
        // Flush rewrite rules si nécessaire (seulement lors de l'activation)
        if (get_option('maljani_verification_endpoint_added') !== 'yes') {
            flush_rewrite_rules();
            update_option('maljani_verification_endpoint_added', 'yes');
        }
    }
    
    /**
     * Ajouter les variables de requête personnalisées
     */
    public function add_query_vars($vars) {
        $vars[] = 'verify_policy';
        return $vars;
    }
    
    /**
     * Gérer les requêtes de vérification
     */
    public function handle_verification_request() {
        if (get_query_var('verify_policy')) {
            $this->show_verification_page();
            exit;
        }
    }
    
    /**
     * Afficher la page de vérification
     */
    public function show_verification_page() {
        $sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        // Charger le header WordPress
        get_header();
        
        echo '<div class="maljani-verification-page" style="max-width: 800px; margin: 40px auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">';
        
        if (!$sale_id || !$token) {
            $this->show_error_message('Invalid verification link. Missing required parameters.');
            echo '</div>';
            get_footer();
            return;
        }
        
        // Vérifier la validité du token
        $verification_result = $this->verify_policy_token($sale_id, $token);
        
        if ($verification_result['valid']) {
            $this->show_policy_verification($verification_result['sale']);
        } else {
            $this->show_error_message($verification_result['error']);
        }
        
        echo '</div>';
        get_footer();
    }
    
    /**
     * Vérifier la validité du token de vérification
     */
    private function verify_policy_token($sale_id, $token) {
        global $wpdb;
        
        // Récupérer les données de la vente
        $table = $wpdb->prefix . 'policy_sale';
        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $sale_id
        ));
        
        if (!$sale) {
            return [
                'valid' => false,
                'error' => 'Policy not found in our records.'
            ];
        }
        
        // Générer le hash attendu
        $expected_hash = $this->generate_verification_hash(
            $sale_id, 
            $sale->policy_number, 
            $sale->passport_number
        );
        
        // Vérifier le token
        if (!hash_equals($expected_hash, $token)) {
            return [
                'valid' => false,
                'error' => 'Invalid verification token. This document may have been tampered with.'
            ];
        }
        
        return [
            'valid' => true,
            'sale' => $sale
        ];
    }
    
    /**
     * Générer le hash de vérification
     */
    private function generate_verification_hash($sale_id, $policy_number, $passport_number) {
        $secret_key = 'maljani_secure_key_2025'; // À changer en production
        $data = $sale_id . '|' . $policy_number . '|' . $passport_number;
        return hash('sha256', $data . $secret_key);
    }
    
    /**
     * Afficher les détails de vérification de la police
     */
    private function show_policy_verification($sale) {
        // Enqueue verification styles
        wp_enqueue_style('maljani-verification', plugin_dir_url(__FILE__) . 'css/maljani-verification.css', [], MALJANI_VERSION);

        // Récupérer les informations de la police
        $policy_title = get_the_title($sale->policy_id);
        $insurer_id = get_post_meta($sale->policy_id, '_policy_insurer', true);
        $insurer_name = '';
        $insurer_logo = '';
        
        if ($insurer_id) {
            $insurer_name = get_post_meta($insurer_id, '_insurer_name', true);
            $insurer_logo_id = get_post_meta($insurer_id, '_insurer_logo', true);
            if ($insurer_logo_id && is_numeric($insurer_logo_id)) {
                $insurer_logo = wp_get_attachment_url($insurer_logo_id);
            }
        }
        
        // Calculer la durée
        $duration_days = '';
        if ($sale->departure && $sale->return) {
            $d1 = new DateTime($sale->departure);
            $d2 = new DateTime($sale->return);
            $duration_days = $d1 < $d2 ? $d1->diff($d2)->days : 0;
        }
        
        ?>
        <?php
        $is_paid = ($sale->policy_status === 'active');
        $status_label = $is_paid ? 'Authenticity Verified' : 'Application Verified';
        $status_msg   = $is_paid ? 'Insurance policy is valid and active.' : 'Insurance application is verified (Payment Pending).';
        $badge_color  = $is_paid ? '#059669' : '#2563eb';
        ?>
        <div class="verification-header-minimal">
            <span class="verification-badge" style="background: <?php echo $badge_color; ?>"><?php echo esc_html($status_label); ?></span>
            <h1><?php echo $is_paid ? 'Insurance policy is valid' : 'Application is verified'; ?></h1>
            <p><?php echo esc_html($status_msg); ?></p>
        </div>
        
        <?php if ($insurer_logo || $insurer_name): ?>
        <div class="insurer-minimal-box">
            <?php if ($insurer_logo): ?>
                <img src="<?php echo esc_url($insurer_logo); ?>" alt="Insurer Logo" class="insurer-emblem-gray">
            <?php endif; ?>
            <div class="insurer-text">
                <h3><?php echo esc_html($insurer_name ?: 'Insurance Company'); ?></h3>
                <span class="insurer-label">Authorized Partner</span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="policy-details-asymmetric">
            <div class="detail-block">
                <h3>Policy Info</h3>
                <div class="mj-row">
                    <span class="mj-label">Number</span>
                    <span class="mj-value">#<?php echo esc_html($sale->policy_number); ?></span>
                </div>
                <div class="mj-row">
                    <span class="mj-label">Product</span>
                    <span class="mj-value"><?php echo esc_html($policy_title); ?></span>
                </div>
                <div class="mj-row">
                    <span class="mj-label">Period</span>
                    <span class="mj-value">
                        <?php echo esc_html($sale->departure); ?> — <?php echo esc_html($sale->return); ?>
                    </span>
                </div>
                <div class="mj-row">
                    <span class="mj-label">Status</span>
                    <span class="mj-value status-<?php echo esc_attr($sale->policy_status); ?>" style="color: <?php echo $badge_color; ?>; font-weight: 800;">
                        <?php echo strtoupper(str_replace('_',' ',$sale->policy_status)); ?>
                        <?php if (!$is_paid) echo ' (PAYMENT PENDING)'; ?>
                    </span>
                </div>
            </div>
            
            <div class="detail-block">
                <h3>Insured Person</h3>
                <div class="mj-row">
                    <span class="mj-label">Full Name</span>
                    <span class="mj-value"><?php echo esc_html($sale->insured_names); ?></span>
                </div>
                <div class="mj-row">
                    <span class="mj-label">Passport</span>
                    <span class="mj-value"><?php echo esc_html($sale->passport_number); ?></span>
                </div>
                <div class="mj-row">
                    <span class="mj-label">Origin</span>
                    <span class="mj-value">KENYA</span>
                </div>
            </div>
        </div>
        
        <div class="verification-footer-minimal">
            <div class="security-meta">
                <p><strong>Verification Hash:</strong> <?php echo substr(md5($sale->id . $sale->policy_number), 0, 12); ?></p>
                <p>Checked on <?php echo date('F j, Y'); ?></p>
            </div>
            <a href="<?php echo home_url(); ?>" class="mj-link-back">Return to portal</a>
        </div>
        <?php
    }
    
    /**
     * Afficher un message d'erreur
     */
    private function show_error_message($message) {
        ?>
        <style>
        .error-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border-radius: 8px;
        }
        .error-content {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
        }
        </style>
        
        <div class="error-header">
            <h1>❌ Verification Failed</h1>
        </div>
        
        <div class="error-content">
            <h3>Unable to Verify Policy</h3>
            <p style="font-size: 16px; color: #666; margin: 20px 0;">
                <?php echo esc_html($message); ?>
            </p>
            
            <div style="margin-top: 30px;">
                <h4>Possible reasons:</h4>
                <ul style="text-align: left; display: inline-block;">
                    <li>The verification link has expired</li>
                    <li>The policy information has been modified</li>
                    <li>The link was copied incorrectly</li>
                    <li>The policy has been cancelled or updated</li>
                </ul>
            </div>
            
            <div style="margin-top: 30px;">
                <p><strong>Need help?</strong></p>
                <p>Please contact the insurance provider directly with your policy number for assistance.</p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo home_url(); ?>" style="display: inline-block; padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Return to Homepage
            </a>
        </div>
        <?php
    }
    
    /**
     * Activer l'endpoint lors de l'activation du plugin
     */
    public static function activate() {
        // Ajouter les rewrite rules
        add_rewrite_rule(
            '^verify-policy/?$',
            'index.php?verify_policy=1',
            'top'
        );
        
        // Flush rewrite rules
        flush_rewrite_rules();
        update_option('maljani_verification_endpoint_added', 'yes');
    }
    
    /**
     * Nettoyer lors de la désactivation
     */
    public static function deactivate() {
        // Supprimer l'option
        delete_option('maljani_verification_endpoint_added');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
