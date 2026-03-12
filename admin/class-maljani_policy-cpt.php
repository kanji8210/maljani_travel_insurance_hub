<?php
class Policy_CPT {

    // Enregistre le CPT et la taxonomie
    public function register_Insurance_Policy() {
        $labels = array(
            'name'               => 'Policies',
            'singular_name'      => 'Policy',
            'menu_name'          => 'Policies',
            'name_admin_bar'     => 'Policy',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Policy',
            'new_item'           => 'New Policy',
            'edit_item'          => 'Edit Policy',
            'view_item'          => 'View Policy',
            'all_items'          => 'All Policies',
            'search_items'       => 'Search Policies',
            'not_found'          => 'No policies found.',
            'not_found_in_trash' => 'No policies found in Trash.'
        );
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'rewrite'            => array('slug' => 'policy'),
            'supports'           => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'show_in_rest'       => true,
            'show_in_menu'       => 'maljani_travel',
        );
        register_post_type('policy', $args);

        // Taxonomie "Regions"
        register_taxonomy(
            'policy_region',
            'policy',
            array(
                'label'        => 'Regions',
                'rewrite'      => array('slug' => 'policy-region'),
                'hierarchical' => true,
                'show_in_rest' => true,
                'show_admin_column' => true,
            )
        );

