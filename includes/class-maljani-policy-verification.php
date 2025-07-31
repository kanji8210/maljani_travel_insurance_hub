<?php

class Maljani_Policy_Verification {
    
    public function __construct() {
        add_action('init', [$this, 'add_verification_endpoint']);
        add_action('template_redirect', [$this, 'handle_verification_request']);
        add_filter('query_vars', [$this, 'add_query_vars']);
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
        <style>
        .verification-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
        }
        .verification-header h1 {
            margin: 0;
            font-size: 28px;
        }
        .verification-header .status {
            font-size: 18px;
            margin-top: 10px;
            font-weight: bold;
        }
        .policy-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
        }
        .detail-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .detail-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
        }
        .detail-value {
            color: #333;
        }
        .insurer-info {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #fff;
            border: 2px solid #667eea;
            border-radius: 8px;
        }
        .insurer-logo {
            max-height: 60px;
            max-width: 200px;
            margin-bottom: 10px;
        }
        .verification-footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            background: #e8f5e8;
            border-radius: 8px;
            color: #2d5a2d;
        }
        @media (max-width: 768px) {
            .policy-details {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
        </style>
        
        <div class="verification-header">
            <h1>✓ Policy Verification Successful</h1>
            <div class="status">This travel insurance policy is VALID and AUTHENTIC</div>
        </div>
        
        <?php if ($insurer_logo || $insurer_name): ?>
        <div class="insurer-info">
            <?php if ($insurer_logo): ?>
                <img src="<?php echo esc_url($insurer_logo); ?>" alt="Insurer Logo" class="insurer-logo">
            <?php endif; ?>
            <h3><?php echo esc_html($insurer_name ?: 'Insurance Company'); ?></h3>
            <p>Authorized Insurance Provider</p>
        </div>
        <?php endif; ?>
        
        <div class="policy-details">
            <div class="detail-section">
                <h3>📋 Policy Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Policy Number:</span>
                    <span class="detail-value"><?php echo esc_html($sale->policy_number); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Product:</span>
                    <span class="detail-value"><?php echo esc_html($policy_title); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Coverage Period:</span>
                    <span class="detail-value">
                        <?php echo esc_html($sale->departure); ?> to <?php echo esc_html($sale->return); ?>
                        <?php if ($duration_days): ?>
                            <br><small>(<?php echo $duration_days; ?> days)</small>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <strong style="color: <?php echo $sale->policy_status === 'confirmed' ? '#28a745' : '#ffc107'; ?>;">
                            <?php echo ucfirst($sale->policy_status); ?>
                        </strong>
                    </span>
                </div>
            </div>
            
            <div class="detail-section">
                <h3>👤 Insured Person</h3>
                <div class="detail-row">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><?php echo esc_html($sale->insured_names); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Passport Number:</span>
                    <span class="detail-value"><?php echo esc_html($sale->passport_number); ?></span>
                </div>
                <?php if ($sale->insured_dob): ?>
                <div class="detail-row">
                    <span class="detail-label">Date of Birth:</span>
                    <span class="detail-value"><?php echo esc_html($sale->insured_dob); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">Country of Origin:</span>
                    <span class="detail-value">KENYA</span>
                </div>
            </div>
        </div>
        
        <div class="detail-section">
            <h3>🏥 Coverage Details</h3>
            <div style="font-size: 16px; line-height: 1.6;">
                • <strong>Medical Transportation/Repatriation:</strong> EUR 36,000<br>
                • <strong>Medical Expenses Abroad:</strong> EUR 36,000<br>
                • <strong>Emergency Medical Assistance:</strong> 24/7 Available<br>
                • <strong>Premium Paid:</strong> <?php echo esc_html($sale->premium ?: 'N/A'); ?> USD
            </div>
        </div>
        
        <div class="verification-footer">
            <p><strong>🔒 Security Information:</strong></p>
            <p>This verification was conducted on <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p>Verification ID: <?php echo substr(md5($sale->id . $sale->policy_number), 0, 8); ?></p>
            <p><small>For additional verification or support, please contact the insurance provider.</small></p>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo home_url(); ?>" style="display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Return to Homepage
            </a>
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
