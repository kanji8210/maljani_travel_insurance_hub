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
        // Récupérer les valeurs existantes
        $logo = get_post_meta($post->ID, '_insurer_logo', true);
        $name = get_post_meta($post->ID, '_insurer_name', true);
        $profile = get_post_meta($post->ID, '_insurer_profile', true);
        $feature_img_id = get_post_meta($post->ID, '_insurer_feature_img', true);
        $feature_img_url = $feature_img_id ? wp_get_attachment_url($feature_img_id) : '';

        // Champ Logo (URL)
        echo '<label for="insurer_logo">Logo URL :</label><br>';
        echo '<input type="text" id="insurer_logo" name="insurer_logo" value="' . esc_attr($logo) . '" style="width:100%;" /><br><br>';

        // Champ Nom
        echo '<label for="insurer_name">Nom de l\'assureur :</label><br>';
        echo '<input type="text" id="insurer_name" name="insurer_name" value="' . esc_attr($name) . '" style="width:100%;" /><br><br>';

        // Champ Profil
        echo '<label for="insurer_profile">Profil :</label><br>';
        $profile = get_post_meta($post->ID, '_insurer_profile', true);
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

        // Champ Image à la une (portrait 6:4)
        echo '<label for="insurer_feature_img">Image à la une (portrait 6:4) :</label><br>';
        echo '<input type="hidden" id="insurer_feature_img" name="insurer_feature_img" value="' . esc_attr($feature_img_id) . '" />';
        echo '<img id="insurer_feature_img_preview" src="' . esc_url($feature_img_url) . '" style="max-width:180px;max-height:120px;display:block;margin-bottom:5px;" />';
        echo '<button type="button" class="button" id="upload_insurer_feature_img">Téléverser une image</button><br><br>';

        // Champ Website
        $website = get_post_meta($post->ID, '_insurer_website', true);
        echo '<label for="insurer_website">Site web :</label><br>';
        echo '<input type="url" id="insurer_website" name="insurer_website" value="' . esc_attr($website) . '" style="width:100%;" placeholder="https://..." /><br><br>';

        // Champ LinkedIn
        $linkedin = get_post_meta($post->ID, '_insurer_linkedin', true);
        echo '<label for="insurer_linkedin">LinkedIn :</label><br>';
        echo '<input type="url" id="insurer_linkedin" name="insurer_linkedin" value="' . esc_attr($linkedin) . '" style="width:100%;" placeholder="https://linkedin.com/company/..." /><br><br>';
        ?>
        <script>
        (function( $ ) {
            'use strict';

            $(document).ready(function() {
                // Pour le bouton d'upload de l'image à la une de l'assureur
                $('#upload_insurer_feature_img').on('click', function(e){
                    e.preventDefault();
                    var frame = wp.media({
                        title: 'Sélectionner ou téléverser une image à la une',
                        button: { text: 'Utiliser cette image' },
                        library: { type: 'image' },
                        multiple: false
                    });
                    frame.on('select', function(){
                        var attachment = frame.state().get('selection').first().toJSON();
                        // Plus aucune vérification de ratio
                        $('#insurer_feature_img').val(attachment.id);
                        $('#insurer_feature_img_preview').attr('src', attachment.url).show();
                    });
                    frame.open();
                });
            });

        })( jQuery );
        </script>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
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
    }
}