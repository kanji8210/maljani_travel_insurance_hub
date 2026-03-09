<?php
class Maljani_Filter {
    public function __construct() {
        add_shortcode('maljani_policy_ajax_filter', array($this, 'render_filter_form'));
        add_shortcode('maljani_filter_form', array($this, 'render_filter_form_only'));
        add_shortcode('maljani_policy_grid', array($this, 'render_policy_grid'));
        
        // Aliases for Page Management compatibility
        add_shortcode('maljani_policy_catalog', array($this, 'render_filter_form'));
        add_shortcode('maljani_quick_quote', array($this, 'render_filter_form_only'));
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
        :root {
            --maljani-primary: #4f46e5;
            --maljani-secondary: #0f172a;
            --maljani-accent: #818cf8;
            --maljani-glass-bg: rgba(255, 255, 255, 0.7);
            --maljani-glass-border: rgba(255, 255, 255, 0.8);
            --maljani-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }

        .maljani-filter-wrapper {
            background: var(--maljani-glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--maljani-glass-border);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 50px;
            box-shadow: var(--maljani-shadow);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .filter-header { margin-bottom: 25px; text-align: left; }
        .filter-header h2 { font-size: 24px; font-weight: 800; color: var(--maljani-secondary); margin: 0; }
        
        #maljani-policy-filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            align-items: flex-end;
        }

        .filter-group { display: flex; flex-direction: column; gap: 8px; }
        .filter-group label { font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }

        .filter-input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #1e293b;
            font-weight: 500;
            transition: all 0.2s;
        }
        .filter-input:focus { border-color: var(--maljani-primary); outline: none; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

        .region-toggles { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .region-btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .region-btn.active {
            background: var(--maljani-primary);
            color: white;
            border-color: var(--maljani-primary);
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }

        .maljani-policy-grid-ajax {
            display: grid;
            grid-template-columns: repeat(<?php echo esc_attr($columns); ?>, 1fr);
            gap: 24px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .maljani-policy-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            height: 100%;
        }
        .maljani-policy-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border-color: #e2e8f0; }

        .policy-card-header {
            padding: 24px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .policy-title-box h3 { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; line-height: 1.3; }
        .policy-title-box h3 a { color: inherit; text-decoration: none; }
        
        .insurer-emblem {
            width: 48px; height: 48px;
            background: white;
            border-radius: 12px;
            padding: 4px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            object-fit: contain;
            border: 1px solid #f1f5f9;
        }

        .policy-card-body { padding: 24px; flex-grow: 1; display: flex; flex-direction: column; gap: 16px; }
        .policy-meta-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #64748b; }
        .policy-label { font-weight: 600; color: #94a3b8; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }

        .premium-box {
            background: #f0f9ff;
            border: 1px dashed #bae6fd;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }
        .premium-value { display: block; font-size: 20px; font-weight: 800; color: #0369a1; }
        .premium-days { font-size: 12px; color: #0ea5e9; font-weight: 600; }

        .policy-card-footer { padding: 20px 24px; border-top: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: 12px; }
        .policy-buy-btn {
            width: 100%;
            background: var(--maljani-primary);
            color: white !important;
            text-align: center;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }
        .policy-buy-btn:hover { background: #4338ca; transform: scale(1.02); }
        
        .see-benefits-link {
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            text-decoration: underline;
        }
        .see-benefits-link:hover { color: var(--maljani-primary); }

        @media (max-width: 1024px) {
            .maljani-policy-grid-ajax { grid-template-columns: repeat(<?php echo min(2, $columns); ?>, 1fr); }
        }
        @media (max-width: 700px) {
            .maljani-policy-grid-ajax { grid-template-columns: 1fr; }
            .maljani-filter-wrapper { padding: 24px; }
        }
    </style>
    <div class="maljani-filter-container">
        <div class="maljani-filter-wrapper" data-columns="<?php echo esc_attr($columns); ?>">
            <div class="filter-header">
                <h2>Find Your Perfect Plan</h2>
            </div>
            <form id="maljani-policy-filter-form">
                <div class="filter-group">
                    <label>Departure Date</label>
                    <input type="date" name="departure" class="filter-input" required>
                </div>
                <div class="filter-group">
                    <label>Return Date</label>
                    <input type="date" name="return" class="filter-input" required>
                </div>
                <div class="filter-group" style="grid-column: span 2;">
                    <label>Destination / Region</label>
                    <div class="region-toggles">
                        <button type="button" class="region-btn active" data-region="">All Regions</button>
                        <?php
                        $regions = get_terms(array('taxonomy' => 'policy_region', 'hide_empty' => false));
                        if (!is_wp_error($regions)) {
                            foreach ($regions as $region) {
                                echo '<button type="button" class="region-btn" data-region="' . esc_attr($region->term_id) . '">' . esc_html($region->name) . '</button>';
                            }
                        }
                        ?>
                    </div>
                    <input type="hidden" name="region_id" id="maljani-region-input" value="">
                </div>
                <!-- The form is handled by AJAX in maljani-filter.js -->
            </form>
        </div>

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
            // Enqueue filter script and localize AJAX parameters (include legacy keys)
            wp_enqueue_script(
                'maljani-filter',
                plugin_dir_url(__FILE__) . '/js/maljani-filter.js',
                array('jquery'),
                defined('MALJANI_VERSION') ? MALJANI_VERSION : null,
                true
            );
            wp_localize_script('maljani-filter', 'maljaniFilter', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'security' => wp_create_nonce('maljani_filter_nonce')
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
            $insurer_name = '';
            $insurer_logo = '';
            
            if ($insurer_id) {
                $insurer_name = get_the_title($insurer_id);
                $insurer_logo = get_post_meta($insurer_id, '_insurer_logo', true);
                if (!$insurer_logo && has_post_thumbnail($insurer_id)) {
                    $insurer_logo = get_the_post_thumbnail_url($insurer_id, 'thumbnail');
                }
            }

            $regions = get_the_terms($policy_id, 'policy_region');
            $region_name = ($regions && !is_wp_error($regions)) ? $regions[0]->name : 'Global';

            $premium = '';
            $currency = get_post_meta($policy_id, '_policy_currency', true) ?: 'KSH';
            
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
            
            // Header: Title and Insurer Emblem
            echo '<div class="policy-card-header">';
            echo '  <div class="policy-title-box">';
            echo '    <h3><a href="' . esc_url(get_permalink($policy_id)) . '">' . esc_html(get_the_title($policy_id)) . '</a></h3>';
            echo '    <span class="policy-region" style="font-size:12px; color:#64748b;">📍 ' . esc_html($region_name) . '</span>';
            echo '  </div>';
            if ($insurer_logo) {
                echo '  <img src="' . esc_url($insurer_logo) . '" alt="' . esc_attr($insurer_name) . '" class="insurer-emblem" title="Insurer: ' . esc_attr($insurer_name) . '">';
            }
            echo '</div>';

            // Body: Metadata rows
            echo '<div class="policy-card-body">';
            
            if ($premium && $days > 0) {
                echo '<div class="premium-box">';
                echo '  <span class="premium-value">' . esc_html($currency) . ' ' . esc_html(number_format($premium)) . '</span>';
                echo '  <span class="premium-days">Total for ' . esc_html($days) . ' days</span>';
                echo '</div>';
            } else {
                echo '<div class="premium-box" style="background:#f1f5f9; border-color:#e2e8f0;">';
                echo '  <span class="premium-value" style="color:#64748b; font-size:16px;">Enter travel dates</span>';
                echo '  <span class="premium-days" style="color:#94a3b8;">to calculate premium</span>';
                echo '</div>';
            }

            echo '  <div class="policy-meta-row">';
            echo '    <span class="policy-label">Underwriter</span>';
            echo '    <span>' . esc_html($insurer_name ?: 'Approved Partner') . '</span>';
            echo '  </div>';
            echo '</div>';
            
            // Footer: Actions
            echo '<div class="policy-card-footer">';
            $sale_page_id = get_option('maljani_page_policy_sale'); // Use the generic slug if possible, or fallback
            if (!$sale_page_id) $sale_page_id = get_option('maljani_policy_sale_page');

            if ($premium && $days > 0 && $sale_page_id) {
                $sale_url = add_query_arg([
                    'policy_id' => $policy_id,
                    'departure' => isset($_POST['departure']) ? $_POST['departure'] : '',
                    'return' => isset($_POST['return']) ? $_POST['return'] : '',
                    'days' => $days
                ], get_permalink($sale_page_id));
                echo '<a href="' . esc_url($sale_url) . '" class="policy-buy-btn">Select Plan</a>';
            } else {
                echo '<button class="policy-buy-btn" style="opacity:0.5; cursor:not-allowed;" disabled>Select Plan</button>';
            }
            
            echo '<span class="see-benefits-link see-benefits" data-policy-id="' . esc_attr($policy_id) . '">View Full Coverage & Benefits</span>';
            
            // Benefits Hidden Modal Content
            $benefits = get_post_meta($policy_id, '_policy_benefits', true);
            echo '<div class="policy-benefits-popup" id="policy-benefits-' . esc_attr($policy_id) . '" style="display:none;">';
            echo '  <div class="popup-benefits-content" style="max-width:400px; padding:30px; background:white; border-radius:16px;">';
            echo '    <h4 style="margin-bottom:15px; font-size:18px; font-weight:800; border-bottom:1px solid #eee; padding-bottom:10px;">🛡️ Coverage Benefits</h4>';
            echo '    <div style="font-size:14px; color:#475569; line-height:1.6;">' . wp_kses_post($benefits ?: 'Contact support for detailed benefits.') . '</div>';
            echo '    <button onclick="jQuery(\'#policy-benefits-' . esc_attr($policy_id) . '\').hide();" style="margin-top:20px; width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; cursor:pointer;">Close</button>';
            echo '  </div>';
            echo '</div>';
            
            echo '</div>'; // footer
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