<?php
//includes\class-maljani-user-dashboard.php

/**
 * Maljani User Dashboard - Tableau de Bord Utilisateur
 * 
 * Tableau de bord personnalisé pour les agents et les clients insured :
 * - Pour les agents : Toutes les polices vendues + gestion de profil + historique des ventes
 * - Pour les insured : Leurs propres polices + édition de profil + téléchargement PDF
 * 
 * Fonctionnalités :
 * - Affichage des polices selon le rôle utilisateur
 * - Édition du profil utilisateur
 * - Génération et téléchargement de PDF des polices
 * - Filtrage et recherche des polices
 * - Statuts en temps réel
 * 
 * Shortcode : [maljani_user_dashboard]
 * 
 * @since 1.0.0
 * @version 1.0.0 - Création du tableau de bord utilisateur (23/07/2025)
 */

class Maljani_User_Dashboard {
    
    public function __construct() {
        add_shortcode('maljani_user_dashboard', [$this, 'render_dashboard']);
        add_shortcode('maljani_login_form', [$this, 'render_login_form']);
        add_shortcode('maljani_insurer_dashboard', [$this, 'render_insurer_dashboard']);
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('init', [$this, 'handle_dashboard_actions']);
        add_action('wp_ajax_maljani_update_profile', [$this, 'handle_profile_update']);
        add_action('wp_ajax_get_policy_details', [$this, 'handle_policy_details_ajax']);
    }
    
