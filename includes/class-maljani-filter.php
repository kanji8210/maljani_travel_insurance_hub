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
        <form id="maljani-policy-filter-form" style="display:flex;flex-direction:column;gap:20px;margin-bottom:30px;">
            <!-- Date inputs -->
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <label style="margin:0;">
                    Departure Date:
                    <input type="date" name="departure" style="margin-left:8px;padding:8px;border:1px solid #ddd;border-radius:4px;" required>
                </label>
                <label style="margin:0;">
                    Return Date:
                    <input type="date" name="return" style="margin-left:8px;padding:8px;border:1px solid #ddd;border-radius:4px;" required>
                </label>
            </div>
            
            <!-- Region filter buttons -->
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-weight:bold;">Filter by Region:</span>
                <button type="button" class="region-filter-btn active" data-region="" style="padding:8px 16px;border:1px solid #0073aa;background:#0073aa;color:white;border-radius:4px;cursor:pointer;transition:all 0.3s ease;">All Regions</button>
                <?php
                $regions = get_terms(array('taxonomy' => 'policy_region', 'hide_empty' => false));
                if (!is_wp_error($regions)) {
                    foreach ($regions as $region) {
                        echo '<button type="button" class="region-filter-btn" data-region="' . esc_attr($region->term_id) . '" style="padding:8px 16px;border:1px solid #0073aa;background:white;color:#0073aa;border-radius:4px;cursor:pointer;transition:all 0.3s ease;">' . esc_html($region->name) . '</button>';
                    }
                }
                ?>
            </div>
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
            $departure = sanitize_text_field($_POST['departure']);
            $return = sanitize_text_field($_POST['return']);
            
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
            $this->render_policy_list($args, $days);
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

    public function render_policy_list($args = array(), $days = 0) {
        try {
            $defaults = array(
                'post_type' => 'policy',
                'posts_per_page' => 12,
            );
            $args = wp_parse_args($args, $defaults);
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                if ($days > 0) {
                    echo '<h2>Policies found for ' . esc_html($days) . ' days of travel</h2>';
                } else {
                    echo '<h2>Policies found</h2>';
                }
                echo '<div class="thumbnail-grid">';
                while ($query->have_posts()) {
                    $query->the_post();
                    $this->render_policy_item(get_the_ID(), $days);
                }
                echo '</div>';
            } else {
                echo '<p>Please widen your search - no policy was found to match your criteria</p>';
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

            echo '<li class="maljani-policy-item" style="display:flex;align-items:flex-start;margin-bottom:24px;border:1px solid #ddd;border-radius:8px;padding:16px;">';
            echo '<div class="maljani-policy-infos" style="flex:1;">';
            echo '<h3 style="margin:0 0 12px 0;"><a href="' . esc_url(get_permalink($policy_id)) . '">' . esc_html(get_the_title($policy_id)) . '</a></h3>';
            
            if ($insurer_id && $insurer_name) {
                echo '<div style="display:flex;align-items:center;margin-bottom:8px;">';
                if ($insurer_logo) {
                    echo '<img src="' . esc_url($insurer_logo) . '" alt="Logo" style="width:32px;height:32px;object-fit:cover;border-radius:50%;margin-right:8px;">';
                }
                echo '<span class="insurer-name" data-insurer-id="' . esc_attr($insurer_id) . '" style="font-weight:bold;">Insurer: ' . esc_html($insurer_name) . '</span>';
                echo '</div>';
            }
            
            if ($region_name) {
                echo '<div style="margin-bottom:8px;"><strong>Region:</strong> ' . esc_html($region_name) . '</div>';
            }
            
            // Affichage du premium si calculé
            if ($premium && $days > 0) {
                echo '<div style="margin-bottom:12px;padding:12px;background:#e8f5e8;border-radius:6px;">';
                echo '<strong style="color:#2d5d2d;">Premium for ' . esc_html($days) . ' days: ' . esc_html($premium) . '</strong>';
                echo '</div>';
            }
            // Lien et bloc pour les bénéfices (toujours affiché)
            echo '<div class="policy-benefits-link" style="margin-bottom:12px;">';
            echo '<a href="#" class="see-benefits" data-policy-id="' . esc_attr($policy_id) . '" style="color:#0073aa;font-weight:500;text-decoration:underline;cursor:pointer;">See benefits</a>';
            echo '</div>';
            $benefits = get_post_meta($policy_id, '_policy_benefits', true);
            echo '<div class="policy-benefits-popup" id="policy-benefits-' . esc_attr($policy_id) . '" style="display:none;">';
            if ($benefits) {
                echo '<div class="popup-benefits-content" style="min-width:220px;max-width:550px;">';
                echo '<h4 style="margin-bottom:10px;">Policy Benefits</h4>';
                echo '<div style="font-size:1em;color:#222;">' . nl2br(esc_html($benefits)) . '</div>';
                echo '</div>';
            } else {
                echo '<div class="popup-benefits-content">No benefits listed for this policy.</div>';
            }
            echo '</div>';
            
            echo '<div style="margin-bottom:12px;">' . esc_html($excerpt) . '</div>';
            
            // Add purchase link if premium is available
            if ($premium && $days > 0) {
                $sale_page_id = get_option('maljani_policy_sale_page');
                if ($sale_page_id) {
                    $sale_url = add_query_arg([
                        'policy_id' => $policy_id,
                        'departure' => isset($_POST['departure']) ? $_POST['departure'] : '',
                        'return' => isset($_POST['return']) ? $_POST['return'] : ''
                    ], get_permalink($sale_page_id));
                    echo '<a href="' . esc_url($sale_url) . '" style="display:inline-block;padding:10px 20px;background:#0073aa;color:white;text-decoration:none;border-radius:4px;">Buy Now - ' . esc_html($premium) . '</a>';
                }
            }
            
            echo '</div>';
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