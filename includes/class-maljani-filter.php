<?php
class Maljani_Filter {
    public function __construct() {
        add_shortcode('maljani_policy_ajax_filter', array($this, 'render_filter_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_maljani_filter_policies', array($this, 'ajax_filter'));
        add_action('wp_ajax_nopriv_maljani_filter_policies', array($this, 'ajax_filter'));
    }

    //enqueue style
    public function enqueue_style() {
        wp_enqueue_style(
            'maljani-filter-style',
            plugin_dir_url(__FILE__) . 'css/maljani-filter.css',
            array(),
            null
        );
    }
    public function render_filter_form() {
        ob_start();
        ?>
        <form id="maljani-policy-filter-form">
            <label>Région :
                <select name="region">
                    <option value="">All</option>
                    <?php
                    $regions = get_terms(array('taxonomy' => 'policy_region', 'hide_empty' => false));
                    foreach ($regions as $region) {
                        echo '<option value="' . esc_attr($region->term_id) . '">' . esc_html($region->name) . '</option>';
                    }
                    ?>
                </select>
            </label>
            <label>Assureur :
                <select name="insurer">
                    <option value="">all</option>
                    <?php
                    $insurers = get_posts(array('post_type' => 'insurer_profile', 'numberposts' => -1));
                    foreach ($insurers as $insurer) {
                        echo '<option value="' . esc_attr($insurer->ID) . '">' . esc_html($insurer->post_title) . '</option>';
                    }
                    ?>
                </select>
            </label>
            <label> Sort by premium:
                <select name="sort_premium">
                    <option value="asc">Ascending</option>
                    <option value="desc">Discending</option>
                </select>
            </label>
            <button type="submit">Filter</button>
        </form>
        <div id="maljani-policy-results">
            <?php $this->render_policy_list(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_filter() {
        $region = sanitize_text_field($_POST['region']);
        $insurer = intval($_POST['insurer']);
        $sort = ($_POST['sort_premium'] === 'desc') ? 'DESC' : 'ASC';

        $args = array(
            'post_type' => 'policy',
            'posts_per_page' => 12,
        );

        $meta_query = array();

        if (!empty($insurer)) {
            $meta_query[] = array(
                'key' => '_policy_insurer',
                'value' => $insurer,
                'compare' => '='
            );
        }
        if (!empty($region)) {
            $meta_query[] = array(
                'key' => '_policy_region',
                'value' => $region,
                'compare' => '='
            );
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $tax_query = array();
        if (!empty($_POST['region'])) {
            $tax_query[] = array(
                'taxonomy' => 'policy_region',
                'field' => 'term_id',
                'terms' => intval($_POST['region'])
            );
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $args['orderby'] = 'meta_value_num';
        $args['meta_key'] = '_policy_min_premium';
        $args['order'] = $sort;

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            echo '<ul class="maljani-policy-list">';
            while ($query->have_posts()) {
                $query->the_post();
                $premium = get_post_meta(get_the_ID(), '_policy_min_premium', true);
                echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a> - Prime : ' . esc_html($premium) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>Please widen you criteria, No policy was fund.</p>';
        }
        wp_die();
    }
    public function enqueue_scripts() {
        wp_enqueue_script(
            'maljani-filter',
            plugin_dir_url(__FILE__) . '/js/maljani-filter.js',
            array('jquery'),
            null,
            true
        );
        wp_localize_script('maljani-filter', 'maljaniFilter', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));

        // Enqueue le style du filtre
        wp_enqueue_style(
            'maljani-filter-style',
            plugin_dir_url(__FILE__) . 'css/maljani-filter.css',
            array(),
            null
        );
    }

    // Nouvelle méthode pour afficher la liste des policies
    public function render_policy_list($args = array()) {
        $defaults = array(
            'post_type' => 'policy',
            'posts_per_page' => 12,
        );
        $args = wp_parse_args($args, $defaults);
        $query = new WP_Query($args);
        //var_dump($query);

        if ($query->have_posts()) {
            echo '<div class="thumbnail-grid">';
            echo '<h2>Policies</h2>';
            while ($query->have_posts()) {
                $query->the_post();
                $this->render_policy_item(get_the_ID());
            }
            echo '</div>';
        } else {
            echo '<p> Please widen you search no policy was found to match your creteria</p>';
        }
        wp_reset_postdata();
    }

    // Affichage d'une policy (thumbnail à gauche, infos à droite)
    public function render_policy_item($policy_id) {
        // Feature image (portrait)
        $feature_img_id = get_post_meta($policy_id, '_policy_feature_img', true);
        $img_url = $feature_img_id ? wp_get_attachment_url($feature_img_id) : plugins_url('images/default-policy-image.jpg', __FILE__);

        // Assureur
        $insurer_id = get_post_meta($policy_id, '_policy_insurer', true);
        $insurer_name = $insurer_logo = '';
        if ($insurer_id) {
            $insurer_name = get_the_title($insurer_id);
            $insurer_logo = get_post_meta($insurer_id, '_insurer_logo', true);
            if (!$insurer_logo && has_post_thumbnail($insurer_id)) {
                $insurer_logo = get_the_post_thumbnail_url($insurer_id, 'thumbnail');
            }
        }

        // Régions
        $regions = get_the_terms($policy_id, 'policy_region');
        $region_names = $regions && !is_wp_error($regions) ? wp_list_pluck($regions, 'name') : array();

        // Excerpt
        $description = get_post_meta($policy_id, '_policy_description', true);
        $excerpt = wp_trim_words($description, 20, '...');

        echo '<li class="maljani-policy-item" style="display:flex;align-items:flex-start;margin-bottom:24px;">';
        // Colonne gauche : image
        echo '<div class="maljani-policy-thumb" style="flex:0 0 120px;margin-right:20px;">';
        echo '<a href="' . esc_url(get_permalink($policy_id)) . '">';
        echo '<img src="' . esc_url($img_url) . '" alt="" style="width:120px;height:180px;object-fit:cover;border-radius:8px;">';
        echo '</a>';
        echo '</div>';
        // Colonne droite : infos
        echo '<div class="maljani-policy-infos">';
        if ($insurer_id && $insurer_name) {
            echo '<div style="display:flex;align-items:center;margin-bottom:8px;">';
            if ($insurer_logo) {
                echo '<img src="' . esc_url($insurer_logo) . '" alt="Logo" style="width:32px;height:32px;object-fit:cover;border-radius:50%;margin-right:8px;">';
            }
            echo '<span style="font-weight:bold;">' . esc_html($insurer_name) . '</span>';
            echo '</div>';
        }
        if (!empty($region_names)) {
            echo '<div style="margin-bottom:8px;"><strong>Région(s):</strong> ' . esc_html(implode(', ', $region_names)) . '</div>';
        }
        // Supprime le titre, affiche la description coupée à 20 mots
        echo '<div>' . esc_html($excerpt) . '</div>';
        echo '</div>';
        echo '</li>';
    }
}
new Maljani_Filter();