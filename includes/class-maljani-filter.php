<?php
class Maljani_Filter {
    public function __construct() {
        add_shortcode('maljani_policy_ajax_filter', array($this, 'render_filter_form'));
        add_shortcode('maljani_filter_form', array($this, 'render_filter_form_only'));
        add_shortcode('maljani_policy_grid', array($this, 'render_policy_grid'));
    // Load filter assets late to overrule theme styles
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 999);
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

    public function render_filter_form($atts = array()) {
        $atts = shortcode_atts(array(
            'columns' => '4',
        ), $atts);
        
        $columns = intval($atts['columns']);
        $columns = max(1, min(4, $columns));
        
        ob_start();
        try {
    ?>
    <style>
        .maljani-policy-grid-ajax {
            display: grid;
            grid-template-columns: repeat(<?php echo esc_attr($columns); ?>, 1fr);
            gap: 24px;
            list-style: none;
            padding: 0;
            margin: 32px 0 0 0;
        }
        @media (max-width: 1024px) {
            .maljani-policy-grid-ajax {
                grid-template-columns: repeat(<?php echo min(2, $columns); ?>, 1fr);
            }
        }
        @media (max-width: 700px) {
            .maljani-policy-grid-ajax {
                grid-template-columns: 1fr;
            }
        }
        .maljani-policy-card {
            border: 1px solid #ddd;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .maljani-policy-card h3 {
            margin: 0;
            font-size: 1.2em;
            color: #222;
        }
        .maljani-policy-card h3 a {
            color: #1e5c3a;
            text-decoration: none;
        }
        .maljani-policy-card h3 a:hover {
            text-decoration: underline;
        }
        .policy-buy-btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--wp--preset--color--primary, #1e5c3a);
            color: #ffffff !important;
            text-decoration: none;
            border: none;
            cursor: pointer;
            text-align: center;
            font-weight: 500;
        }
        .policy-buy-btn:hover {
            opacity: 0.9;
            color: #ffffff !important;
        }
        .region-filter-btn {
            padding: 8px 16px;
            border: 1px solid var(--wp--preset--color--primary, #1e5c3a);
            background: white;
            color: var(--wp--preset--color--primary, #1e5c3a);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .region-filter-btn.active,
        .region-filter-btn:hover {
            background: var(--wp--preset--color--primary, #1e5c3a);
            color: white;
        }
    </style>
    <div class="maljani-filter-wrapper" data-columns="<?php echo esc_attr($columns); ?>">
    <form id="maljani-policy-filter-form" style="display:flex;flex-direction:column;gap:20px;margin-bottom:30px;">
            <!-- Date inputs -->
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <label style="margin:0;color:#222;">
                    Departure Date:
                    <input type="date" name="departure" style="margin-left:8px;padding:8px;border:1px solid #ddd;color:#222;" required>
                </label>
                <label style="margin:0;color:#222;">
                    Return Date:
                    <input type="date" name="return" style="margin-left:8px;padding:8px;border:1px solid #ddd;color:#222;" required>
                </label>
            </div>
            
            <!-- Region filter buttons -->
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-weight:bold;color:#222;">Filter by type/region:</span>
                <button type="button" class="region-filter-btn active" data-region="">All Regions</button>
                <?php
                $regions = get_terms(array('taxonomy' => 'policy_region', 'hide_empty' => false));
                if (!is_wp_error($regions)) {
                    foreach ($regions as $region) {
                        echo '<button type="button" class="region-filter-btn" data-region="' . esc_attr($region->term_id) . '">' . esc_html($region->name) . '</button>';
                    }
                }
                ?>
            </div>
        </form>
        <div id="maljani-policy-results">
            <?php $this->render_policy_list(array(), 0, $columns); ?>
        </div>
        </div>
        <?php
        } catch (Exception $e) {
            echo '<div class="error">Error displaying filter form: ' . esc_html($e->getMessage()) . '</div>';
        }
        return ob_get_clean();
    }

    /**
     * Render filter form only (without policy grid)
     * Shortcode: [maljani_filter_form]
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_filter_form_only($atts = array()) {
        $atts = shortcode_atts(array(
            'redirect' => '', // URL to redirect to with filter parameters
        ), $atts);
        
        ob_start();
        try {
    ?>
    <div class="maljani-filter-form-only">
        <form id="maljani-filter-form-standalone" method="get" action="<?php echo esc_url($atts['redirect'] ? $atts['redirect'] : ''); ?>" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <label style="margin:0;color:#222;">
                Departure Date:
                <input type="date" name="departure" style="margin-left:8px;padding:8px;border:1px solid #ddd;color:#222;" required>
            </label>
            <label style="margin:0;color:#222;">
                Return Date:
                <input type="date" name="return" style="margin-left:8px;padding:8px;border:1px solid #ddd;color:#222;" required>
            </label>
            <label style="margin:0;color:#222;">
                Filter by type/region:
                <select name="region_id" style="margin-left:8px;padding:8px;border:1px solid #ddd;color:#222;">
                    <option value="">All Regions</option>
                    <?php
                    $regions = get_terms(array('taxonomy' => 'policy_region', 'hide_empty' => false));
                    if (!is_wp_error($regions)) {
                        foreach ($regions as $region) {
                            echo '<option value="' . esc_attr($region->term_id) . '">' . esc_html($region->name) . '</option>';
                        }
                    }
                    ?>
                </select>
            </label>
            <button type="submit" style="padding:10px 24px;border:1px solid #222;color:#222;cursor:pointer;">Search Policies</button>
        </form>
    </div>
    <?php
        } catch (Exception $e) {
            echo '<div class="error">Error displaying filter form: ' . esc_html($e->getMessage()) . '</div>';
        }
        return ob_get_clean();
    }

    /**
     * Render policy grid with customizable columns and posts per page
     * Shortcode: [maljani_policy_grid columns="3" posts_per_page="12"]
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_policy_grid($atts = array()) {
        $atts = shortcode_atts(array(
            'columns' => '3',
            'posts_per_page' => '12',
            'region' => '',
        ), $atts);
        
        $columns = intval($atts['columns']);
        $columns = max(1, min(4, $columns)); // Limit between 1-4 columns
        
        $posts_per_page = intval($atts['posts_per_page']);
        $posts_per_page = max(1, min(50, $posts_per_page)); // Limit between 1-50 posts
        
        // Get filter parameters from URL
        $region_id = isset($_GET['region_id']) ? intval($_GET['region_id']) : ($atts['region'] ? intval($atts['region']) : 0);
        $departure = isset($_GET['departure']) ? sanitize_text_field($_GET['departure']) : '';
        $return = isset($_GET['return']) ? sanitize_text_field($_GET['return']) : '';
        
        // Calculate days
        $days = 0;
        if ($departure && $return) {
            $d1 = new DateTime($departure);
            $d2 = new DateTime($return);
            $days = $d1 < $d2 ? $d1->diff($d2)->days : 0;
        }
        
        $args = array(
            'post_type' => 'policy',
            'posts_per_page' => $posts_per_page,
        );
        
        if ($region_id) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'policy_region',
                    'field' => 'term_id',
                    'terms' => $region_id,
                )
            );
        }
        
        // Calculate column width based on columns parameter
        $column_width = 100 / $columns;
        
        ob_start();
        ?>
        <style>
            .maljani-policy-grid-<?php echo esc_attr($columns); ?> {
                display: grid;
                grid-template-columns: repeat(<?php echo esc_attr($columns); ?>, 1fr);
                gap: 24px;
                list-style: none;
                padding: 0;
                margin: 32px 0 0 0;
            }
            @media (max-width: 1024px) {
                .maljani-policy-grid-<?php echo esc_attr($columns); ?> {
                    grid-template-columns: repeat(<?php echo min(2, $columns); ?>, 1fr);
                }
            }
            @media (max-width: 700px) {
                .maljani-policy-grid-<?php echo esc_attr($columns); ?> {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <div class="maljani-policy-grid-wrapper">
            <?php
            $query = new WP_Query($args);
            
            if ($query->have_posts()) {
                if ($days > 0) {
                    echo '<h2 style="color:#222;">Policies found for ' . esc_html($days) . ' days of travel</h2>';
                }
                echo '<ul class="maljani-policy-grid-' . esc_attr($columns) . '">';
                while ($query->have_posts()) {
                    $query->the_post();
                    $this->render_policy_item(get_the_ID(), $days);
                }
                echo '</ul>';
            } else {
                echo '<p style="color:#222;">Please widen your search - no policy was found to match your criteria</p>';
            }
            wp_reset_postdata();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_filter() {
        try {
            $region = intval($_POST['region']);
            $departure = sanitize_text_field($_POST['departure']);
            $return = sanitize_text_field($_POST['return']);
            $columns = isset($_POST['columns']) ? intval($_POST['columns']) : 4;
            
            // Calculate days
            $days = 0;
            if ($departure && $return) {
                $d1 = new DateTime($departure);
                $d2 = new DateTime($return);
                $days = $d1 < $d2 ? $d1->diff($d2)->days : 0;
            }

            $meta_query = array();
            $tax_query = array();

            if ($region) {
                $tax_query[] = array(
                    'taxonomy' => 'policy_region',
                    'field'    => 'term_id',
                    'terms'    => $region,
                );
            }

            $args = array(
                'post_type' => 'policy',
                'posts_per_page' => 12,
            );
            
            if (!empty($meta_query)) {
                $args['meta_query'] = $meta_query;
            }
            
            if (!empty($tax_query)) {
                $args['tax_query'] = $tax_query;
            }

            ob_start();
            $this->render_policy_list($args, $days, $columns);
            $html = ob_get_clean();

            wp_send_json_success(array('html' => $html, 'days' => $days));
        } catch (Exception $e) {
            error_log('Erreur AJAX filter : ' . $e->getMessage());
            wp_send_json_error('Erreur lors du filtrage : ' . $e->getMessage());
        }
        wp_die();
    }

    public function enqueue_scripts() {
        try {
            if (is_page('votre-page-filtre')) { // Remplacez par le slug de la page filtre
                wp_enqueue_script('maljani-filter-js', plugin_dir_url(__FILE__).'js/maljani-filter.js', array('jquery'), null, true);
                wp_localize_script('maljani-filter-js', 'maljaniFilterAjax', array(
                    'ajaxurl' => admin_url('admin-ajax.php')
                ));
            }

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

    public function render_policy_list($args = array(), $days = 0, $columns = 4) {
        try {
            $defaults = array(
                'post_type' => 'policy',
                'posts_per_page' => 12,
            );
            $args = wp_parse_args($args, $defaults);
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                if ($days > 0) {
                    echo '<h2 style="color:#222;">Policies found for ' . esc_html($days) . ' days of travel</h2>';
                }
                echo '<ul class="maljani-policy-grid-ajax">';
                while ($query->have_posts()) {
                    $query->the_post();
                    $this->render_policy_item(get_the_ID(), $days);
                }
                echo '</ul>';
            } else {
                echo '<p style="color:#222;">Please widen your search - no policy was found to match your criteria</p>';
            }
            wp_reset_postdata();
        } catch (Exception $e) {
            echo '<div class="error">Erreur lors de l\'affichage des policies : ' . esc_html($e->getMessage()) . '</div>';
            error_log('Erreur render_policy_list : ' . $e->getMessage());
        }
    }

    public function render_policy_item($policy_id, $days = 0) {
        try {
            $insurer_id = get_post_meta($policy_id, '_policy_insurer', true);
            $insurer_name = $insurer_logo = '';
            if ($insurer_id) {
                $insurer_name = get_the_title($insurer_id);
                $insurer_logo = get_post_meta($insurer_id, '_insurer_logo', true);
                if (!$insurer_logo && has_post_thumbnail($insurer_id)) {
                    $insurer_logo = get_the_post_thumbnail_url($insurer_id, 'thumbnail');
                }
            }

            // Get region from taxonomy instead of meta
            $regions = get_the_terms($policy_id, 'policy_region');
            $region_name = '';
            if ($regions && !is_wp_error($regions)) {
                $region_name = $regions[0]->name;
            }

            $description = get_post_meta($policy_id, '_policy_description', true);
            $excerpt = wp_trim_words($description, 20, '...');

            // Calculate premium if days are provided
            $premium = '';
            if ($days > 0) {
                $premiums = get_post_meta($policy_id, '_policy_day_premiums', true);
                if (is_array($premiums)) {
                    foreach ($premiums as $row) {
                        if ($days >= intval($row['from']) && $days <= intval($row['to'])) {
                            $premium = $row['premium'];
                            break;
                        }
                    }
                }
            }

            echo '<li class="maljani-policy-card">';
            echo '<h3><a href="' . esc_url(get_permalink($policy_id)) . '">' . esc_html(get_the_title($policy_id)) . '</a></h3>';
            
            if ($insurer_id && $insurer_name) {
                echo '<div style="display:flex;align-items:center;gap:8px;">';
                if ($insurer_logo) {
                    echo '<img src="' . esc_url($insurer_logo) . '" alt="Logo" style="width:32px;height:32px;object-fit:cover;">';
                }
                echo '<span class="insurer-name" data-insurer-id="' . esc_attr($insurer_id) . '" style="font-weight:bold;color:#222;">Insurer: ' . esc_html($insurer_name) . '</span>';
                echo '</div>';
            }
            
            if ($region_name) {
                echo '<div style="color:#222;"><strong>Region:</strong> ' . esc_html($region_name) . '</div>';
            }
            
            // Affichage du premium si calculé
            if ($premium && $days > 0) {
                echo '<div style="padding:12px;background:#f5f5f5;border:1px solid #ddd;">';
                echo '<strong style="color:#222;">Premium for ' . esc_html($days) . ' days: ' . esc_html($premium) . '</strong>';
                echo '</div>';
            }
            // Lien et bloc pour les bénéfices (toujours affiché)
            echo '<div class="policy-benefits-link">';
            echo '<a href="#" class="see-benefits" data-policy-id="' . esc_attr($policy_id) . '" style="color:#1e5c3a;font-weight:500;text-decoration:underline;cursor:pointer;">See benefits</a>';
            echo '</div>';
            $benefits = get_post_meta($policy_id, '_policy_benefits', true);
            echo '<div class="policy-benefits-popup" id="policy-benefits-' . esc_attr($policy_id) . '" style="display:none;">';
            if ($benefits) {
                echo '<div class="popup-benefits-content" style="min-width:220px;max-width:340px;">';
                echo '<h4 style="margin-bottom:10px;">Policy Benefits</h4>';
                echo '<div style="font-size:1em;color:#222;">' . wp_kses_post($benefits) . '</div>';
                echo '</div>';
            } else {
                echo '<div class="popup-benefits-content">No benefits listed for this policy.</div>';
            }
            echo '</div>';
            
            echo '<div style="color:#222;flex:1;">' . esc_html($excerpt) . '</div>';
            
            // Add purchase link if premium is available
            if ($premium && $days > 0) {
                $sale_page_id = get_option('maljani_policy_sale_page');
                if ($sale_page_id) {
                    $sale_url = add_query_arg([
                        'policy_id' => $policy_id,
                        'departure' => isset($_POST['departure']) ? $_POST['departure'] : '',
                        'return' => isset($_POST['return']) ? $_POST['return'] : ''
                    ], get_permalink($sale_page_id));
                    echo '<a href="' . esc_url($sale_url) . '" class="policy-buy-btn">Buy Now - ' . esc_html($premium) . '</a>';
                }
            }
            
            echo '</li>';
            // Affichage du profil assureur (caché)
            $insurer_profile = get_post_meta($insurer_id, '_insurer_profile', true);
            $insurer_website = get_post_meta($insurer_id, '_insurer_website', true);
            $insurer_linkedin = get_post_meta($insurer_id, '_insurer_linkedin', true);
            echo '<div class="insurer-profile-popup" id="insurer-profile-' . esc_attr($insurer_id) . '" style="display:none;">';
            echo '  <div class="popup-profile-content" style="min-width:260px;max-width:340px;">';
            echo '    <div style="text-align:center;margin-bottom:12px;">';
            if ($insurer_logo) {
                echo '      <img src="' . esc_url($insurer_logo) . '" alt="Logo" style="width:64px;height:64px;object-fit:cover;border-radius:50%;box-shadow:0 2px 8px rgba(24,49,83,0.10);">';
            }
            echo '    </div>';
            echo '    <h3 style="margin-bottom:8px;">' . esc_html($insurer_name) . '</h3>';
            if ($insurer_profile) {
                echo '    <div style="font-size:1em;color:#222;margin-bottom:10px;">' . esc_html($insurer_profile) . '</div>';
            }
            if ($insurer_website) {
                echo '    <div style="margin-bottom:6px;"><a href="' . esc_url($insurer_website) . '" target="_blank" style="color:#0073aa;font-weight:500;">🌐 Site web</a></div>';
            }
            if ($insurer_linkedin) {
                echo '    <div><a href="' . esc_url($insurer_linkedin) . '" target="_blank" style="color:#0a66c2;font-weight:500;">in LinkedIn</a></div>';
            }
            echo '  </div>';
            echo '</div>';
            echo '</li>';
        } catch (Exception $e) {
            echo '<li class="maljani-policy-item error">Erreur lors de l\'affichage de la policy : ' . esc_html($e->getMessage()) . '</li>';
            error_log('Erreur render_policy_item : ' . $e->getMessage());
        }
    }

    public function get_insurer_profile() {
        try {
            $insurer_id = intval($_POST['insurer_id']);
            $user = get_userdata($insurer_id);
            if ($user) {
                $logo = get_user_meta($insurer_id, 'insurer_logo', true);
                $profile = get_user_meta($insurer_id, 'insurer_profile', true);
                $website = get_user_meta($insurer_id, 'insurer_website', true);
                $linkedin = get_user_meta($insurer_id, 'insurer_linkedin', true);
                echo '<div class="popup-profile-content" style="min-width:260px;max-width:340px;">';
                if ($logo) {
                    echo '<div style="text-align:center;margin-bottom:12px;"><img src="' . esc_url($logo) . '" alt="Logo" style="width:64px;height:64px;object-fit:cover;border-radius:50%;box-shadow:0 2px 8px rgba(24,49,83,0.10);"></div>';
                }
                echo '<h3 style="margin-bottom:8px;">' . esc_html($user->display_name) . '</h3>';
                if ($profile) {
                    echo '<div style="font-size:1em;color:#222;margin-bottom:10px;">' . esc_html($profile) . '</div>';
                }
                if ($website) {
                    echo '<div style="margin-bottom:6px;"><a href="' . esc_url($website) . '" target="_blank" style="color:#0073aa;font-weight:500;">🌐 Site web</a></div>';
                }
                if ($linkedin) {
                    echo '<div><a href="' . esc_url($linkedin) . '" target="_blank" style="color:#0a66c2;font-weight:500;">in LinkedIn</a></div>';
                }
                echo '</div>';
            } else {
                echo '<div class="popup-profile-content">Profile not found.</div>';
            }
        } catch (Exception $e) {
            echo '<div class="popup-profile-content">Error: ' . esc_html($e->getMessage()) . '</div>';
        }
        wp_die();
    }
}
new Maljani_Filter();