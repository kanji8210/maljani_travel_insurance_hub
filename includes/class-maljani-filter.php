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
    <div class="maljani-filter-container wizard-layout" 
         x-data="{ 
            step: 1, 
            region: '', 
            departure: '', 
            returnDate: '',
            days: 0,
            loading: false,
            results: '',
            calculateDays() {
                if (this.departure && this.returnDate) {
                    const d1 = new Date(this.departure);
                    const d2 = new Date(this.returnDate);
                    if (d2 > d1) {
                        this.days = Math.ceil(Math.abs(d2 - d1) / (1000 * 60 * 60 * 24));
                    } else {
                        this.days = 0;
                    }
                }
            },
            async generateQuote() {
                if (!this.departure || !this.returnDate) {
                    alert('Please select both departure and return dates.');
                    return;
                }
                if (this.days <= 0) {
                    alert('Return date must be after departure date.');
                    return;
                }
                
                this.loading = true;
                this.step = 3;
                
                try {
                    const response = await jQuery.post(maljaniFilter.ajax_url, {
                        action: 'maljani_filter_policies',
                        departure: this.departure,
                        return: this.returnDate,
                        region: this.region,
                        security: maljaniFilter.security
                    });
                    
                    if (response.success && response.data && response.data.html) {
                        setTimeout(() => {
                            this.results = response.data.html;
                            this.loading = false;
                            // Initialize Lucide after results load
                            setTimeout(() => { if(window.lucide) lucide.createIcons(); }, 100);
                        }, 1500);
                    } else {
                        alert('No policies found.');
                        this.step = 2;
                        this.loading = false;
                    }
                } catch (e) {
                    alert('Error loading policies.');
                    this.step = 2;
                    this.loading = false;
                }
            }
         }">
        <div class="maljani-wizard-wrapper" x-show="!results">
            <div class="wizard-progress">
                <div class="progress-step" :class="{ 'active': step === 1, 'completed': step > 1 }">
                    <span class="step-num" x-show="step <= 1">1</span>
                    <span class="step-num completed" x-show="step > 1"><i data-lucide="check" style="width:16px;height:16px;"></i></span>
                    <span class="step-label">Destination</span>
                </div>
                <div class="progress-step" :class="{ 'active': step === 2, 'completed': step > 2 }">
                    <span class="step-num" x-show="step <= 2">2</span>
                    <span class="step-num completed" x-show="step > 2"><i data-lucide="check" style="width:16px;height:16px;"></i></span>
                    <span class="step-label">Dates</span>
                </div>
                <div class="progress-step" :class="{ 'active': step === 3 }">
                    <span class="step-num">3</span>
                    <span class="step-label">Results</span>
                </div>
                <div class="progress-line">
                    <div class="progress-fill" :style="'width: ' + ((step - 1) / 2 * 100) + '%'"></div>
                </div>
            </div>

            <form id="maljani-policy-filter-form" class="wizard-form" @submit.prevent="generateQuote">
                <!-- STEP 1: DESTINATION -->
                <div class="wizard-step" :class="{ 'active': step === 1 }" x-show="step === 1">
                    <div class="step-header">
                        <h2>Which region are you traveling to?</h2>
                        <p>Select your destination to see specific coverage plans.</p>
                    </div>
                    <div class="region-grid">
                        <div class="region-card" :class="{ 'active': region === '' }" @click="region = ''; step = 2">
                            <div class="region-icon"><i data-lucide="globe"></i></div>
                            <span class="region-name">Worldwide</span>
                        </div>
                        <?php
                        $regions = get_terms(array('taxonomy' => 'policy_region', 'hide_empty' => false));
                        if (!is_wp_error($regions)) {
                            foreach ($regions as $region) {
                                $icon = 'map-pin';
                                $name = esc_html($region->name);
                                if (stripos($name, 'Europe') !== false) $icon = 'palmtree';
                                elseif (stripos($name, 'Africa') !== false) $icon = 'mountain';
                                elseif (stripos($name, 'Asia') !== false) $icon = 'landmark';
                                elseif (stripos($name, 'America') !== false) $icon = 'flag';
                                
                                echo '<div class="region-card" :class="{ \'active\': region == \'' . esc_attr($region->term_id) . '\' }" @click="region = \'' . esc_attr($region->term_id) . '\'; step = 2">';
                                echo '  <div class="region-icon"><i data-lucide="' . $icon . '"></i></div>';
                                echo '  <span class="region-name">' . $name . '</span>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                    <input type="hidden" name="region_id" x-model="region">
                </div>

                <!-- STEP 2: DATES -->
                <div class="wizard-step" :class="{ 'active': step === 2 }" x-show="step === 2">
                    <div class="step-header">
                        <h2>When is your trip?</h2>
                        <p>We need your travel dates to calculate the exact premium.</p>
                    </div>
                    <div class="date-selection-grid">
                        <div class="filter-group">
                            <label>Departure Date</label>
                            <div class="input-with-icon">
                                <span class="input-icon"><i data-lucide="calendar"></i></span>
                                <input type="date" name="departure" class="filter-input" x-model="departure" @change="calculateDays" required>
                            </div>
                        </div>
                        <div class="filter-group">
                            <label>Return Date</label>
                            <div class="input-with-icon">
                                <span class="input-icon"><i data-lucide="plane-takeoff"></i></span>
                                <input type="date" name="return" class="filter-input" x-model="returnDate" @change="calculateDays" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="trip-summary-box" x-show="days > 0" x-transition>
                        <span class="summary-icon"><i data-lucide="clock"></i></span>
                        <span class="summary-text">Trip Duration: <strong x-text="days"></strong> days</span>
                    </div>

                    <div class="wizard-actions">
                        <button type="button" class="mj-btn-back" @click="step = 1"><i data-lucide="arrow-left" style="width:18px;height:18px;display:inline;vertical-align:middle;margin-right:8px;"></i> Back</button>
                        <button type="submit" class="mj-btn-next mj-btn-primary">Generate Quote <i data-lucide="sparkles" style="width:18px;height:18px;display:inline;vertical-align:middle;margin-left:8px;"></i></button>
                    </div>
                </div>

                <!-- STEP 3: LOADING -->
                <div class="wizard-step" :class="{ 'active': step === 3 }" x-show="step === 3 && loading">
                    <div class="results-transition">
                        <div class="transition-loader">
                            <div class="pulse-ring"></div>
                            <span class="loader-icon"><i data-lucide="shield-check" style="width:48px;height:48px;"></i></span>
                        </div>
                        <h3>Finding your perfect plans...</h3>
                        <p>Comparing coverage and benefits from our partners.</p>
                    </div>
                </div>
            </form>
        </div>

        <div id="maljani-policy-results" class="wizard-results-container" x-show="results" x-transition>
            <div class="results-toolbar">
                <button class="mj-btn-outline" @click="results = ''; step = 1"><i data-lucide="refresh-cw" style="width:16px;height:16px;display:inline;vertical-align:middle;margin-right:8px;"></i> Start New Quote</button>
                <div id="active-filters-summary"></div>
            </div>
            <div class="maljani-results-grid-anchor" x-html="results">
            </div>
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
            'redirect' => '', 
        ), $atts);
        
        ob_start();
        try {
    ?>
    <div class="maljani-filter-container compact-wizard" 
         x-data="{ 
            step: 1, 
            region: '', 
            departure: '', 
            returnDate: '',
            redirectUrl: '<?php echo esc_url($atts['redirect']); ?>',
            submitForm() {
                if (!this.departure || !this.returnDate) {
                    alert('Please select travel dates.');
                    return;
                }
                if (this.redirectUrl) {
                    const url = new URL(this.redirectUrl, window.location.origin);
                    url.searchParams.set('departure', this.departure);
                    url.searchParams.set('return', this.returnDate);
                    if (this.region) url.searchParams.set('region_id', this.region);
                    window.location.href = url.toString();
                } else {
                    // Fallback to standard submit if no redirect
                    document.getElementById('compact-submit-btn').click();
                }
            }
         }">
        
        <div class="maljani-wizard-wrapper">
            <!-- COMPACT STEPPER -->
            <div class="wizard-progress compact">
                <div class="progress-step" :class="{ 'active': step === 1, 'completed': step > 1 }">
                    <span class="step-num" x-show="step <= 1">1</span>
                    <span class="step-num completed" x-show="step > 1"><i data-lucide="check" style="width:14px;height:14px;"></i></span>
                    <span class="step-label">Destination</span>
                </div>
                <div class="progress-step" :class="{ 'active': step === 2 }">
                    <span class="step-num">2</span>
                    <span class="step-label">Dates</span>
                </div>
                <div class="progress-line">
                    <div class="progress-fill" :style="'width: ' + ((step - 1) * 100) + '%'"></div>
                </div>
            </div>

            <form id="maljani-compact-filter-form" class="wizard-form" @submit.prevent="submitForm">
                <!-- STEP 1: DESTINATION (Compact Grid) -->
                <div class="wizard-step" :class="{ 'active': step === 1 }" x-show="step === 1" x-transition>
                    <div class="step-header">
                        <h3>Where to?</h3>
                        <p>Select your region to quick-start your quote.</p>
                    </div>
                    <div class="region-grid compact">
                        <div class="region-card" :class="{ 'active': region === '' }" @click="region = ''; step = 2">
                            <div class="region-icon"><i data-lucide="globe"></i></div>
                            <span class="region-name">Worldwide</span>
                        </div>
                        <?php
                        $regions = get_terms(array('taxonomy' => 'policy_region', 'hide_empty' => false));
                        if (!is_wp_error($regions)) {
                            foreach ($regions as $region) {
                                $icon = 'map-pin';
                                $name = esc_html($region->name);
                                if (stripos($name, 'Europe') !== false) $icon = 'palmtree';
                                elseif (stripos($name, 'Africa') !== false) $icon = 'mountain';
                                elseif (stripos($name, 'Asia') !== false) $icon = 'landmark';
                                elseif (stripos($name, 'America') !== false) $icon = 'flag';
                                
                                echo '<div class="region-card" :class="{ \'active\': region == \'' . esc_attr($region->term_id) . '\' }" @click="region = \'' . esc_attr($region->term_id) . '\'; step = 2">';
                                echo '  <div class="region-icon"><i data-lucide="' . $icon . '"></i></div>';
                                echo '  <span class="region-name">' . $name . '</span>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                    <input type="hidden" name="region_id" x-model="region">
                </div>

                <!-- STEP 2: DATES -->
                <div class="wizard-step" :class="{ 'active': step === 2 }" x-show="step === 2" x-transition>
                    <div class="step-header">
                        <h3>Travel Dates</h3>
                    </div>
                    <div class="date-input-group">
                        <div class="filter-input-wrapper">
                            <label>Departure</label>
                            <input type="date" x-model="departure" class="filter-input" required>
                        </div>
                        <div class="filter-input-wrapper">
                            <label>Return</label>
                            <input type="date" x-model="returnDate" class="filter-input" required>
                        </div>
                    </div>
                    
                    <div class="wizard-actions">
                        <button type="button" @click="step = 1" class="mj-btn-text">
                            <i data-lucide="chevron-left"></i> Change Region
                        </button>
                        <button type="submit" class="mj-btn-primary">
                            Get Quote <i data-lucide="sparkles"></i>
                        </button>
                    </div>
                    <button type="submit" id="compact-submit-btn" style="display:none;"></button>
                </div>
            </form>
        </div>
    </div>
    <?php
        } catch (Exception $e) {
            echo '<div class="error">Error: ' . esc_html($e->getMessage()) . '</div>';
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
            // Enqueue Alpine.js
            wp_enqueue_script(
                'alpinejs',
                'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
                array(),
                '3.x.x',
                true
            );

            // Enqueue Lucide Icons
            wp_enqueue_script(
                'lucide-icons',
                'https://unpkg.com/lucide@latest',
                array(),
                'latest',
                true
            );

            // Enqueue filter script and localize AJAX parameters
            wp_enqueue_script(
                'maljani-filter',
                plugin_dir_url(__FILE__) . '/js/maljani-filter.js',
                array('jquery', 'alpinejs', 'lucide-icons'),
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
                    echo '<div class="results-header">';
                    echo '  <h2>Policies found for <strong>' . esc_html($days) . ' days</strong> of travel</h2>';
                    echo '</div>';
                }
                echo '<ul class="maljani-policy-grid-ajax">';
                while ($query->have_posts()) {
                    $query->the_post();
                    $this->render_policy_item(get_the_ID(), $days);
                }
                echo '</ul>';
            } else {
                echo '<div class="no-results-box">';
                echo '  <div class="no-results-icon"><i data-lucide="search-x"></i></div>';
                echo '  <h3>No policies found</h3>';
                echo '  <p>Please widen your search or try different travel dates. No policy was found to match your criteria.</p>';
                echo '</div>';
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
            
            // Header: Centered Logo and Title
            echo '<div class="policy-card-header">';
            if ($insurer_logo) {
                echo '  <div class="insurer-logo-wrapper">';
                echo '    <img src="' . esc_url($insurer_logo) . '" alt="' . esc_attr($insurer_name) . '" class="insurer-emblem">';
                echo '  </div>';
            }
            echo '  <div class="policy-title-box">';
            echo '    <span class="policy-category-badge">' . esc_html($region_name) . '</span>';
            echo '    <h3><a href="' . esc_url(get_permalink($policy_id)) . '">' . esc_html(get_the_title($policy_id)) . '</a></h3>';
            echo '    <p class="underwriter-name">by ' . esc_html($insurer_name ?: 'Approved Partner') . '</p>';
            echo '  </div>';
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

            echo '</div>';
            
            echo '<div class="policy-card-actions">';
            echo '  <div class="benefits-link-container">';
            echo '    <span class="see-benefits-link see-benefits" data-policy-id="' . esc_attr($policy_id) . '">';
            echo '      <span class="icon"><i data-lucide="search"></i></span> View Full Benefits & Coverage';
            echo '    </span>';
            echo '  </div>';
            
            $sale_page_id = get_option('maljani_page_policy_sale'); 
            if (!$sale_page_id) $sale_page_id = get_option('maljani_policy_sale_page');

            if ($premium && $days > 0 && $sale_page_id) {
                $sale_url = add_query_arg([
                    'policy_id' => $policy_id,
                    'departure' => isset($_POST['departure']) ? $_POST['departure'] : '',
                    'return' => isset($_POST['return']) ? $_POST['return'] : '',
                    'days' => $days
                ], get_permalink($sale_page_id));
                echo '<a href="' . esc_url($sale_url) . '" class="policy-buy-btn">Choose This Plan</a>';
            } else {
                echo '<button class="policy-buy-btn" style="opacity:0.5; cursor:not-allowed;" disabled>Choose This Plan</button>';
            }
            
            // Benefits Hidden Modal Content
            $benefits = get_post_meta($policy_id, '_policy_benefits', true);
            echo '<div class="policy-benefits-popup" id="policy-benefits-' . esc_attr($policy_id) . '" style="display:none;">';
            echo '  <div class="popup-benefits-content" style="max-width:400px; padding:30px; background:white; border-radius:32px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid rgba(255,255,255,0.8); backdrop-filter: blur(10px);">';
            echo '    <h4 style="margin-bottom:20px; font-size:20px; font-weight:800; border-bottom:1px solid #f1f5f9; padding-bottom:15px; color:#1e293b; display:flex; align-items:center; gap:10px;"><i data-lucide="shield-check" style="color:#4f46e5;"></i> Coverage Benefits</h4>';
            echo '    <div style="font-size:15px; color:#64748b; line-height:1.7;">' . wp_kses_post($benefits ?: 'Contact support for detailed benefits.') . '</div>';
            echo '    <button onclick="jQuery(\'#policy-benefits-' . esc_attr($policy_id) . '\').hide();" style="margin-top:25px; width:100%; padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; font-weight:700; cursor:pointer; color:#475569; transition:all 0.2s;">Close</button>';
            echo '  </div>';
            echo '</div>';
            
            echo '</div>'; // actions
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