<?php
// insurer profile as CPT
class Insurer_Profile_CPT {
    public function register_Insurer(){
        $labels = array(
            'name'               => 'Insurer Profiles',
            'singular_name'      => 'Insurer Profile',
            'menu_name'          => 'Insurer Profiles',
            'name_admin_bar'     => 'Insurer Profile',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Insurer Profile',
            'new_item'           => 'New Insurer Profile',
            'edit_item'          => 'Edit Insurer Profile',
            'view_item'          => 'View Insurer Profile',
            'all_items'          => 'All Insurer Profiles',
            'search_items'       => 'Search Insurer Profiles',
            'not_found'          => 'No insurer profiles found.',
            'not_found_in_trash' => 'No insurer profiles found in Trash.'
        );
        $args =array(
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'rewrite'            => array('slug' => 'insurer-profile'),
            'supports'           => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'show_in_rest'       => true,
            'show_in_menu'       => 'maljani_travel',
        );
        //add wp function
        register_post_type('insurer_profile', $args);

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'insurer_profile_details',
            'Insurer Details',
            array($this, 'render_meta_box'),
            'insurer_profile',
            'normal',
            'default'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('insurer_profile_meta_box', 'insurer_profile_meta_box_nonce');

        // Récupérer les valeurs existantes
        $logo_id = get_post_meta($post->ID, '_insurer_logo_id', true);
        $logo_url_manual = get_post_meta($post->ID, '_insurer_logo', true);
        $logo_preview_url = $logo_id ? wp_get_attachment_url($logo_id) : $logo_url_manual;

        $name = get_post_meta($post->ID, '_insurer_name', true);
        $profile = get_post_meta($post->ID, '_insurer_profile', true);
        $feature_img_id = get_post_meta($post->ID, '_insurer_feature_img', true);
        $feature_img_url = $feature_img_id ? wp_get_attachment_url($feature_img_id) : '';
        $website = get_post_meta($post->ID, '_insurer_website', true);
        $linkedin = get_post_meta($post->ID, '_insurer_linkedin', true);
        $pesapal_id = get_post_meta($post->ID, '_insurer_pesapal_merchant_id', true);

        ?>
        <style>
            .maljani-admin-tabs {
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
            
            .img-preview-box { 
                width: 120px; height: 120px; background: #f1f5f9; border: 2px dashed #cbd5e1; 
                border-radius: 12px; display: flex; align-items: center; justify-content: center;
                margin-bottom: 15px; overflow: hidden;
            }
            .img-preview-box img { width: 100%; height: 100%; object-fit: cover; }
            .img-preview-box.feature-img { width: 180px; height: 120px; }
            
            .mj-btn { padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; font-size: 13px; }
            .mj-btn-primary { background: #4f46e5; color: white; }
            .mj-btn-primary:hover { background: #4338ca; transform: translateY(-1px); }
            .mj-btn-secondary { background: #f1f5f9; color: #475569; }
            .mj-btn-secondary:hover { background: #e2e8f0; }

            @keyframes mjFadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        </style>

        <div class="maljani-insurer-admin-wrapper">
            <div class="maljani-admin-tabs">
                <div class="maljani-tab-link active" data-tab="basic">Basic Info</div>
                <div class="maljani-tab-link" data-tab="profile">Profile & Media</div>
                <div class="maljani-tab-link" data-tab="links">External Links</div>
                <div class="maljani-tab-link" data-tab="api" style="color:#0ea5e9">API & Payment</div>
            </div>

            <!-- Tab: Basic -->
            <div id="tab-basic" class="maljani-tab-content active">
                <div class="mj-form-group">
                    <label for="insurer_name">Official Insurer Name</label>
                    <input type="text" id="insurer_name" name="insurer_name" value="<?php echo esc_attr($name); ?>" class="mj-input" placeholder="e.g. BlueShield Insurance" />
                </div>
                
                <div class="mj-form-group">
                    <label>Insurer Logo</label>
                    <div class="img-preview-box" id="insurer_logo_preview_container">
                        <?php if ($logo_preview_url) : ?>
                            <img src="<?php echo esc_url($logo_preview_url); ?>" id="insurer_logo_preview">
                        <?php else : ?>
                            <span style="color:#94a3b8; font-size:40px;">🏦</span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="insurer_logo_id" id="insurer_logo_id" value="<?php echo esc_attr($logo_id); ?>">
                    <div class="mj-form-group">
                        <label for="insurer_logo">Or Manual Logo URL</label>
                        <input type="text" id="insurer_logo" name="insurer_logo" value="<?php echo esc_attr($logo_url_manual); ?>" class="mj-input" placeholder="https://..." />
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="mj-btn mj-btn-primary" id="upload_insurer_logo">Upload Logo</button>
                        <button type="button" class="mj-btn mj-btn-secondary" id="remove_insurer_logo" <?php echo !$logo_id ? 'style="display:none;"' : ''; ?>>Remove</button>
                    </div>
                </div>
            </div>

            <!-- Tab: Profile -->
            <div id="tab-profile" class="maljani-tab-content">
                <div class="mj-form-group">
                    <label for="insurer_profile">Insurer Profile (Brief description)</label>
                    <?php
                    wp_editor(
                        $profile,
                        'insurer_profile',
                        array(
                            'textarea_name' => 'insurer_profile',
                            'media_buttons' => false,
                            'textarea_rows' => 8,
                            'teeny' => true
                        )
                    );
                    ?>
                    <p class="description">Recommended: Max 150 words for optimal display.</p>
                </div>

                <div class="mj-form-group">
                    <label>Feature Image (Portrait 6:4)</label>
                    <div class="img-preview-box feature-img" id="insurer_feature_img_preview_container">
                        <?php if ($feature_img_url) : ?>
                            <img src="<?php echo esc_url($feature_img_url); ?>" id="insurer_feature_img_preview">
                        <?php else : ?>
                            <span style="color:#94a3b8; font-size:40px;">🖼️</span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" id="insurer_feature_img" name="insurer_feature_img" value="<?php echo esc_attr($feature_img_id); ?>" />
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="mj-btn mj-btn-primary" id="upload_insurer_feature_img">Upload Image</button>
                        <button type="button" class="mj-btn mj-btn-secondary" id="remove_insurer_feature_img">Remove</button>
                    </div>
                </div>
            </div>

            <!-- Tab: Links -->
            <div id="tab-links" class="maljani-tab-content">
                <div class="mj-form-group">
                    <label for="insurer_website">Official Website</label>
                    <input type="url" id="insurer_website" name="insurer_website" value="<?php echo esc_attr($website); ?>" class="mj-input" placeholder="https://www.insurer.com" />
                </div>

                <div class="mj-form-group">
                    <label for="insurer_linkedin">LinkedIn Page</label>
                    <input type="url" id="insurer_linkedin" name="insurer_linkedin" value="<?php echo esc_attr($linkedin); ?>" class="mj-input" placeholder="https://linkedin.com/company/insurer" />
                </div>
            </div>

            <!-- Tab: API & Payment -->
            <div id="tab-api" class="maljani-tab-content">
                <div class="mj-form-group">
                    <label for="insurer_pesapal_id">Pesapal Merchant ID (for Split Payments)</label>
                    <input type="text" id="insurer_pesapal_id" name="insurer_pesapal_id" value="<?php echo esc_attr($pesapal_id); ?>" class="mj-input" placeholder="e.g. 5ca... " />
                    <p class="description">If provided, insurance premiums will be automatically routed to this Merchant ID via Pesapal Split Payment. If empty, funds are kept in Maljani's main account.</p>
                </div>
            </div>
        </div>

        <script>
        (function( $ ) {
            'use strict';

            $(document).ready(function() {
                // Tab switching logic
                $('.maljani-tab-link').click(function() {
                    var tab = $(this).data('tab');
                    $('.maljani-tab-link').removeClass('active');
                    $(this).addClass('active');
                    $('.maljani-tab-content').removeClass('active');
                    $('#tab-' + tab).addClass('active');
                });

                // Logo Upload logic
                $('#upload_insurer_logo').on('click', function(e){
                    e.preventDefault();
                    var frame = wp.media({
                        title: 'Select Insurer Logo',
                        button: { text: 'Use this logo' },
                        library: { type: 'image' },
                        multiple: false
                    });
                    frame.on('select', function(){
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#insurer_logo_id').val(attachment.id);
                        if ($('#insurer_logo_preview').length) {
                             $('#insurer_logo_preview').attr('src', attachment.url);
                        } else {
                             $('#insurer_logo_preview_container').html('<img src="' + attachment.url + '" id="insurer_logo_preview">');
                        }
                        $('#remove_insurer_logo').show();
                        $('#insurer_logo').val('');
                    });
                    frame.open();
                });

                $('#remove_insurer_logo').on('click', function(e){
                    e.preventDefault();
                    $('#insurer_logo_id').val('');
                    $('#insurer_logo_preview_container').html('<span style="color:#94a3b8; font-size:40px;">🏦</span>');
                    $(this).hide();
                });

                // Feature Image Upload logic
                $('#upload_insurer_feature_img').on('click', function(e){
                    e.preventDefault();
                    var frame = wp.media({
                        title: 'Select Insurer Feature Image',
                        button: { text: 'Use this image' },
                        library: { type: 'image' },
                        multiple: false
                    });
                    frame.on('select', function(){
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#insurer_feature_img').val(attachment.id);
                        if ($('#insurer_feature_img_preview').length) {
                            $('#insurer_feature_img_preview').attr('src', attachment.url);
                        } else {
                            $('#insurer_feature_img_preview_container').html('<img src="' + attachment.url + '" id="insurer_feature_img_preview">');
                        }
                        $('#remove_insurer_feature_img').show();
                    });
                    frame.open();
                });

                $('#remove_insurer_feature_img').on('click', function(e){
                    e.preventDefault();
                    $('#insurer_feature_img').val('');
                    $('#insurer_feature_img_preview_container').html('<span style="color:#94a3b8; font-size:40px;">🖼️</span>');
                    $(this).hide();
                });
            });

        })( jQuery );
        </script>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['insurer_profile_meta_box_nonce']) || !wp_verify_nonce($_POST['insurer_profile_meta_box_nonce'], 'insurer_profile_meta_box')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        // Save logo ID (attachment)
        if (isset($_POST['insurer_logo_id'])) {
            update_post_meta($post_id, '_insurer_logo_id', intval($_POST['insurer_logo_id']));
        }
        
        // Save logo URL (alternative)
        if (isset($_POST['insurer_logo'])) {
            update_post_meta($post_id, '_insurer_logo', sanitize_text_field($_POST['insurer_logo']));
        }
        
        if (isset($_POST['insurer_name'])) {
            update_post_meta($post_id, '_insurer_name', sanitize_text_field($_POST['insurer_name']));
        }
        if (isset($_POST['insurer_profile'])) {
            // Limiter à 150 mots
            $profile = wp_strip_all_tags($_POST['insurer_profile']);
            $words = explode(' ', $profile);
            if (count($words) > 150) {
                $profile = implode(' ', array_slice($words, 0, 150));
            }
            update_post_meta($post_id, '_insurer_profile', $profile);
        }
        if (isset($_POST['insurer_feature_img'])) {
            update_post_meta($post_id, '_insurer_feature_img', intval($_POST['insurer_feature_img']));
        }
        if (isset($_POST['insurer_website'])) {
            update_post_meta($post_id, '_insurer_website', esc_url_raw($_POST['insurer_website']));
        }
        if (isset($_POST['insurer_linkedin'])) {
            update_post_meta($post_id, '_insurer_linkedin', esc_url_raw($_POST['insurer_linkedin']));
        }
        if (isset($_POST['insurer_pesapal_id'])) {
            update_post_meta($post_id, '_insurer_pesapal_merchant_id', sanitize_text_field($_POST['insurer_pesapal_id']));
        }
    }
}