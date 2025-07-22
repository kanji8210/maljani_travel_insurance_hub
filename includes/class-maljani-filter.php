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
        try {
            wp_enqueue_style(
                'maljani-filter-style',
                plugin_dir_url(__FILE__) . 'css/maljani-filter.css',
                array(),
                null
            );
        } catch (Exception $e) {
            error_log('Erreur lors de l\'enregistrement du style : ' . $e->getMessage());
        }
    }

    public function render_filter_form() {
        ob_start();
        try {
        ?>
        <form id="maljani-policy-filter-form" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <label style="margin:0;">
                Destination region:
                <select name="region" style="margin-left:4px;">
                    <option value="">All regions</option>
                    <?php
                    $regions = get_terms(array('taxonomy' => 'policy_region', 'hide_empty' => false));
                    if (is_wp_error($regions)) {
                        echo '<option disabled>Error loading regions</option>';
                    } else {
                        foreach ($regions as $region) {
                            echo '<option value="' . esc_attr($region->term_id) . '">' . esc_html($region->name) . '</option>';
                        }
                    }
                    ?>
                </select>
            </label>
            <label style="margin:0;">
                Insurer:
                <select name="insurer" style="margin-left:4px;">
                    <option value="">All insurers</option>
                    <?php
                    $insurers = get_posts(array('post_type' => 'insurer_profile', 'numberposts' => -1));
                    if (empty($insurers)) {
                        echo '<option disabled>Error loading insurers</option>';
                    } else {
                        foreach ($insurers as $insurer) {
                            echo '<option value="' . esc_attr($insurer->ID) . '">' . esc_html($insurer->post_title) . '</option>';
                        }
                    }
                    ?>
                </select>
            </label>
            <button type="submit" style="margin-left:8px;">Filter</button>
        </form>
        <div id="maljani-policy-results">
            <?php $this->render_policy_list(); ?>
        </div>
        <?php
        } catch (Exception $e) {
            echo '<div class="error">Error displaying filter form: ' . esc_html($e->getMessage()) . '</div>';
        }
        return ob_get_clean();
    }

    public function ajax_filter() {
        try {
            $region = intval($_POST['region']);
            $insurer = intval($_POST['insurer']);

            $meta_query = array();

            if ($insurer) {
                if (!get_post($insurer) || get_post_type($insurer) !== 'insurer_profile') {
                    wp_send_json_error('Invalid insurer ID');
                    wp_die();
                }
                $meta_query[] = array(
                    'key' => '_policy_insurer',
                    'value' => $insurer,
                    'compare' => '='
                );
            }
            if ($region) {
                $meta_query[] = array(
                    'key' => '_policy_region',
                    'value' => $region,
                    'compare' => '='
                );
            }

            $args = array(
                'post_type' => 'policy',
                'posts_per_page' => 12,
            );
            if (!empty($meta_query)) {
                $args['meta_query'] = $meta_query;
            }

            ob_start();
            $this->render_policy_list($args);
            $html = ob_get_clean();

            wp_send_json_success(array('html' => $html));
        } catch (Exception $e) {
            error_log('Erreur AJAX filter : ' . $e->getMessage());
            wp_send_json_error('Erreur lors du filtrage : ' . $e->getMessage());
        }
        wp_die();
    }

    public function enqueue_scripts() {
        try {
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

            wp_enqueue_style(
                'maljani-filter-style',
                plugin_dir_url(__FILE__) . 'css/maljani-filter.css',
                array(),
                null
            );
        } catch (Exception $e) {
            error_log('Erreur lors de l\'enregistrement des scripts : ' . $e->getMessage());
        }
    }

    public function render_policy_list($args = array()) {
        try {
            $defaults = array(
                'post_type' => 'policy',
                'posts_per_page' => 12,
            );
            $args = wp_parse_args($args, $defaults);
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                echo '<h2>Policies found</h2>';
                echo '<div class="thumbnail-grid">';
                while ($query->have_posts()) {
                    $query->the_post();
                    error_log('Policy ID: ' . get_the_ID() . ' | _policy_region: ' . get_post_meta(get_the_ID(), '_policy_region', true) . ' | _policy_insurer: ' . get_post_meta(get_the_ID(), '_policy_insurer', true));
                    $this->render_policy_item(get_the_ID());
                }
                echo '</div>';
            } else {
                echo '<p> Please widen you search no policy was found to match your creteria</p>';
            }
            wp_reset_postdata();
        } catch (Exception $e) {
            echo '<div class="error">Erreur lors de l\'affichage des policies : ' . esc_html($e->getMessage()) . '</div>';
            error_log('Erreur render_policy_list : ' . $e->getMessage());
        }
    }

    public function render_policy_item($policy_id) {
        try {
            $feature_img_id = get_post_meta($policy_id, '_policy_feature_img', true);
            $img_url = $feature_img_id ? wp_get_attachment_url($feature_img_id) : plugins_url('images/default-policy-image.jpg', __FILE__);

            $insurer_id = get_post_meta($policy_id, '_policy_insurer', true);
            $insurer_name = $insurer_logo = '';
            if ($insurer_id) {
                $insurer_name = get_the_title($insurer_id);
                $insurer_logo = get_post_meta($insurer_id, '_insurer_logo', true);
                if (!$insurer_logo && has_post_thumbnail($insurer_id)) {
                    $insurer_logo = get_the_post_thumbnail_url($insurer_id, 'thumbnail');
                }
            }

            $region_id = get_post_meta($policy_id, '_policy_region', true);
            $region_name = '';
            if ($region_id) {
                $region = get_term($region_id, 'policy_region');
                if ($region && !is_wp_error($region)) {
                    $region_name = $region->name;
                }
            }

            $description = get_post_meta($policy_id, '_policy_description', true);
            $excerpt = wp_trim_words($description, 20, '...');

            echo '<li class="maljani-policy-item" style="display:flex;align-items:flex-start;margin-bottom:24px;">';
            echo '<div class="maljani-policy-thumb" style="flex:0 0 120px;margin-right:20px;">';
            echo '<a href="' . esc_url(get_permalink($policy_id)) . '">';
            echo '<img src="' . esc_url($img_url) . '" alt="" style="width:120px;height:180px;object-fit:cover;border-radius:8px;">';
            echo '</a>';
            echo '</div>';
            echo '<div class="maljani-policy-infos">';
            if ($insurer_id && $insurer_name) {
                echo '<div style="display:flex;align-items:center;margin-bottom:8px;">';
                if ($insurer_logo) {
                    echo '<img src="' . esc_url($insurer_logo) . '" alt="Logo" style="width:32px;height:32px;object-fit:cover;border-radius:50%;margin-right:8px;">';
                }
                echo '<span style="font-weight:bold;">' . esc_html($insurer_name) . '</span>';
                echo '</div>';
            }
            if ($region_name) {
                echo '<div style="margin-bottom:8px;"><strong>Région:</strong> ' . esc_html($region_name) . '</div>';
            }
            echo '<div>' . esc_html($excerpt) . '</div>';
            echo '</div>';
            echo '</li>';
        } catch (Exception $e) {
            echo '<li class="maljani-policy-item error">Erreur lors de l\'affichage de la policy : ' . esc_html($e->getMessage()) . '</li>';
            error_log('Erreur render_policy_item : ' . $e->getMessage());
        }
    }
}
new Maljani_Filter();