    public function enqueue_assets() {
        // Charger seulement sur les pages avec le shortcode ou la page dashboard configurée
        $dashboard_page_id = get_option('maljani_user_dashboard_page');
        $should_enqueue = false;
        
        if (is_singular()) {
            if ($dashboard_page_id && is_page($dashboard_page_id)) {
                $should_enqueue = true;
            } elseif (has_shortcode(get_post()->post_content, 'maljani_user_dashboard')) {
                $should_enqueue = true;
            }
        }
        
        if ($should_enqueue) {
            // Styles gérés par le système d'isolation - pas besoin de CSS externe
            // wp_enqueue_style('maljani-dashboard', plugin_dir_url(__FILE__) . 'css/dashboard.css', [], '1.0.0');
            
            wp_enqueue_script('maljani-dashboard', plugin_dir_url(__FILE__) . 'js/dashboard.js', ['jquery'], defined('MALJANI_VERSION') ? MALJANI_VERSION : '1.0.0', true);

            // Localisation pour AJAX — include both legacy keys and new keys for compatibility
            wp_localize_script('maljani-dashboard', 'maljaniDashboard', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('maljani_dashboard_nonce'),
                'security' => wp_create_nonce('maljani_dashboard_nonce'),
                'strings' => [
                    'confirm_update' => 'Confirm profile update?',
                    'generating_pdf' => 'Generating PDF...',
                    'error_occurred' => 'An error occurred. Please try again.'
                ]
            ]);
        }
    }
    
    public function handle_dashboard_actions() {
        // Gestion des actions spécifiques du dashboard via URL
        if (isset($_GET['maljani_action'])) {
            switch ($_GET['maljani_action']) {
                case 'download_pdf':
                    $this->handle_pdf_download();
                    break;
                case 'view_policy':
                    $this->handle_policy_view();
                    break;
            }
        }
    }
    
    public function render_login_form() {
        if (is_user_logged_in()) {
            return $this->render_dashboard();
        }
        
        ob_start();
        ?>
        <div class="maljani-login-wrapper">
            <div class="login-card">
                <div class="login-header">
                    <h2>Maljani Hub Login</h2>
                    <p>Access your insurance and agency portal</p>
                </div>
                <?php wp_login_form([
                    'label_username' => 'Email Address',
                    'label_password' => 'Secure Password',
                    'label_remember' => 'Stay connected',
                    'label_log_in'   => 'Enter Hub',
                    'remember'       => true,
                    'value_remember' => true,
                    'redirect'       => get_permalink()
                ]); ?>
                <div class="login-footer">
                    <p>New to Maljani? <a href="<?php echo get_permalink(get_option('maljani_page_register_agency')); ?>">Register your agency</a></p>
                </div>
            </div>
        </div>
        <style>
            .maljani-login-wrapper {
                max-width: 450px; margin: 40px auto;
                background: rgba(255, 255, 255, 0.7);
                backdrop-filter: blur(10px);
                border-radius: 24px; padding: 40px;
                box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
                border: 1px solid rgba(255,255,255,0.8);
            }
            .login-header { text-align: center; margin-bottom: 30px; }
            .login-header h2 { font-size: 24px; font-weight: 800; color: #1e293b; margin: 0; }
            .login-header p { color: #64748b; font-size: 14px; margin-top: 8px; }
            #loginform label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 13px; }
            #loginform input[type="text"], #loginform input[type="password"] {
                width: 100%; padding: 12px 16px; border-radius: 12px;
                border: 1px solid #e2e8f0; background: #f8fafc;
                margin-bottom: 20px; transition: all 0.2s;
            }
            #loginform input:focus { border-color: #4f46e5; outline: none; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
            #loginform .forgetmenot { margin-bottom: 20px; color: #64748b; font-size: 13px; }
            #loginform .button-primary {
                width: 100%; border: none; background: #4f46e5; color: white;
                padding: 14px; border-radius: 12px; font-weight: 700;
                cursor: pointer; transition: transform 0.2s;
            }
            #loginform .button-primary:hover { background: #4338ca; transform: translateY(-1px); }
            .login-footer { text-align: center; margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px; color: #64748b; font-size: 14px; }
            .login-footer a { color: #4f46e5; font-weight: 600; text-decoration: none; }
        </style>
        <?php
        return ob_get_clean();
    }

    public function render_insurer_dashboard() {
        if (!is_user_logged_in() || !current_user_can('insurer_access') && !in_array('insurer', wp_get_current_user()->roles)) {
            return '<div class="notice notice-error"><p>Access denied. This portal is for authorized insurers only.</p></div>';
        }

        $policies = $this->get_pending_insurer_policies();
        
        ob_start();
        ?>
        <div class="maljani-dashboard-container insurer-portal">
            <div class="maljani-dashboard-header" style="background: linear-gradient(135deg, #1e3a8a, #1d4ed8);">
                <div style="display:flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2>🏦 Insurer Underwriting Portal</h2>
                        <p>Review and verify submitted insurance policies.</p>
                    </div>
                    <div class="portal-stat">
                        <span class="stat-value"><?php echo count($policies); ?></span>
                        <span class="stat-label">Pending Review</span>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <h3>Policies Awaiting Action</h3>
                <?php if (empty($policies)): ?>
                    <div style="text-align:center; padding: 60px 20px;">
                        <span style="font-size: 48px;">✅</span>
                        <p style="margin-top: 20px; font-size: 18px; color: #64748b;">Great work! All policies have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <div class="insurer-policy-grid">
                        <?php foreach ($policies as $policy): ?>
                            <div class="policy-review-card">
                                <div class="review-status">Awaiting Verification</div>
                                <h4><?php echo esc_html($policy->insured_names); ?></h4>
                                <div class="review-meta">
                                    <span>#<?php echo esc_html($policy->policy_number); ?></span>
                                    <span><?php echo esc_html($policy->region); ?></span>
                                </div>
                                <div class="review-details">
                                    <div class="r-row"><span>Departure:</span> <strong><?php echo esc_html($policy->departure); ?></strong></div>
                                    <div class="r-row"><span>Return:</span> <strong><?php echo esc_html($policy->return); ?></strong></div>
                                    <div class="r-row"><span>Premium:</span> <strong><?php echo esc_html($policy->premium); ?></strong></div>
                                </div>
                                <div class="review-actions">
                                    <button class="maljani-btn-verify" data-id="<?php echo $policy->id; ?>">Review Documents</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <style>
            .insurer-portal .portal-stat { text-align: center; background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.2); }
            .portal-stat .stat-value { display: block; font-size: 32px; font-weight: 800; line-height: 1; }
            .portal-stat .stat-label { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
            .content-card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
            .insurer-policy-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
            .policy-review-card { border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; position: relative; transition: all 0.2s; }
            .policy-review-card:hover { border-color: #3b82f6; box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.1); }
            .review-status { position: absolute; top: 20px; right: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #3b82f6; background: #eff6ff; padding: 4px 8px; border-radius: 6px; }
            .policy-review-card h4 { margin: 0 0 10px 0; font-size: 18px; color: #1e293b; }
            .review-meta { display: flex; gap: 10px; margin-bottom: 20px; color: #64748b; font-size: 12px; }
            .review-meta span { background: #f1f5f9; padding: 2px 8px; border-radius: 4px; }
            .review-details { margin-bottom: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px; }
            .r-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 5px; }
            .r-row span { color: #64748b; }
            .maljani-btn-verify { width: 100%; padding: 12px; border: none; background: #1e293b; color: white; border-radius: 10px; font-weight: 600; cursor: pointer; }
        </style>
        <?php
        return ob_get_clean();
    }

    private function get_pending_insurer_policies() {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        // In a real scenario, we'd filter by the insurer's assigned policies
        // For now, show all pending
        return $wpdb->get_results("SELECT * FROM $table WHERE policy_status = 'pending' ORDER BY created_at DESC LIMIT 20");
    }

    public function render_dashboard() {
        $isolation = Maljani_Style_Isolation::instance();
        
        if (!is_user_logged_in()) {
            return $isolation->wrap_output('
                <div class="maljani-dashboard-login-required">
                    <p>You must be logged in to view your dashboard.</p>
                    <a href="' . wp_login_url(get_permalink()) . '" class="maljani-btn">Login</a>
                </div>', ['class' => 'maljani-dashboard-wrapper']);
        }
        
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        
        // Premium Hub Wrapper
        ob_start();
        ?>
        <div class="maljani-hub-entrance">
            <div class="hub-vignette"></div>
            <div class="hub-content">
                <div class="hub-welcome">
                    <span class="hub-badge">Premium Access</span>
                    <h1>Welcome, <?php echo esc_html($current_user->first_name ?: $current_user->display_name); ?></h1>
                    <p>Redirecting you to your personal control center...</p>
                </div>
                
                <div class="hub-loading">
                    <div class="hub-spinner"></div>
                </div>

                <div class="hub-routing-box">
                    <?php 
                    $agency_page = get_option('maljani_page_agency_dashboard');
                    $client_page = get_option('maljani_page_client_dashboard');
                    $insurer_page = get_option('maljani_page_insurer_dashboard');
                    $agency_url = $agency_page ? get_permalink($agency_page) : site_url('/agency-dashboard');
                    $client_url = $client_page ? get_permalink($client_page) : site_url('/my-policies');
                    $insurer_url = $insurer_page ? get_permalink($insurer_page) : site_url('/insurer-portal');
                    ?>
                    <?php if (in_array('agent', $user_roles) || current_user_can('manage_maljani_agencies')): ?>
                        <div class="route-info">
                            <span class="route-icon">🏢</span>
                            <span class="route-text">Agency Management Portal</span>
                        </div>
                        <script>setTimeout(() => { window.location.href = '<?php echo esc_url($agency_url); ?>'; }, 1500);</script>
                    <?php elseif (in_array('insurer', $user_roles)): ?>
                        <div class="route-info">
                            <span class="route-icon">🏦</span>
                            <span class="route-text">Insurer Underwriting Portal</span>
                        </div>
                        <script>setTimeout(() => { window.location.href = '<?php echo esc_url($insurer_url); ?>'; }, 1500);</script>
                    <?php elseif (in_array('insured', $user_roles)): ?>
                        <div class="route-info">
                            <span class="route-icon">🛡️</span>
                            <span class="route-text">Personal Insurance Hub</span>
                        </div>
                        <script>setTimeout(() => { window.location.href = '<?php echo esc_url($client_url); ?>'; }, 1500);</script>
                    <?php else: ?>
                         <div class="route-info">
                            <span class="route-icon">👤</span>
                            <span class="route-text">User Profile Settings</span>
                        </div>
                        <script>setTimeout(() => { jQuery('#fallback-profile').fadeIn(); jQuery('.hub-content').fadeOut(); }, 1500);</script>
                    <?php endif; ?>
                </div>
            </div>

            <div id="fallback-profile" style="display:none;">
                <div class="maljani-dashboard-container">
                    <div class="maljani-dashboard-header">
                        <h2>👤 My Account - <?php echo esc_html($current_user->display_name); ?></h2>
                        <p>Basic account settings.</p>
                    </div>
                    <div class="tab-content active" id="profile-tab">
                        <?php $this->render_profile_section($this->get_user_profile_data($current_user->ID), $current_user); ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .maljani-hub-entrance {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            min-height: 500px; padding: 100px 40px; border-radius: 24px;
            position: relative; overflow: hidden; color: white; text-align: center;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Inter', system-ui, sans-serif;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .hub-content { z-index: 2; animation: hubFadeIn 1s cubic-bezier(0.16, 1, 0.3, 1); }
        .hub-badge { 
            background: rgba(79, 70, 229, 0.2); color: #818cf8; padding: 6px 16px; 
            border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1px; border: 1px solid rgba(129, 140, 248, 0.3);
            margin-bottom: 20px; display: inline-block;
        }
        .hub-welcome h1 { font-size: 42px; font-weight: 800; margin: 0; background: linear-gradient(to right, #fff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hub-welcome p { font-size: 18px; color: #94a3b8; margin-top: 10px; }
        
        .hub-loading { margin: 40px 0; }
        .hub-spinner {
            width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.1);
            border-top-color: #4f46e5; border-radius: 50%;
            margin: 0 auto; animation: hubSpin 1s linear infinite;
        }
        .hub-routing-box { background: rgba(255,255,255,0.03); padding: 20px 40px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05); }
        .route-info { display: flex; align-items: center; gap: 15px; justify-content: center; }
        .route-icon { font-size: 24px; }
        .route-text { font-weight: 600; color: #e2e8f0; }

        @keyframes hubFadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes hubSpin { to { transform: rotate(360deg); } }
        
        /* Fallback Styles */
        #fallback-profile { width: 100%; color: #1e293b; background: white; border-radius: 24px; padding: 20px; }
        .maljani-dashboard-header { background: #1e293b; color: white; padding: 30px; border-radius: 16px; margin-bottom: 30px; text-align: left; }
        </style>
        <?php
        return ob_get_clean();
    }
    
    private function get_user_profile_data($user_id) {
        $user = get_userdata($user_id);
        return [
            'full_name' => get_user_meta($user_id, 'full_name', true) ?: $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, 'phone', true),
            'dob' => get_user_meta($user_id, 'dob', true),
            'passport_number' => get_user_meta($user_id, 'passport_number', true),
            'national_id' => get_user_meta($user_id, 'national_id', true),
            'address' => get_user_meta($user_id, 'address', true),
            'country' => get_user_meta($user_id, 'country', true)
        ];
    }
    
    private function get_user_policies($user_id, $is_agent = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        
        if ($is_agent) {
            // Pour les agents : toutes les polices qu'ils ont vendues
            $query = $wpdb->prepare(
                "SELECT * FROM $table WHERE agent_id = %d ORDER BY created_at DESC",
                $user_id
            );
        } else {
            // Pour les insured : seulement leurs propres polices
            $query = $wpdb->prepare(
                "SELECT * FROM $table WHERE agent_id = %d ORDER BY created_at DESC",
                $user_id
            );
        }
        
        return $wpdb->get_results($query);
    }
    
    private function count_policies_by_status($policies, $status) {
        return count(array_filter($policies, function($policy) use ($status) {
            return $policy->policy_status === $status;
        }));
    }
    
    private function render_notifications() {
        if (isset($_GET['profile_updated'])) {
            echo '<div class="notice notice-success"><p>Profile updated successfully!</p></div>';
        }
        if (isset($_GET['profile_error'])) {
            echo '<div class="notice notice-error"><p>Error updating profile. Please try again.</p></div>';
        }
    }
    
    private function render_policies_table($policies, $is_agent) {
        ?>
        <div class="policies-section">
            <div class="policies-header">
                <h3><?php echo $is_agent ? 'Policies You Have Sold' : 'Your Insurance Policies'; ?></h3>
                <div class="policies-filters">
                    <input type="text" id="policy-search" placeholder="Search policies..." style="padding:8px;border:1px solid #ddd;border-radius:4px;">
                    <select id="status-filter" style="padding:8px;border:1px solid #ddd;border-radius:4px;margin-left:10px;">
                        <option value="">All Statuses</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="pending">Pending</option>
                        <option value="unconfirmed">Unconfirmed</option>
                    </select>
                </div>
            </div>
            
            <?php if (empty($policies)): ?>
                <div style="text-align:center;padding:40px;background:white;border-radius:8px;">
                    <p style="font-size:18px;color:#666;">
                        <?php echo $is_agent ? 'No policies sold yet.' : 'You have no insurance policies yet.'; ?>
                    </p>
                    <?php if (!$is_agent): ?>
                        <a href="<?php echo get_permalink(get_option('maljani_policy_sale_page')); ?>" class="btn btn-primary">
                            Get Covered Now
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table class="policies-table">
                    <thead>
                        <tr>
                            <th>Policy #</th>
                            <th>Date Applied</th>
                            <th><?php echo $is_agent ? 'Client Name' : 'Policy Name'; ?></th>
                            <th>Region</th>
                            <th>Travel Dates</th>
                            <th>Premium</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="policies-tbody">
                        <?php foreach ($policies as $policy): ?>
                            <tr data-status="<?php echo esc_attr($policy->policy_status); ?>">
                                <td><strong><?php echo esc_html($policy->policy_number); ?></strong></td>
                                <td><?php echo date('M j, Y', strtotime($policy->created_at)); ?></td>
                                <td>
                                    <?php if ($is_agent): ?>
                                        <?php echo esc_html($policy->insured_names); ?>
                                        <br><small><?php echo esc_html($policy->insured_email); ?></small>
                                    <?php else: ?>
                                        <?php echo esc_html(get_the_title($policy->policy_id)); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($policy->region); ?></td>
                                <td>
                                    <?php echo date('M j', strtotime($policy->departure)); ?> - 
                                    <?php echo date('M j, Y', strtotime($policy->return)); ?>
                                    <br><small><?php echo $policy->days; ?> days</small>
                                </td>
                                <td><strong><?php echo esc_html($policy->premium); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($policy->policy_status); ?>">
                                        <?php echo ucfirst($policy->policy_status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?php echo plugin_dir_url(__FILE__) . 'generate-policy-pdf.php?sale_id=' . $policy->id; ?>" 
                                           target="_blank" class="btn btn-primary" title="Generate PDF">
                                            📄 PDF
                                        </a>
                                        <button class="btn btn-secondary view-details" data-policy-id="<?php echo $policy->id; ?>">
                                            👁️ View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Recherche de polices
                    const searchInput = document.getElementById('policy-search');
                    const statusFilter = document.getElementById('status-filter');
                    const tbody = document.getElementById('policies-tbody');
                    const rows = tbody.querySelectorAll('tr');
                    
                    function filterTable() {
                        const searchTerm = searchInput.value.toLowerCase();
                        const statusFilter_value = statusFilter.value;
                        
                        rows.forEach(row => {
                            const text = row.textContent.toLowerCase();
                            const status = row.dataset.status;
                            
                            const matchesSearch = text.includes(searchTerm);
                            const matchesStatus = !statusFilter_value || status === statusFilter_value;
                            
                            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
                        });
                    }
                    
                    searchInput.addEventListener('keyup', filterTable);
                    statusFilter.addEventListener('change', filterTable);
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_profile_section($user_data, $current_user) {
        ?>
        <div class="profile-section">
            <h3>👤 Profile Information</h3>
            <form method="post" action="" class="profile-form">
                <?php wp_nonce_field('maljani_update_profile', 'profile_nonce'); ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo esc_attr($user_data['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo esc_attr($user_data['email']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo esc_attr($user_data['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="dob">Date of Birth</label>
                        <input type="date" id="dob" name="dob" value="<?php echo esc_attr($user_data['dob']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="passport_number">Passport Number</label>
                        <input type="text" id="passport_number" name="passport_number" value="<?php echo esc_attr($user_data['passport_number']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="national_id">National ID</label>
                        <input type="text" id="national_id" name="national_id" value="<?php echo esc_attr($user_data['national_id']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3"><?php echo esc_textarea($user_data['address']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country" value="<?php echo esc_attr($user_data['country']); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" name="maljani_update_profile" class="btn btn-primary" style="padding:12px 30px;font-size:16px;">
                        💾 Update Profile
                    </button>
                </div>
            </form>
        </div>
        
        <?php
        // Traitement de la mise à jour du profil
        if (isset($_POST['maljani_update_profile']) && wp_verify_nonce($_POST['profile_nonce'], 'maljani_update_profile')) {
            $this->handle_profile_update_form();
        }
    }
    
    private function render_analytics_section($policies) {
        $total_sales = count($policies);
        $total_premium = array_sum(array_column($policies, 'premium'));
        $confirmed_sales = $this->count_policies_by_status($policies, 'confirmed');
        $pending_sales = $this->count_policies_by_status($policies, 'pending');
        
        // Statistiques par région
        $regions_stats = [];
        foreach ($policies as $policy) {
            $region = $policy->region ?: 'Unknown';
            if (!isset($regions_stats[$region])) {
                $regions_stats[$region] = ['count' => 0, 'premium' => 0];
            }
            $regions_stats[$region]['count']++;
            $regions_stats[$region]['premium'] += floatval($policy->premium);
        }
        ?>
        <div class="analytics-section">
            <h3>📊 Sales Analytics</h3>
            
            <div class="analytics-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                <div class="analytics-card">
                    <h4>Total Sales</h4>
                    <div class="big-number"><?php echo $total_sales; ?></div>
                    <p>Policies sold</p>
                </div>
                <div class="analytics-card">
                    <h4>Total Premium</h4>
                    <div class="big-number"><?php echo number_format($total_premium, 0); ?></div>
                    <p>Total value</p>
                </div>
                <div class="analytics-card">
                    <h4>Conversion Rate</h4>
                    <div class="big-number"><?php echo $total_sales ? round(($confirmed_sales / $total_sales) * 100) : 0; ?>%</div>
                    <p>Confirmed sales</p>
                </div>
                <div class="analytics-card">
                    <h4>Pending Review</h4>
                    <div class="big-number"><?php echo $pending_sales; ?></div>
                    <p>Awaiting confirmation</p>
                </div>
            </div>
            
            <?php if (!empty($regions_stats)): ?>
            <div class="regions-breakdown">
                <h4>Sales by Region</h4>
                <table class="policies-table">
                    <thead>
                        <tr>
                            <th>Region</th>
                            <th>Policies Sold</th>
                            <th>Total Premium</th>
                            <th>Average Premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regions_stats as $region => $stats): ?>
                        <tr>
                            <td><strong><?php echo esc_html($region); ?></strong></td>
                            <td><?php echo $stats['count']; ?></td>
                            <td><?php echo number_format($stats['premium'], 0); ?></td>
                            <td><?php echo number_format($stats['premium'] / $stats['count'], 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .analytics-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .analytics-card h4 {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        .big-number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .analytics-card p {
            margin: 0;
            color: #888;
            font-size: 12px;
        }
        </style>
        <?php
    }
    
    public function handle_profile_update_form() {
        $user_id = get_current_user_id();
        
        // Mise à jour des données utilisateur principales
        wp_update_user([
            'ID' => $user_id,
            'user_email' => sanitize_email($_POST['email']),
            'display_name' => sanitize_text_field($_POST['full_name'])
        ]);
        
        // Mise à jour des métadonnées
        update_user_meta($user_id, 'full_name', sanitize_text_field($_POST['full_name']));
        update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone']));
        update_user_meta($user_id, 'dob', sanitize_text_field($_POST['dob']));
        update_user_meta($user_id, 'passport_number', sanitize_text_field($_POST['passport_number']));
        update_user_meta($user_id, 'national_id', sanitize_text_field($_POST['national_id']));
        update_user_meta($user_id, 'address', sanitize_textarea_field($_POST['address']));
        update_user_meta($user_id, 'country', sanitize_text_field($_POST['country']));
        
        // Redirection avec message de succès
        wp_redirect(add_query_arg('profile_updated', '1', get_permalink()));
        exit;
    }
    
    public function handle_policy_details_ajax() {
        // Vérifier le nonce et les permissions
        if (!wp_verify_nonce($_POST['nonce'], 'maljani_dashboard_nonce') || !is_user_logged_in()) {
            wp_send_json_error('Access denied.');
        }
        
        $policy_sale_id = intval($_POST['policy_id']);
        $current_user = wp_get_current_user();
        
        // Récupérer les détails de la vente
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        $sale = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND agent_id = %d",
            $policy_sale_id,
            $current_user->ID
        ));
        
        if (!$sale) {
            wp_send_json_error('Policy not found or access denied.');
        }
        
        // Récupérer les informations de la police
        $policy_title = get_the_title($sale->policy_id);
        $policy_content = get_post_field('post_content', $sale->policy_id);
        $insurer_id = get_post_meta($sale->policy_id, '_policy_insurer', true);
        $insurer_name = $insurer_id ? get_the_title($insurer_id) : 'N/A';
        $coverage_details = get_post_meta($sale->policy_id, '_policy_cover_details', true);
        $terms = get_post_meta($sale->policy_id, '_policy_terms', true);
        
        // Construire le HTML des détails
        ob_start();
        ?>
        <div class="policy-details">
            <div class="detail-section">
                <h4>📋 Policy Information</h4>
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td><strong>Policy Number:</strong></td><td><?php echo esc_html($sale->policy_number); ?></td></tr>
                    <tr><td><strong>Policy Name:</strong></td><td><?php echo esc_html($policy_title); ?></td></tr>
                    <tr><td><strong>Insurer:</strong></td><td><?php echo esc_html($insurer_name); ?></td></tr>
                    <tr><td><strong>Region:</strong></td><td><?php echo esc_html($sale->region); ?></td></tr>
                    <tr><td><strong>Premium:</strong></td><td><strong><?php echo esc_html($sale->premium); ?></strong></td></tr>
                    <tr><td><strong>Travel Dates:</strong></td><td><?php echo date('M j, Y', strtotime($sale->departure)) . ' - ' . date('M j, Y', strtotime($sale->return)); ?></td></tr>
                    <tr><td><strong>Duration:</strong></td><td><?php echo $sale->days; ?> days</td></tr>
                    <tr><td><strong>Status:</strong></td><td><span class="status-badge status-<?php echo esc_attr($sale->policy_status); ?>"><?php echo ucfirst($sale->policy_status); ?></span></td></tr>
                </table>
            </div>
            
            <div class="detail-section">
                <h4>👤 Insured Person</h4>
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td><strong>Full Name:</strong></td><td><?php echo esc_html($sale->insured_names); ?></td></tr>
                    <tr><td><strong>Date of Birth:</strong></td><td><?php echo esc_html($sale->insured_dob); ?></td></tr>
                    <tr><td><strong>Email:</strong></td><td><?php echo esc_html($sale->insured_email); ?></td></tr>
                    <tr><td><strong>Phone:</strong></td><td><?php echo esc_html($sale->insured_phone); ?></td></tr>
                    <tr><td><strong>Passport Number:</strong></td><td><?php echo esc_html($sale->passport_number); ?></td></tr>
                    <tr><td><strong>National ID:</strong></td><td><?php echo esc_html($sale->national_id); ?></td></tr>
                    <tr><td><strong>Address:</strong></td><td><?php echo esc_html($sale->insured_address); ?></td></tr>
                    <tr><td><strong>Country:</strong></td><td><?php echo esc_html($sale->country_of_origin); ?></td></tr>
                </table>
            </div>
            
            <div class="detail-section">
                <h4>💳 Payment Information</h4>
                <table style="width:100%;border-collapse:collapse;">
                    <tr><td><strong>Amount Paid:</strong></td><td><?php echo esc_html($sale->amount_paid); ?></td></tr>
                    <tr><td><strong>Payment Reference:</strong></td><td><?php echo esc_html($sale->payment_reference); ?></td></tr>
                    <tr><td><strong>Payment Status:</strong></td><td><span class="status-badge status-<?php echo esc_attr($sale->payment_status); ?>"><?php echo ucfirst($sale->payment_status); ?></span></td></tr>
                    <tr><td><strong>Date Applied:</strong></td><td><?php echo date('M j, Y g:i A', strtotime($sale->created_at)); ?></td></tr>
                </table>
            </div>
            
            <?php if ($coverage_details): ?>
            <div class="detail-section">
                <h4>🛡️ Coverage Details</h4>
                <div style="background:#f8f9fa;padding:15px;border-radius:6px;">
                    <?php echo wpautop(esc_html($coverage_details)); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="detail-section">
                <h4>📄 Actions</h4>
                <a href="<?php echo plugin_dir_url(__FILE__) . 'generate-policy-pdf.php?sale_id=' . $sale->id; ?>" 
                   target="_blank" class="btn btn-primary" style="margin-right:10px;">
                    📄 Download PDF
                </a>
                <button onclick="document.getElementById('policy-modal').remove();" class="btn btn-secondary">
                    Close
                </button>
            </div>
        </div>
        
        <style>
        .detail-section {
            margin-bottom: 25px;
        }
        .detail-section h4 {
            margin: 0 0 15px 0;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
        }
        .detail-section table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-section table td:first-child {
            width: 30%;
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-unconfirmed { background: #f8d7da; color: #721c24; }
        </style>
        <?php
        
        $html = ob_get_clean();
        wp_send_json_success($html);
    }
    
    private function handle_policy_view() {
        // Fonction pour affichage détaillé d'une police (utilisée via AJAX)
        // Implémentée dans handle_policy_details_ajax()
    }
}

// Initialiser la classe
new Maljani_User_Dashboard();