        // Hooks pour metaboxes, scripts et AJAX
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        if (is_admin()) {
            add_action('wp_ajax_add_policy_region', array($this, 'ajax_add_policy_region'));
        }
    }

    // Ajoute la metabox
    public function add_meta_boxes() {
        add_meta_box(
            'policy_details',
            'Policy Details',
            array($this, 'render_meta_box'),
            'policy',
            'normal',
            'default'
        );
    }

    // Affiche la metabox avec une interface à onglets premium
    public function render_meta_box($post) {
        wp_nonce_field('policy_meta_box', 'policy_meta_box_nonce');
        
        // Récupération des valeurs existantes
        $insurer_id = get_post_meta($post->ID, '_policy_insurer', true);
        $description = get_post_meta($post->ID, '_policy_description', true);
        $cover_details = get_post_meta($post->ID, '_policy_cover_details', true);
        $benefits = get_post_meta($post->ID, '_policy_benefits', true);
        $not_covered = get_post_meta($post->ID, '_policy_not_covered', true);
        $day_premiums = get_post_meta($post->ID, '_policy_day_premiums', true);
        $feature_img_id = get_post_meta($post->ID, '_policy_feature_img', true);
        $feature_img_url = $feature_img_id ? wp_get_attachment_url($feature_img_id) : '';
        $currency = get_post_meta($post->ID, '_policy_currency', true) ?: 'KSH';
        $payment_details = get_post_meta($post->ID, '_policy_payment_details', true);

        ?>
        <style>
            .maljani-policy-tabs {
                display: flex;
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
                margin: -6px -12px 20px -12px;
            }
            .maljani-tab-link {
                padding: 14px 24px;
                cursor: pointer;
                font-weight: 600;
                color: #64748b;
                border-bottom: 2px solid transparent;
                transition: all 0.2s;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .maljani-tab-link:hover { color: #4f46e5; background: rgba(79, 70, 229, 0.05); }
            .maljani-tab-link.active {
                color: #4f46e5;
                border-bottom-color: #4f46e5;
                background: white;
            }
            .maljani-tab-content { display: none; padding: 10px 0; }
            .maljani-tab-content.active { display: block; animation: mjFadeIn 0.3s ease; }
            
            .mj-form-group { margin-bottom: 24px; }
            .mj-form-group label { display: block; font-weight: 700; color: #1e293b; margin-bottom: 8px; font-size: 14px; }
            .mj-input { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; transition: border-color 0.2s; }
            .mj-input:focus { border-color: #4f46e5; outline: none; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
            
            .mj-premium-table { width: 100%; border-collapse: collapse; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
            .mj-premium-table th { background: #f8fafc; padding: 12px; text-align: left; font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase; }
            .mj-premium-table td { padding: 10px; border-top: 1px solid #e2e8f0; }
            
            .mj-btn { padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; font-size: 13px; }
            .mj-btn-primary { background: #4f46e5; color: white; }
            .mj-btn-primary:hover { background: #4338ca; transform: translateY(-1px); }
            .mj-btn-secondary { background: #f1f5f9; color: #475569; }
            .mj-btn-secondary:hover { background: #e2e8f0; }
            
            .img-preview-box { 
                width: 120px; height: 160px; background: #f1f5f9; border: 2px dashed #cbd5e1; 
                border-radius: 12px; display: flex; align-items: center; justify-content: center;
                margin-bottom: 15px; overflow: hidden;
            }
            .img-preview-box img { width: 100%; height: 100%; object-fit: cover; }
            
            @keyframes mjFadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        </style>

        <div class="maljani-policy-admin-wrapper">
            <div class="maljani-policy-tabs">
                <div class="maljani-tab-link active" data-tab="basic">Basic Info</div>
                <div class="maljani-tab-link" data-tab="coverage">Coverage & Benefits</div>
                <div class="maljani-tab-link" data-tab="pricing">Pricing Structure</div>
                <div class="maljani-tab-link" data-tab="media">Media & Notes</div>
            </div>

            <!-- Tab: Basic -->
            <div id="tab-basic" class="maljani-tab-content active">
                <div class="mj-form-group">
                    <label>Assigned Insurer</label>
                    <select name="policy_insurer" class="mj-input">
                        <option value="">-- Select Insurer --</option>
                        <?php
                        $insurers = get_posts(['post_type' => 'insurer_profile', 'numberposts' => -1]);
                        foreach ($insurers as $ins) {
                            printf('<option value="%d" %s>%s</option>', $ins->ID, selected($insurer_id, $ins->ID, false), esc_html($ins->post_title));
                        }
                        ?>
                    </select>
                </div>
                <div class="mj-form-group">
                    <label>Short Description (Marketing Hook)</label>
                    <input type="text" name="policy_description" value="<?php echo esc_attr($description); ?>" class="mj-input" placeholder="e.g. Best coverage for European travel">
                </div>
                <div class="mj-form-group">
                    <label>Primary Region (Taxonomy)</label>
                    <?php
                    $regions = get_terms(['taxonomy' => 'policy_region', 'hide_empty' => false]);
                    $current_regions = wp_get_post_terms($post->ID, 'policy_region', ['fields' => 'ids']);
                    ?>
                    <div style="display:flex; gap:10px;">
                        <select name="policy_region" class="mj-input" style="flex:1;">
                            <option value="">-- Select Region --</option>
                            <?php foreach ($regions as $reg) : ?>
                                <option value="<?php echo $reg->term_id; ?>" <?php echo in_array($reg->term_id, $current_regions) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($reg->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="new_policy_region" placeholder="Quick add new..." class="mj-input" style="flex:1;">
                        <button type="button" id="add_policy_region" class="mj-btn mj-btn-primary">Add</button>
                    </div>
                </div>
            </div>

            <!-- Tab: Coverage -->
            <div id="tab-coverage" class="maljani-tab-content">
                <div class="mj-form-group">
                    <label>Coverage Details</label>
                    <?php wp_editor($cover_details, 'policy_cover_details', ['textarea_rows' => 10]); ?>
                </div>
                <div class="mj-form-group">
                    <label>Included Benefits</label>
                    <?php wp_editor($benefits, 'policy_benefits', ['textarea_rows' => 10]); ?>
                </div>
                <div class="mj-form-group">
                    <label>What is NOT covered</label>
                    <?php wp_editor($not_covered, 'policy_not_covered', ['textarea_rows' => 5]); ?>
                </div>
            </div>

            <!-- Tab: Pricing -->
            <div id="tab-pricing" class="maljani-tab-content">
                <div class="mj-form-group">
                    <label>Display Currency</label>
                    <select name="policy_currency" class="mj-input" style="max-width:200px;">
                        <option value="KSH" <?php selected($currency, 'KSH'); ?>>KSH</option>
                        <option value="USD" <?php selected($currency, 'USD'); ?>>USD</option>
                        <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR</option>
                    </select>
                </div>
                <!-- New Financial Fields -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                    <div class="mj-form-group">
                        <label>Aggregator Commission (%)</label>
                        <input type="number" name="aggregator_commission_pct" value="<?php echo esc_attr(get_post_meta($post->ID, '_policy_aggregator_comm_pct', true) ?: '0'); ?>" step="0.01" class="mj-input" placeholder="e.g. 15.00">
                        <p class="description">What insurer pays Maljani.</p>
                    </div>
                    <div class="mj-form-group">
                        <label>Agency Commission (%)</label>
                        <input type="number" name="agency_commission_pct" value="<?php echo esc_attr(get_post_meta($post->ID, '_policy_agency_comm_pct', true) ?: '0'); ?>" step="0.01" class="mj-input" placeholder="e.g. 10.00">
                        <p class="description">Paid to agency by insurer.</p>
                    </div>
                    <div class="mj-form-group">
                        <label>Client Service Fee (%)</label>
                        <input type="number" name="client_service_fee_pct" value="<?php echo esc_attr(get_post_meta($post->ID, '_policy_client_fee_pct', true) ?: '0'); ?>" step="0.01" class="mj-input" placeholder="e.g. 5.00">
                        <p class="description">Added to client's price.</p>
                    </div>
                </div>
                <div class="mj-form-group">
                    <label>Pricing Rules (Duration based)</label>
                    <table class="mj-premium-table" id="day-premium-table">
                        <thead>
                            <tr>
                                <th>Min Days</th>
                                <th>Max Days</th>
                                <th>Premium Amount</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($day_premiums)) : foreach ($day_premiums as $row) : ?>
                                <tr>
                                    <td><input type="number" name="day_premium_from[]" value="<?php echo esc_attr($row['from']); ?>" class="mj-input"></td>
                                    <td><input type="number" name="day_premium_to[]" value="<?php echo esc_attr($row['to']); ?>" class="mj-input"></td>
                                    <td><input type="number" name="day_premium_amount[]" value="<?php echo esc_attr($row['premium']); ?>" step="0.01" class="mj-input"></td>
                                    <td><button type="button" class="remove-row mj-btn mj-btn-secondary">&times;</button></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr>
                                    <td><input type="number" name="day_premium_from[]" value="1" class="mj-input"></td>
                                    <td><input type="number" name="day_premium_to[]" value="30" class="mj-input"></td>
                                    <td><input type="number" name="day_premium_amount[]" value="0.00" step="0.01" class="mj-input"></td>
                                    <td><button type="button" class="remove-row mj-btn mj-btn-secondary">&times;</button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="button" id="add-day-premium-row" class="mj-btn mj-btn-primary" style="margin-top:15px; width: 100%;">+ Add Duration Bracket</button>
                </div>
            </div>

            <!-- Tab: Media -->
            <div id="tab-media" class="maljani-tab-content">
                <div class="mj-form-group">
                    <label>Policy Feature Image</label>
                    <div class="img-preview-box" id="policy_feature_img_preview_container">
                        <?php if ($feature_img_url) : ?>
                            <img src="<?php echo esc_url($feature_img_url); ?>" id="policy_feature_img_preview">
                        <?php else : ?>
                            <span style="color:#94a3b8; font-size:40px;">🖼️</span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="policy_feature_img" id="policy_feature_img" value="<?php echo esc_attr($feature_img_id); ?>">
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="mj-btn mj-btn-primary" id="upload_policy_feature_img">Select Image</button>
                        <button type="button" class="mj-btn mj-btn-secondary" id="remove_policy_feature_img">Remove</button>
                    </div>
                </div>
                <div class="mj-form-group">
                    <label>Internal Notes (Private)</label>
                    <textarea name="policy_payment_details" class="mj-input" rows="5" placeholder="Internal underwriting or payment notes..."><?php echo esc_textarea($payment_details); ?></textarea>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.maljani-tab-link').click(function() {
                var tab = $(this).data('tab');
                $('.maljani-tab-link').removeClass('active');
                $(this).addClass('active');
                $('.maljani-tab-content').removeClass('active');
                $('#tab-' + tab).addClass('active');
            });
        });
        </script>
        ?>
        <?php
    }

    // Sauvegarde des champs personnalisés
    public function save_meta_boxes($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['policy_meta_box_nonce']) || !wp_verify_nonce($_POST['policy_meta_box_nonce'], 'policy_meta_box')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        if (isset($_POST['policy_insurer'])) {
            update_post_meta($post_id, '_policy_insurer', intval($_POST['policy_insurer']));
        }
        if (isset($_POST['policy_description'])) {
            update_post_meta($post_id, '_policy_description', sanitize_text_field($_POST['policy_description']));
        }
        if (isset($_POST['policy_cover_details'])) {
            update_post_meta($post_id, '_policy_cover_details', wp_kses_post($_POST['policy_cover_details']));
        }
        if (isset($_POST['policy_benefits'])) {
            update_post_meta($post_id, '_policy_benefits', wp_kses_post($_POST['policy_benefits']));
        }
        if (isset($_POST['policy_not_covered'])) {
            update_post_meta($post_id, '_policy_not_covered', wp_kses_post($_POST['policy_not_covered']));
        }
        // Save currency
        if (isset($_POST['policy_currency'])) {
            update_post_meta($post_id, '_policy_currency', sanitize_text_field($_POST['policy_currency']));
        }
        // Sauvegarde du tableau dynamique
        $premiums = array();
        if (isset($_POST['day_premium_from'], $_POST['day_premium_to'], $_POST['day_premium_amount'])) {
            $from = $_POST['day_premium_from'];
            $to = $_POST['day_premium_to'];
            $amount = $_POST['day_premium_amount'];
            for ($i = 0; $i < count($from); $i++) {
                if ($from[$i] !== '' && $to[$i] !== '' && $amount[$i] !== '') {
                    $premiums[] = array(
                        'from' => intval($from[$i]),
                        'to' => intval($to[$i]),
                        'premium' => floatval($amount[$i])
                    );
                }
            }
        }
        update_post_meta($post_id, '_policy_day_premiums', $premiums);

        // Save Financial Settings
        if (isset($_POST['aggregator_commission_pct'])) {
            update_post_meta($post_id, '_policy_aggregator_comm_pct', floatval($_POST['aggregator_commission_pct']));
        }
        if (isset($_POST['agency_commission_pct'])) {
            update_post_meta($post_id, '_policy_agency_comm_pct', floatval($_POST['agency_commission_pct']));
        }
        if (isset($_POST['client_service_fee_pct'])) {
            update_post_meta($post_id, '_policy_client_fee_pct', floatval($_POST['client_service_fee_pct']));
        }

        if (isset($_POST['policy_feature_img'])) {
            update_post_meta($post_id, '_policy_feature_img', intval($_POST['policy_feature_img']));
        }
        if (isset($_POST['policy_region'])) {
            // Sauvegarde dans la taxonomie (déjà présent)
            wp_set_post_terms($post_id, array(intval($_POST['policy_region'])), 'policy_region');
            // Sauvegarde aussi dans le champ meta pour les requêtes meta_query
            update_post_meta($post_id, '_policy_region', intval($_POST['policy_region']));
        }
        if (isset($_POST['policy_payment_details'])) {
            update_post_meta($post_id, '_policy_payment_details', wp_kses_post($_POST['policy_payment_details']));
        }
    }

    // Charge les scripts nécessaires à l'admin
    public function enqueue_admin_scripts($hook) {
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            wp_enqueue_style('wp-jquery-ui-dialog');
            wp_enqueue_media();
            wp_enqueue_script(
                'policy-admin-js',
                plugin_dir_url(__FILE__) . 'js/policy-admin.js',
                array('jquery'),
                null,
                true
            );
            // Pass AJAX data to JavaScript — include both legacy keys and new ones
            wp_localize_script('policy-admin-js', 'policyAdmin', array(
                'nonce' => wp_create_nonce('add_policy_region_nonce'),
                'security' => wp_create_nonce('add_policy_region_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ajax_url' => admin_url('admin-ajax.php')
            ));
        }
    }

    // Handler AJAX pour ajout rapide de région
    public function ajax_add_policy_region() {
        // Vérification des capacités et du nonce
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['security'], 'add_policy_region_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $region = sanitize_text_field($_POST['region']);
        if (empty($region)) {
            wp_send_json_error('Region name is required');
        }
        
        $term = wp_insert_term($region, 'policy_region');
        if (!is_wp_error($term)) {
            wp_send_json_success(array(
                'term_id' => $term['term_id'],
                'name' => $region
            ));
        } else {
            wp_send_json_error('Error creating region: ' . $term->get_error_message());
        }
    }
}
