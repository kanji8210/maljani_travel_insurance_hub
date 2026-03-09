<?php
/**
 * Maljani Client Dashboard - Portal for Insured Users
 */

class Maljani_Client_Dashboard {

    public static function init() {
        return new self();
    }

    public function __construct() {
        add_shortcode('maljani_client_dashboard', [$this, 'render_dashboard']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'maljani_client_dashboard')) {
            wp_enqueue_style('maljani-client-dashboard', plugin_dir_url(__FILE__) . 'css/maljani-client-dashboard.css', [], time());
            // Reuse some common dashboard logic if it exists
            wp_enqueue_script('maljani-client-dashboard', plugin_dir_url(__FILE__) . 'js/maljani-client-dashboard.js', ['jquery'], time(), true);
        }
    }

    public function render_dashboard() {
        if (!is_user_logged_in()) {
            return '<div class="maljani-dashboard-msg">Please log in to view your insurance portal.</div>';
        }

        $current_user = wp_get_current_user();
        $policies = $this->get_user_policies();

        ob_start();
        ?>
        <div class="maljani-client-dashboard">
            <header class="client-header">
                <div class="header-welcome">
                    <span class="hub-pill">Policyholder Portal</span>
                    <h2>Hello, <?php echo esc_html($current_user->first_name ?: $current_user->display_name); ?></h2>
                    <p>Manage your active travel insurance policies and documents.</p>
                </div>
                <div class="header-stats">
                    <div class="mini-stat">
                        <span class="stat-num"><?php echo count($policies); ?></span>
                        <span class="stat-label">Total Policies</span>
                    </div>
                </div>
            </header>

            <nav class="client-tabs">
                <button class="client-tab active" data-target="my-policies">My Policies</button>
                <button class="client-tab" data-target="my-profile">Profile Settings</button>
                <button class="client-tab" data-target="get-support">Support</button>
            </nav>

            <div class="client-content">
                <!-- POLICIES VIEW -->
                <section id="my-policies" class="client-section active">
                    <?php if (empty($policies)): ?>
                        <div class="empty-state">
                            <span class="empty-icon">🛡️</span>
                            <h3>No policies found</h3>
                            <p>You haven't purchased any travel insurance policies yet.</p>
                            <a href="<?php echo home_url('/buy'); ?>" class="mj-btn-primary">Browse Plans</a>
                        </div>
                    <?php else: ?>
                        <div class="policy-grid">
                            <?php foreach ($policies as $policy): ?>
                                <div class="policy-card glass-morphism">
                                    <div class="policy-card-header">
                                        <span class="policy-id">#<?php echo esc_html($policy->policy_number); ?></span>
                                        <span class="status-badge <?php echo esc_attr($policy->policy_status); ?>">
                                            <?php echo ucfirst(esc_html($policy->policy_status)); ?>
                                        </span>
                                    </div>
                                    <div class="policy-card-body">
                                        <h4><?php echo esc_html($policy->insured_names); ?></h4>
                                        <div class="policy-meta">
                                            <div class="meta-row"><span>Region:</span> <strong><?php echo esc_html($policy->region); ?></strong></div>
                                            <div class="meta-row"><span>Dates:</span> <strong><?php echo esc_html($policy->departure); ?> - <?php echo esc_html($policy->return); ?></strong></div>
                                        </div>
                                    </div>
                                    <div class="policy-card-footer">
                                        <?php if ($policy->policy_status === 'active'): ?>
                                            <a href="?maljani_action=download_pdf&id=<?php echo $policy->id; ?>" class="btn-download">Download PDF</a>
                                        <?php else: ?>
                                            <span class="pending-note">Available once activated</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- PROFILE VIEW -->
                <section id="my-profile" class="client-section">
                    <div class="profile-form-container">
                        <h3>Account Details</h3>
                        <!-- Profile editing logic would be integrated here -->
                        <p>Contact your agency or support to update primary account information.</p>
                        <div class="user-info-card">
                            <p><strong>Email:</strong> <?php echo esc_html($current_user->user_email); ?></p>
                            <p><strong>Username:</strong> <?php echo esc_html($current_user->user_login); ?></p>
                        </div>
                    </div>
                </section>

                <!-- SUPPORT VIEW -->
                <section id="get-support" class="client-section">
                    <div class="support-welcome">
                        <h3>Need Assistance?</h3>
                        <p>Our support team is available to help you with any questions regarding your coverage.</p>
                        <div class="support-options">
                            <button class="mj-btn-primary" onclick="window.maljaniChat.open()">Start Live Chat</button>
                            <p class="support-email">Or email us at: support@maljani.com</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('.client-tab').click(function() {
                    var target = $(this).data('target');
                    $('.client-tab').removeClass('active');
                    $(this).addClass('active');
                    $('.client-section').removeClass('active');
                    $('#' + target).addClass('active');
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    private function get_user_policies() {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        
        // Link by user_id or email
        $user_id = get_current_user_id();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC", 
            $user_id
        ));
    }
}

if (defined('ABSPATH')) { Maljani_Client_Dashboard::init(); }
