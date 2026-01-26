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
        );
        register_post_type('policy', $args);

        // Taxonomie "Regions"
        register_taxonomy(
            'policy_region',
            'policy',
            array(
                'label'        => 'Regions',
                'rewrite'      => array('slug' => 'policy-region'),
                'hierarchical' => false,
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

    // Affiche la metabox
    public function render_meta_box($post) {
        wp_nonce_field('policy_meta_box', 'policy_meta_box_nonce');
        
        // Récupération des valeurs existantes
        $insurer_id = get_post_meta($post->ID, '_policy_insurer', true);
        $region = get_post_meta($post->ID, '_policy_region', true);
        $description = get_post_meta($post->ID, '_policy_description', true);
        $cover_details = get_post_meta($post->ID, '_policy_cover_details', true);
        $benefits = get_post_meta($post->ID, '_policy_benefits', true);
        $not_covered = get_post_meta($post->ID, '_policy_not_covered', true);
        $day_premiums = get_post_meta($post->ID, '_policy_day_premiums', true);
        $feature_img_id = get_post_meta($post->ID, '_policy_feature_img', true);
        $feature_img_url = $feature_img_id ? wp_get_attachment_url($feature_img_id) : '';

        echo '<style>
            .policy-form-section {
                background: #fff;
                border: 1px solid #ddd;
                padding: 20px;
                margin-bottom: 20px;
            }
            .policy-form-section h3 {
                margin: 0 0 15px 0;
                padding-bottom: 10px;
                border-bottom: 2px solid #1e5c3a;
                color: #222;
            }
            .policy-form-row {
                margin-bottom: 15px;
            }
            .policy-form-row label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
                color: #222;
            }
            .policy-form-row input[type="text"],
            .policy-form-row select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
            }
            .policy-premium-table {
                width: 100%;
                border-collapse: collapse;
            }
            .policy-premium-table th {
                background: #f5f5f5;
                padding: 10px;
                text-align: left;
                border: 1px solid #ddd;
                font-weight: 600;
            }
            .policy-premium-table td {
                padding: 8px;
                border: 1px solid #ddd;
            }
            .policy-premium-table input {
                width: 100%;
                padding: 6px;
                border: 1px solid #ddd;
            }
            .policy-btn-primary {
                background: #1e5c3a;
                color: #fff;
                border: none;
                padding: 8px 16px;
                cursor: pointer;
                font-weight: 500;
            }
            .policy-btn-primary:hover {
                opacity: 0.9;
            }
            .policy-btn-secondary {
                background: #fff;
                color: #222;
                border: 1px solid #ddd;
                padding: 8px 16px;
                cursor: pointer;
            }
            .policy-feature-img-preview {
                max-width: 150px;
                max-height: 225px;
                border: 1px solid #ddd;
                display: block;
                margin: 10px 0;
            }
            .policy-info-box {
                background: #f0f8ff;
                padding: 15px;
                border-left: 4px solid #1e5c3a;
                margin: 20px 0;
            }
        </style>';

        // Section 1: Basic Information
        echo '<div class="policy-form-section">';
        echo '<h3>Basic Information</h3>';
        
        // Sélecteur d'assureur
        $insurers = get_posts(array(
            'post_type' => 'insurer_profile',
            'numberposts' => -1,
            'post_status' => 'publish'
        ));
        echo '<div class="policy-form-row">';
        echo '<label for="policy_insurer">Insurer</label>';
        echo '<select id="policy_insurer" name="policy_insurer">';
        echo '<option value="">-- Select Insurer --</option>';
        foreach ($insurers as $insurer) {
            $selected = ($insurer_id == $insurer->ID) ? 'selected' : '';
            echo '<option value="' . esc_attr($insurer->ID) . '" ' . $selected . '>' . esc_html($insurer->post_title) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Région
        $regions = get_terms(array(
            'taxonomy' => 'policy_region',
            'hide_empty' => false,
        ));
        $current_regions = wp_get_post_terms($post->ID, 'policy_region', array('fields' => 'ids'));
        echo '<div class="policy-form-row">';
        echo '<label for="policy_region">Region</label>';
        echo '<div style="display:flex;gap:10px;align-items:center;">';
        echo '<select id="policy_region" name="policy_region" style="flex:1;">';
        echo '<option value="">-- Select Region --</option>';
        foreach ($regions as $region) {
            $selected = in_array($region->term_id, $current_regions) ? 'selected' : '';
            echo '<option value="' . esc_attr($region->term_id) . '" ' . $selected . '>' . esc_html($region->name) . '</option>';
        }
        echo '</select>';
        echo '<input type="text" id="new_policy_region" placeholder="Add new region" style="flex:1;" />';
        echo '<button type="button" id="add_policy_region" class="policy-btn-primary">Add</button>';
        echo '</div>';
        echo '</div>';

        // Description
        echo '<div class="policy-form-row">';
        echo '<label for="policy_description">Short Description</label>';
        echo '<input type="text" id="policy_description" name="policy_description" value="' . esc_attr($description) . '" placeholder="Brief description of this policy" />';
        echo '</div>';
        
        echo '</div>'; // End Basic Information

        // Section 2: Feature Image
        echo '<div class="policy-form-section">';
        echo '<h3>Feature Image</h3>';
        echo '<div class="policy-form-row">';
        echo '<label>Policy Image (recommended: portrait 4:6 ratio)</label>';
        echo '<input type="hidden" id="policy_feature_img" name="policy_feature_img" value="' . esc_attr($feature_img_id) . '" />';
        if ($feature_img_url) {
            echo '<img id="policy_feature_img_preview" src="' . esc_url($feature_img_url) . '" class="policy-feature-img-preview" />';
        } else {
            echo '<img id="policy_feature_img_preview" src="" class="policy-feature-img-preview" style="display:none;" />';
        }
        echo '<button type="button" class="policy-btn-primary" id="upload_policy_feature_img">Upload Image</button>';
        echo '<button type="button" class="policy-btn-secondary" id="remove_policy_feature_img" style="margin-left:10px;">Remove</button>';
        echo '</div>';
        echo '</div>'; // End Feature Image

        // Section 3: Policy Details
        echo '<div class="policy-form-section">';
        echo '<h3>Policy Details</h3>';
        
        // Cover details (WYSIWYG)
        echo '<div class="policy-form-row">';
        echo '<label for="policy_cover_details">Cover Details</label>';
        wp_editor($cover_details, 'policy_cover_details', array(
            'textarea_name' => 'policy_cover_details',
            'textarea_rows' => 8,
            'media_buttons' => true,
            'teeny' => false,
            'tinymce' => array(
                'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,link,unlink,|,undo,redo',
                'toolbar2' => 'formatselect,|,forecolor,backcolor,|,alignleft,aligncenter,alignright,alignjustify,|,outdent,indent',
                'resize' => true,
                'menubar' => false,
                'height' => 200
            ),
            'quicktags' => array(
                'buttons' => 'strong,em,ul,ol,li,link,block,del,ins,img,more,code'
            )
        ));
        echo '</div>';

        // Benefits (WYSIWYG)
        echo '<div class="policy-form-row">';
        echo '<label for="policy_benefits">Benefits</label>';
        wp_editor($benefits, 'policy_benefits', array(
            'textarea_name' => 'policy_benefits',
            'textarea_rows' => 8,
            'media_buttons' => true,
            'teeny' => false,
            'tinymce' => array(
                'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,link,unlink,|,undo,redo',
                'toolbar2' => 'formatselect,|,forecolor,backcolor,|,alignleft,aligncenter,alignright,alignjustify,|,outdent,indent',
                'resize' => true,
                'menubar' => false,
                'height' => 200
            ),
            'quicktags' => array(
                'buttons' => 'strong,em,ul,ol,li,link,block,del,ins,img,more,code'
            )
        ));
        echo '</div>';

        // What is not covered
        echo '<div class="policy-form-row">';
        echo '<label for="policy_not_covered">What is Not Covered</label>';
        wp_editor($not_covered, 'policy_not_covered', array(
            'textarea_name' => 'policy_not_covered',
            'textarea_rows' => 8,
            'media_buttons' => true,
            'teeny' => false,
            'tinymce' => array(
                'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,link,unlink,|,undo,redo',
                'toolbar2' => 'formatselect,|,forecolor,backcolor,|,alignleft,aligncenter,alignright,alignjustify,|,outdent,indent',
                'resize' => true,
                'menubar' => false,
                'height' => 200
            ),
            'quicktags' => array(
                'buttons' => 'strong,em,ul,ol,li,link,block,del,ins,img,more,code'
            )
        ));
        echo '</div>';
        echo '</div>'; // End Policy Details

        // Section 4: Pricing
        echo '<div class="policy-form-section">';
        echo '<h3>Pricing Structure</h3>';
        echo '<div class="policy-form-row">';
        echo '<label>Day Range & Premiums</label>';
        echo '<p style="color:#666;margin:5px 0 10px 0;">Define premium amounts based on travel duration ranges</p>';
        echo '<table class="policy-premium-table" id="day-premium-table">';
        echo '<thead><tr><th>From (days)</th><th>To (days)</th><th>Premium Amount</th><th style="width:80px;">Action</th></tr></thead><tbody>';
        if (!empty($day_premiums) && is_array($day_premiums)) {
            foreach ($day_premiums as $row) {
                echo '<tr>
                    <td><input type="number" name="day_premium_from[]" value="' . esc_attr($row['from']) . '" min="1" placeholder="1" /></td>
                    <td><input type="number" name="day_premium_to[]" value="' . esc_attr($row['to']) . '" min="1" placeholder="365" /></td>
                    <td><input type="number" name="day_premium_amount[]" value="' . esc_attr($row['premium']) . '" min="0" step="0.01" placeholder="0.00" /></td>
                    <td><button type="button" class="remove-row policy-btn-secondary">Remove</button></td>
                </tr>';
            }
        } else {
            // Ligne vide par défaut
            echo '<tr>
                <td><input type="number" name="day_premium_from[]" value="" min="1" placeholder="1" /></td>
                <td><input type="number" name="day_premium_to[]" value="" min="1" placeholder="365" /></td>
                <td><input type="number" name="day_premium_amount[]" value="" min="0" step="0.01" placeholder="0.00" /></td>
                <td><button type="button" class="remove-row policy-btn-secondary">Remove</button></td>
            </tr>';
        }
        echo '</tbody></table>';
        echo '<button type="button" id="add-day-premium-row" class="policy-btn-primary" style="margin-top:10px;">Add Pricing Row</button>';
        echo '</div>';
        echo '</div>'; // End Pricing

        // Section 5: Additional Information
        echo '<div class="policy-form-section">';
        echo '<h3>Additional Information</h3>';
        
        // Payment details
        $payment_details = get_post_meta($post->ID, '_policy_payment_details', true);
        echo '<div class="policy-form-row">';
        echo '<label for="policy_payment_details">Payment Details (Private - Not Published)</label>';
        echo '<textarea id="policy_payment_details" name="policy_payment_details" rows="4" style="width:100%;padding:8px;border:1px solid #ddd;">' . esc_textarea($payment_details) . '</textarea>';
        echo '<p style="color:#666;margin:5px 0 0 0;">Internal notes about payment processing, commissions, etc.</p>';
        echo '</div>';
        
        // Info box
        echo '<div class="policy-info-box">';
        echo '<p style="margin:0;"><strong>Note:</strong> A public quote form with departure/return dates will automatically display on this policy\'s public page.</p>';
        echo '</div>';
        
        echo '</div>'; // End Additional Information
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
            // Pass AJAX data to JavaScript
            wp_localize_script('policy-admin-js', 'policyAdmin', array(
                'nonce' => wp_create_nonce('add_policy_region_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php')
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
