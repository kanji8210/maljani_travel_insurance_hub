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
        // Récupération des valeurs existantes
        $insurer_id = get_post_meta($post->ID, '_policy_insurer', true);
        $description = get_post_meta($post->ID, '_policy_description', true);
        $cover_details = get_post_meta($post->ID, '_policy_cover_details', true);
        $benefits = get_post_meta($post->ID, '_policy_benefits', true);
        $not_covered = get_post_meta($post->ID, '_policy_not_covered', true);
        $day_premiums = get_post_meta($post->ID, '_policy_day_premiums', true);
        $feature_img_id = get_post_meta($post->ID, '_policy_feature_img', true);
        $feature_img_url = $feature_img_id ? wp_get_attachment_url($feature_img_id) : '';

        // Sélecteur d'assureur
        $insurers = get_posts(array(
            'post_type' => 'insurer_profile',
            'numberposts' => -1,
            'post_status' => 'publish'
        ));
        echo '<label for="policy_insurer">Insurer :</label><br>';
        echo '<select id="policy_insurer" name="policy_insurer" style="width:100%;">';
        echo '<option value="">-- Select --</option>';
        foreach ($insurers as $insurer) {
            $selected = ($insurer_id == $insurer->ID) ? 'selected' : '';
            echo '<option value="' . esc_attr($insurer->ID) . '" ' . $selected . '>' . esc_html($insurer->post_title) . '</option>';
        }
        echo '</select><br><br>';

        // Description
        echo '<label for="policy_description">Description :</label><br>';
        echo '<input type="text" id="policy_description" name="policy_description" value="' . esc_attr($description) . '" style="width:100%;" /><br><br>';

        // Cover details (WYSIWYG)
        echo '<label for="policy_cover_details">Cover Details :</label><br>';
        wp_editor($cover_details, 'policy_cover_details', array('textarea_name' => 'policy_cover_details', 'textarea_rows' => 5));
        echo '<br>';

        // Benefits (WYSIWYG)
        echo '<label for="policy_benefits">Benefits :</label><br>';
        wp_editor($benefits, 'policy_benefits', array('textarea_name' => 'policy_benefits', 'textarea_rows' => 5));
        echo '<br>';

        // What is not covered
        echo '<label for="policy_not_covered">What is not covered :</label><br>';
        wp_editor($not_covered, 'policy_not_covered', array('textarea_name' => 'policy_not_covered', 'textarea_rows' => 5));
        echo '<br>';

        // Jour & prime (tableau dynamique)
        echo '<label>Day Range & Premiums :</label>';
        echo '<table id="day-premium-table" style="width:100%;margin-bottom:10px;">';
        echo '<thead><tr><th>From (days)</th><th>To (days)</th><th>Premium</th><th></th></tr></thead><tbody>';
        if (!empty($day_premiums) && is_array($day_premiums)) {
            foreach ($day_premiums as $row) {
                echo '<tr>
                    <td><input type="number" name="day_premium_from[]" value="' . esc_attr($row['from']) . '" min="1" style="width:90px;" /></td>
                    <td><input type="number" name="day_premium_to[]" value="' . esc_attr($row['to']) . '" min="1" style="width:90px;" /></td>
                    <td><input type="number" name="day_premium_amount[]" value="' . esc_attr($row['premium']) . '" min="0" step="0.01" style="width:120px;" /></td>
                    <td><button type="button" class="remove-row button">-</button></td>
                </tr>';
            }
        } else {
            // Ligne vide par défaut
            echo '<tr>
                <td><input type="number" name="day_premium_from[]" value="" min="1" style="width:90px;" /></td>
                <td><input type="number" name="day_premium_to[]" value="" min="1" style="width:90px;" /></td>
                <td><input type="number" name="day_premium_amount[]" value="" min="0" step="0.01" style="width:120px;" /></td>
                <td><button type="button" class="remove-row button">-</button></td>
            </tr>';
        }
        echo '</tbody></table>';
        echo '<button type="button" id="add-day-premium-row" class="button">Add Row</button>';

        // Feature Image (portrait)
        echo '<label for="policy_feature_img">Feature Image (portrait 400x600px) :</label><br>';
        echo '<input type="hidden" id="policy_feature_img" name="policy_feature_img" value="' . esc_attr($feature_img_id) . '" />';
        echo '<img id="policy_feature_img_preview" src="' . esc_url($feature_img_url) . '" style="max-width:120px;max-height:180px;display:block;margin-bottom:5px;" />';
        echo '<button type="button" class="button" id="upload_policy_feature_img">Upload Image</button><br><br>';

        // Région (dropdown + ajout rapide)
        $regions = get_terms(array(
            'taxonomy' => 'policy_region',
            'hide_empty' => false,
        ));
        $current_regions = wp_get_post_terms($post->ID, 'policy_region', array('fields' => 'ids'));
        echo '<label for="policy_region">Region :</label><br>';
        echo '<select id="policy_region" name="policy_region" style="width:100%;">';
        echo '<option value="">-- Select --</option>';
        foreach ($regions as $region) {
            $selected = in_array($region->term_id, $current_regions) ? 'selected' : '';
            echo '<option value="' . esc_attr($region->term_id) . '" ' . $selected . '>' . esc_html($region->name) . '</option>';
        }
        echo '</select> ';
        echo '<input type="text" id="new_policy_region" placeholder="Add new region" style="width:60%;" />';
        echo '<button type="button" id="add_policy_region" class="button">Add</button><br><br>';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('day-premium-table').getElementsByTagName('tbody')[0];
            document.getElementById('add-day-premium-row').onclick = function() {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input type="number" name="day_premium_from[]" min="1" style="width:90px;" /></td>
                    <td><input type="number" name="day_premium_to[]" min="1" style="width:90px;" /></td>
                    <td><input type="number" name="day_premium_amount[]" min="0" step="0.01" style="width:120px;" /></td>
                    <td><button type="button" class="remove-row button">-</button></td>
                `;
                table.appendChild(row);
            };
            table.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-row')) {
                    e.target.closest('tr').remove();
                }
            });
        });
        jQuery(document).ready(function($){
            var frame;
            $('#upload_policy_feature_img').on('click', function(e){
                e.preventDefault();
                if(frame){ frame.open(); return; }
                frame = wp.media({
                    title: 'Select or Upload Feature Image',
                    button: { text: 'Use this image' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    // Vérification portrait
                    if(attachment.width < 400 || attachment.height < 600 || attachment.width/attachment.height > 0.8){
                        alert('Image must be portrait and at least 400x600px');
                        return;
                    }
                    if(attachment.width/attachment.height < 1.49 || attachment.width/attachment.height > 1.51){
                        alert('L\'image doit être au format portrait avec un ratio de 6:4 (largeur:hauteur)');
                        return;
                    }
                    var isPortrait = attachment.height > attachment.width;
                    if(!isPortrait){
                        alert("Astuce : Pour un meilleur rendu, il est recommandé d'utiliser une image portrait (hauteur supérieure à la largeur).");
                    }
                    $('#policy_feature_img').val(attachment.id);
                    $('#policy_feature_img_preview').attr('src', attachment.url).show();
                });
                frame.open();
            });
        });
        document.getElementById('add_policy_region').onclick = function() {
            var newRegion = document.getElementById('new_policy_region').value;
            if (!newRegion) return;
            var data = new FormData();
            data.append('action', 'add_policy_region');
            data.append('region', newRegion);
            fetch(ajaxurl, {method:'POST', body:data})
                .then(r=>r.json())
                .then(function(resp){
                    if(resp.success && resp.term_id){
                        var sel = document.getElementById('policy_region');
                        var opt = document.createElement('option');
                        opt.value = resp.term_id;
                        opt.text = newRegion;
                        opt.selected = true;
                        sel.appendChild(opt);
                        sel.value = resp.term_id;
                        document.getElementById('new_policy_region').value = '';
                    } else {
                        alert('Could not add region');
                    }
                });
        };
        </script>
        <?php
    }

    // Sauvegarde des champs personnalisés
    public function save_meta_boxes($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
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
            wp_set_post_terms($post_id, array(intval($_POST['policy_region'])), 'policy_region');
        }
    }

    // Charge les scripts nécessaires à l'admin
    public function enqueue_admin_scripts($hook) {
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            wp_enqueue_style('wp-jquery-ui-dialog');
            wp_enqueue_media();
        }
    }

    // Handler AJAX pour ajout rapide de région
    public function ajax_add_policy_region() {
        if (!current_user_can('edit_posts')) wp_send_json_error();
        $region = sanitize_text_field($_POST['region']);
        $term = wp_insert_term($region, 'policy_region');
        if (!is_wp_error($term)) {
            wp_send_json_success(['term_id' => $term['term_id']]);
        }
        wp_send_json_error();
    }
}