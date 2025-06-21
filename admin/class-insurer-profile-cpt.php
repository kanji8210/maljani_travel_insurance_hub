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

        // Champ Logo (URL)
        echo '<label for="insurer_logo">Logo URL :</label><br>';
        echo '<input type="text" id="insurer_logo" name="insurer_logo" value="' . esc_attr($logo) . '" style="width:100%;" /><br><br>';

        // Champ Nom
        echo '<label for="insurer_name">Nom de l\'assureur :</label><br>';
        echo '<input type="text" id="insurer_name" name="insurer_name" value="' . esc_attr($name) . '" style="width:100%;" /><br><br>';

        // Champ Profil
        echo '<label for="insurer_profile">Profil (150 mots max) :</label><br>';
        echo '<textarea id="insurer_profile" name="insurer_profile" rows="5" style="width:100%;">' . esc_textarea($profile) . '</textarea>';
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
    }
}