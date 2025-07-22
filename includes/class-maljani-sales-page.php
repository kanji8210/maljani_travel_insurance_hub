<?php
//includes\class-maljani-sales-page.php

class Maljani_Sales_Page {
    public function __construct() {
        add_shortcode('maljani_policy_sale', [$this, 'render_sales_form']);
        add_shortcode('maljani_sales_form', [$this, 'render_sales_form']); // Backward compatibility
        add_action('wp_enqueue_scripts', [$this, 'enqueue_style']);
        add_action('init', [$this, 'handle_form_submission']);
        add_action('init', [$this, 'handle_policy_redirection']); // Ajouter la gestion des redirections

        // Vérifie et crée la table si elle n'existe pas
        add_action('init', [$this, 'check_and_create_table']);
        
        // Ajoute un message d'avertissement pour les administrateurs si la page n'est pas configurée
        add_action('admin_notices', [$this, 'check_sales_page_configured']);
        
        // Solution de secours pour afficher le formulaire sur n'importe quelle page avec le paramètre
        add_filter('the_content', [$this, 'maybe_inject_sales_form']);
    }
    
    // Nouvelle fonction pour gérer les redirections de sélection de police
    public function handle_policy_redirection() {
        // Ne pas rediriger automatiquement - laisser le formulaire fonctionner naturellement
        // Cette fonction est maintenant désactivée pour éviter les boucles de redirection
        return;
    }
    
    // Injecte le formulaire de vente dans le contenu si maljani_sales=1 est défini
    public function maybe_inject_sales_form($content) {
        // Afficher le formulaire si maljani_sales=1 OU si nous avons des paramètres de police/région
        if ((isset($_GET['maljani_sales']) && $_GET['maljani_sales']) || 
            (isset($_GET['policy_id']) || isset($_GET['region_id']) || (isset($_GET['departure']) && isset($_GET['return'])))) {
            $form = $this->render_sales_form();
            return $content . '<div class="maljani-injected-sales-form">' . $form . '</div>';
        }
        return $content;
    }
    
    public function check_sales_page_configured() {
        // Affiche un message d'avertissement uniquement dans le tableau de bord admin
        if (is_admin() && current_user_can('manage_options')) {
            $sale_page_id = get_option('maljani_policy_sale_page');
            if (!$sale_page_id) {
                echo '<div class="notice notice-warning is-dismissible"><p>';
                echo '<strong>Insurance Hub:</strong> La page de vente de police n\'est pas configurée. ';
                echo 'Veuillez <a href="' . admin_url('admin.php?page=maljani-settings') . '">configurer une page</a> avec le shortcode [maljani_policy_sale].';
                echo '</p></div>';
            }
        }
    }

    public function check_and_create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            if (file_exists(plugin_dir_path(__FILE__) . 'class-maljani-activator.php')) {
                require_once plugin_dir_path(__FILE__) . 'class-maljani-activator.php';
                if (class_exists('Maljani_Activator')) {
                    Maljani_Activator::activate();
                }
            }
        }
    }

    public function enqueue_style() {
        // Charge le CSS si le shortcode est utilisé OU si c'est la page de vente configurée OU si on a des paramètres de police
        $sale_page_id = get_option('maljani_policy_sale_page');
        $should_enqueue = false;
        
        // Vérifier si nous avons des paramètres indiquant qu'on doit afficher le formulaire
        $has_sales_params = isset($_GET['maljani_sales']) || isset($_GET['policy_id']) || isset($_GET['region_id']) ||
                           (isset($_GET['departure']) && isset($_GET['return']));
        
        if (is_singular()) {
            // Vérifie si c'est la page de vente configurée
            if ($sale_page_id && is_page($sale_page_id)) {
                $should_enqueue = true;
            }
            // Ou si la page contient l'un des shortcodes
            elseif (has_shortcode(get_post()->post_content, 'maljani_policy_sale') || has_shortcode(get_post()->post_content, 'maljani_sales_form')) {
                $should_enqueue = true;
            }
            // Ou si nous avons des paramètres de vente
            elseif ($has_sales_params) {
                $should_enqueue = true;
            }
        }
        
        if ($should_enqueue) {
            wp_enqueue_style(
                'maljani-sales-form-style',
                plugin_dir_url(__FILE__) . '../templates/sales-form.css',
                [],
                null
            );
            wp_enqueue_script(
                'maljani-sales-form-js',
                plugin_dir_url(__FILE__) . '../templates/sales-form.js',
                ['jquery'],
                null,
                true
            );
        }
    }

    public function render_sales_form() {
        // Si nous sommes sur la page d'accueil avec le paramètre maljani_sales, vérifier si nous devons afficher le formulaire
        if (is_front_page() && isset($_GET['maljani_sales']) && $_GET['maljani_sales']) {
            // Nous sommes sur la page d'accueil avec le paramètre, donc le shortcode n'a pas été configuré
            echo '<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">';
            echo '<strong>Attention:</strong> Vous utilisez la page d\'accueil comme page de vente, ce qui n\'est pas recommandé. ';
            echo 'Veuillez configurer une page dédiée avec le shortcode [maljani_policy_sale] dans les paramètres du plugin.';
            echo '</div>';
        }
        
        // Notifications
        if (isset($_GET['sale_success'])) {
            echo '<div class="notice notice-success" style="background:#d4edda;color:#155724;padding:12px;border-radius:6px;margin-bottom:16px;">Sale saved successfully!</div>';
        }
        if (isset($_GET['sale_error'])) {
            echo '<div class="notice notice-error" style="background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;margin-bottom:16px;">An error occurred. Please try again.</div>';
        }

        // Récupère l'utilisateur courant et son rôle
        $current_user = wp_get_current_user();
        $user_role = ($current_user->exists()) ? $current_user->roles[0] : '';
        $is_agent = ($user_role === 'insurer');
        $is_client = ($user_role === 'insured');

        // Récupère les paramètres GET
        $policy_id = isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0;
        $region_id = isset($_GET['region_id']) ? intval($_GET['region_id']) : 0;
        $departure = isset($_GET['departure']) ? sanitize_text_field($_GET['departure']) : '';
        $return = isset($_GET['return']) ? sanitize_text_field($_GET['return']) : '';
        $days = 0;
        if ($departure && $return) {
            $d1 = new DateTime($departure);
            $d2 = new DateTime($return);
            $days = $d1 < $d2 ? $d1->diff($d2)->days : 0;
        }

        // Informations sur la police et la région
        $policy_title = $policy_id ? get_the_title($policy_id) : '';
        $region_name = '';
        $region_title = '';
        
        if ($region_id) {
            $region_term = get_term($region_id, 'policy_region');
            if ($region_term && !is_wp_error($region_term)) {
                $region_title = $region_term->name;
            }
        }
        
        if ($policy_id) {
            $regions = get_the_terms($policy_id, 'policy_region');
            if ($regions && !is_wp_error($regions)) {
                $region_name = $regions[0]->name;
            }
        }

        // Calcul du premium
        $premium = '';
        if ($policy_id && $days > 0) {
            $premiums = get_post_meta($policy_id, '_policy_day_premiums', true);
            if (is_array($premiums)) {
                foreach ($premiums as $row) {
                    if ($days >= intval($row['from']) && $days <= intval($row['to'])) {
                        $premium = $row['premium'];
                        break;
                    }
                }
            }
            // Injection des données de premium en JS pour mise à jour dynamique
            echo '<script>
                window.maljaniPremiums = ' . json_encode($premiums) . ';
                window.policyId = ' . json_encode($policy_id) . ';
                window.departureDateValue = ' . json_encode($departure) . ';
                window.returnDateValue = ' . json_encode($return) . ';
                window.daysValue = ' . json_encode($days) . ';
                window.premiumValue = ' . json_encode($premium) . ';
            </script>';
        }

        // Préremplissage pour client connecté
        $client_data = [
            'full_name' => $is_client ? $current_user->display_name : '',
            'dob' => $is_client ? get_user_meta($current_user->ID, 'insured_dob', true) : '',
            'passport' => $is_client ? get_user_meta($current_user->ID, 'passport_number', true) : '',
            'national_id' => $is_client ? get_user_meta($current_user->ID, 'national_id', true) : '',
            'phone' => $is_client ? get_user_meta($current_user->ID, 'phone', true) : '',
            'email' => $is_client ? $current_user->user_email : '',
            'address' => $is_client ? get_user_meta($current_user->ID, 'address', true) : '',
            'country' => $is_client ? get_user_meta($current_user->ID, 'country', true) : '',
        ];

        // Récupère les conditions générales
        $terms = $policy_id ? get_post_meta($policy_id, '_policy_terms', true) : '';

        ob_start();
        ?>
        <div class="maljani-sales-form-container">
            <h2>Get Covered<?php echo $policy_title ? ': ' . esc_html($policy_title) : ''; ?></h2>

            <!-- Étape 1 : Saisie des dates -->
            <?php if (!$departure || !$return || $days <= 0): ?>
                <form method="get" class="maljani-sales-form" autocomplete="off">
                    <?php if ($policy_id): ?>
                        <input type="hidden" name="policy_id" value="<?php echo esc_attr($policy_id); ?>">
                    <?php endif; ?>
                    <?php if ($region_id): ?>
                        <input type="hidden" name="region_id" value="<?php echo esc_attr($region_id); ?>">
                    <?php endif; ?>
                    <!-- Préservation des autres paramètres GET de l'URL -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if ($key !== 'departure' && $key !== 'return' && $key !== 'policy_id' && $key !== 'region_id'): ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="maljani-form-group">
                        <label>Departure date</label>
                        <input type="date" name="departure" value="<?php echo esc_attr($departure); ?>" required>
                    </div>
                    <div class="maljani-form-group">
                        <label>Return date</label>
                        <input type="date" name="return" value="<?php echo esc_attr($return); ?>" required>
                    </div>
                    <button type="submit" class="maljani-sales-btn">
                        <?php echo ($policy_id || $region_id) ? 'Continue' : 'Show available regions'; ?>
                    </button>
                </form>
            <?php endif; ?>

            <!-- Étape 2 : Choix de la région -->
            <?php if ($departure && $return && $days > 0 && !$region_id && !$policy_id): ?>
                <form method="get" class="maljani-sales-form" autocomplete="off">
                    <input type="hidden" name="departure" value="<?php echo esc_attr($departure); ?>">
                    <input type="hidden" name="return" value="<?php echo esc_attr($return); ?>">
                    
                    <!-- Préservation des autres paramètres GET de l'URL -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if ($key !== 'departure' && $key !== 'return' && $key !== 'region_id' && $key !== 'policy_id'): ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div class="maljani-form-group">
                        <label for="region_id">Select your destination region</label>
                        <select name="region_id" id="region_id" required>
                            <option value="">-- Choose a region --</option>
                            <?php
                            $regions = get_terms([
                                'taxonomy' => 'policy_region',
                                'hide_empty' => true,
                                'orderby' => 'name',
                                'order' => 'ASC'
                            ]);
                            foreach ($regions as $region) {
                                echo '<option value="' . esc_attr($region->term_id) . '"';
                                if (isset($_GET['region_id']) && $_GET['region_id'] == $region->term_id) echo ' selected';
                                echo '>' . esc_html($region->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="maljani-sales-btn" id="select-region-btn">Continue to Policies</button>
                    
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const regionSelect = document.getElementById('region_id');
                        const regionForm = regionSelect.closest('form');
                        
                        if (regionSelect) {
                            // Améliorer visuellement le select existant
                            regionSelect.style.padding = '12px 15px';
                            regionSelect.style.border = '2px solid #ddd';
                            regionSelect.style.borderRadius = '8px';
                            regionSelect.style.fontSize = '16px';
                            regionSelect.style.background = 'white';
                            regionSelect.style.width = '100%';
                        }
                        
                        regionSelect.addEventListener('change', function() {
                            if (this.value) {
                                // Soumettre automatiquement le formulaire lorsqu'une région est sélectionnée
                                setTimeout(() => {
                                    document.getElementById('select-region-btn').click();
                                }, 100);
                            }
                        });
                        
                        regionForm.addEventListener('submit', function(e) {
                            const regionId = regionSelect.value;
                            if (!regionId) {
                                e.preventDefault();
                                alert('Veuillez sélectionner une région.');
                                return false;
                            }
                        });
                    });
                    </script>
                </form>
            <?php endif; ?>

            <!-- Étape 3 : Choix de la policy (filtrée par région) -->
            <?php if ($departure && $return && $days > 0 && $region_id && !$policy_id): ?>
                <form method="get" class="maljani-sales-form" autocomplete="off">
                    <input type="hidden" name="departure" value="<?php echo esc_attr($departure); ?>">
                    <input type="hidden" name="return" value="<?php echo esc_attr($return); ?>">
                    <input type="hidden" name="region_id" value="<?php echo esc_attr($region_id); ?>">
                    
                    <!-- Préservation des autres paramètres GET de l'URL -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if ($key !== 'departure' && $key !== 'return' && $key !== 'region_id' && $key !== 'policy_id'): ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div class="maljani-sales-summary" style="margin-bottom: 20px;">
                        <p><strong>Selected Region:</strong> <?php echo esc_html($region_title); ?></p>
                        <p><strong>Travel Duration:</strong> <?php echo esc_html($days); ?> days</p>
                    </div>
                    
                    <div class="maljani-form-group">
                        <label for="policy_id">Select a policy for <?php echo esc_html($region_title); ?></label>
                        <select name="policy_id" id="policy_id" required>
                            <option value="">-- Choose a policy --</option>
                            <?php
                            // Récupérer seulement les polices de la région sélectionnée
                            $policies = get_posts([
                                'post_type' => 'policy',
                                'posts_per_page' => -1,
                                'post_status' => 'publish',
                                'orderby' => 'title',
                                'order' => 'ASC',
                                'tax_query' => [
                                    [
                                        'taxonomy' => 'policy_region',
                                        'field'    => 'term_id',
                                        'terms'    => $region_id,
                                    ],
                                ],
                            ]);
                            foreach ($policies as $p) {
                                $premiums = get_post_meta($p->ID, '_policy_day_premiums', true);
                                
                                // Récupération des informations sur l'assureur
                                $insurer_id = get_post_meta($p->ID, '_policy_insurer', true);
                                $insurer_name = '';
                                
                                if ($insurer_id) {
                                    $insurer = get_post($insurer_id);
                                    $insurer_name = $insurer ? $insurer->post_title : '';
                                }
                                
                                $policy_premium = '';
                                if (is_array($premiums)) {
                                    foreach ($premiums as $row) {
                                        if ($days >= intval($row['from']) && $days <= intval($row['to'])) {
                                            $policy_premium = $row['premium'];
                                            break;
                                        }
                                    }
                                }
                                
                                echo '<option value="' . esc_attr($p->ID) . '"';
                                if (isset($_GET['policy_id']) && $_GET['policy_id'] == $p->ID) echo ' selected';
                                echo '>' . esc_html($p->post_title);
                                if ($insurer_name) echo ' | ' . esc_html($insurer_name);
                                if ($policy_premium) echo ' | Premium: ' . esc_html($policy_premium);
                                echo '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="maljani-sales-btn" id="select-policy-btn">Continue</button>
                    
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const policySelect = document.getElementById('policy_id');
                        const policyForm = policySelect.closest('form');
                        
                        if (policySelect) {
                            // Améliorer visuellement le select existant au lieu de le remplacer
                            policySelect.style.padding = '12px 15px';
                            policySelect.style.border = '2px solid #ddd';
                            policySelect.style.borderRadius = '8px';
                            policySelect.style.fontSize = '16px';
                            policySelect.style.background = 'white';
                            policySelect.style.width = '100%';
                        }
                        
                        policySelect.addEventListener('change', function() {
                            if (this.value) {
                                // Soumettre automatiquement le formulaire lorsqu'une police est sélectionnée
                                setTimeout(() => {
                                    document.getElementById('select-policy-btn').click();
                                }, 100);
                            }
                        });
                        
                        policyForm.addEventListener('submit', function(e) {
                            const policyId = policySelect.value;
                            if (!policyId) {
                                e.preventDefault();
                                alert('Veuillez sélectionner une police.');
                                return false;
                            }
                        });
                    });
                    </script>
                </form>
            <?php endif; ?>

            <!-- Étape 4 : Formulaire complet -->
            <?php if ($policy_id && $departure && $return && $days > 0): ?>
                <form method="post" class="maljani-sales-form" autocomplete="off">
                    <input type="hidden" name="policy_id" value="<?php echo esc_attr($policy_id); ?>">
                    <input type="hidden" name="region" value="<?php echo esc_attr($region_name); ?>">
                    <input type="hidden" name="premium" value="<?php echo esc_attr($premium); ?>">
                    <input type="hidden" name="days" value="<?php echo esc_attr($days); ?>">
                    <input type="hidden" name="departure" value="<?php echo esc_attr($departure); ?>">
                    <input type="hidden" name="return" value="<?php echo esc_attr($return); ?>">

                    <div class="maljani-sales-summary">
                        <p><strong>Policy:</strong> <?php echo esc_html($policy_title); ?></p>
                        <p><strong>Region:</strong> <?php echo esc_html($region_name ?: $region_title); ?></p>
                        <p><strong>Premium (Amount to pay):</strong> <span id="premium-amount"><?php echo esc_html($premium); ?></span></p>
                        <p><strong>Days covered:</strong> <span id="days-covered"><?php echo esc_html($days); ?></span></p>
                    </div>

                    <?php if ($is_agent || $is_client): ?>
                        <div class="maljani-form-group">
                            <label>
                                <input type="checkbox" name="buying_for_someone_else" id="buying_for_someone_else" value="1">
                                I am buying for someone else
                            </label>
                        </div>
                    <?php endif; ?>

                    <div id="insured-fields">
                        <input type="text" name="insured_names" placeholder="Full name (as it appears on passport)" value="<?php echo esc_attr($client_data['full_name']); ?>" required>
                        <input type="date" name="insured_dob" placeholder="Date of birth" value="<?php echo esc_attr($client_data['dob']); ?>" required>
                        <input type="text" name="passport_number" placeholder="Passport number" value="<?php echo esc_attr($client_data['passport']); ?>" required>
                        <input type="text" name="national_id" placeholder="National ID or PIN number" value="<?php echo esc_attr($client_data['national_id']); ?>" required>
                        <input type="text" name="insured_phone" placeholder="Phone number" value="<?php echo esc_attr($client_data['phone']); ?>" required>
                        <input type="email" name="insured_email" placeholder="Email address" value="<?php echo esc_attr($client_data['email']); ?>" required>
                        <input type="text" name="insured_address" placeholder="Residential address" value="<?php echo esc_attr($client_data['address']); ?>" required>
                        <input type="text" name="country_of_origin" placeholder="Country of origin" value="<?php echo esc_attr($client_data['country']); ?>" required>
                    </div>

                    <div class="maljani-form-group">
                        <label>Amount to pay</label>
                        <input type="text" name="amount_paid" value="<?php echo esc_attr($premium); ?>" readonly>
                    </div>
                    <div class="maljani-form-group">
                        <label>Payment reference</label>
                        <input type="text" name="payment_reference" placeholder="Enter payment reference" required>
                    </div>
                    <div class="maljani-form-group" style="max-height:120px;overflow:auto;background:#f7f7f7;padding:10px;border-radius:6px;margin-bottom:8px;">
                        <?php echo wpautop( esc_html($terms) ); ?>
                    </div>
                    <div class="maljani-form-group">
                        <label>
                            <input type="checkbox" name="accept_terms" required>
                            I accept the terms and conditions
                        </label>
                    </div>
                    <button type="submit" name="maljani_submit_sales" class="maljani-sales-btn">
                        <span class="dashicons dashicons-yes"></span>
                        Confirm & Get Covered
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validation des formulaires GET
            const getForms = document.querySelectorAll('form[method="get"]');
            getForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const departure = form.querySelector('input[name="departure"]')?.value;
                    const return_date = form.querySelector('input[name="return"]')?.value;
                    
                    if (!departure || !return_date) {
                        e.preventDefault();
                        alert('Veuillez sélectionner les dates de départ et de retour.');
                        return false;
                    }
                    
                    // Valider que return > departure
                    if (new Date(departure) >= new Date(return_date)) {
                        e.preventDefault();
                        alert('La date de retour doit être postérieure à la date de départ.');
                        return false;
                    }
                });
            });

            // Affichage des champs selon "buying for someone else"
            const checkbox = document.getElementById('buying_for_someone_else');
            if(checkbox) {
                checkbox.addEventListener('change', function() {
                    const insuredFields = document.getElementById('insured-fields');
                    if(this.checked) {
                        insuredFields.querySelectorAll('input').forEach(input => input.value = '');
                    } else {
                        <?php if ($is_client): ?>
                        insuredFields.querySelector('input[name="insured_names"]').value = "<?php echo esc_js($client_data['full_name']); ?>";
                        insuredFields.querySelector('input[name="insured_dob"]').value = "<?php echo esc_js($client_data['dob']); ?>";
                        insuredFields.querySelector('input[name="passport_number"]').value = "<?php echo esc_js($client_data['passport']); ?>";
                        insuredFields.querySelector('input[name="national_id"]').value = "<?php echo esc_js($client_data['national_id']); ?>";
                        insuredFields.querySelector('input[name="insured_phone"]').value = "<?php echo esc_js($client_data['phone']); ?>";
                        insuredFields.querySelector('input[name="insured_email"]').value = "<?php echo esc_js($client_data['email']); ?>";
                        insuredFields.querySelector('input[name="insured_address"]').value = "<?php echo esc_js($client_data['address']); ?>";
                        insuredFields.querySelector('input[name="country_of_origin"]').value = "<?php echo esc_js($client_data['country']); ?>";
                        <?php endif; ?>
                    }
                });
            }

            // Calcul dynamique du premium and des jours
            function updateDaysAndPremium() {
                const dep = document.getElementById('departure');
                const ret = document.getElementById('return');
                const daysField = document.getElementById('days_covered');
                const amountField = document.querySelector('input[name="amount_paid"]');
                const premiumSpan = document.getElementById('premium-amount');
                const daysSpan = document.getElementById('days-covered');
                if(dep && ret && daysField && amountField) {
                    const d1 = new Date(dep.value);
                    const d2 = new Date(ret.value);
                    const diff = Math.ceil((d2 - d1) / (1000*60*60*24));
                    daysField.value = (dep.value && ret.value && diff > 0) ? diff : '';
                    if(daysSpan) daysSpan.textContent = daysField.value;
                    // Calcul premium
                    let premium = '';
                    if(window.maljaniPremiums && diff > 0) {
                        for(const row of window.maljaniPremiums) {
                            if(diff >= parseInt(row.from) && diff <= parseInt(row.to)) {
                                premium = row.premium;
                                break;
                            }
                        }
                    }
                    amountField.value = premium;
                    if(premiumSpan) premiumSpan.textContent = premium;
                }
            }
            if(document.getElementById('departure')) document.getElementById('departure').addEventListener('change', updateDaysAndPremium);
            if(document.getElementById('return')) document.getElementById('return').addEventListener('change', updateDaysAndPremium);
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_form_submission() {
        if (isset($_POST['maljani_submit_sales'])) {
            global $wpdb;
            $table = $wpdb->prefix . 'policy_sale';

            $policy_id = intval($_POST['policy_id']);
            // Récupère le nom de la région
            $region_name = '';
            $regions = get_the_terms($policy_id, 'policy_region');
            if ($regions && !is_wp_error($regions)) {
                $region_name = $regions[0]->name;
            }

            // Calcul du nombre de jours
            $departure = isset($_POST['departure']) ? $_POST['departure'] : '';
            $return = isset($_POST['return']) ? $_POST['return'] : '';
            $days = 0;
            if ($departure && $return) {
                $d1 = new DateTime($departure);
                $d2 = new DateTime($return);
                $days = $d1 < $d2 ? $d1->diff($d2)->days : 0;
            }

            // Calcul du premium
            $premiums = get_post_meta($policy_id, '_policy_day_premiums', true);
            $premium = 0;
            if (is_array($premiums)) {
                foreach ($premiums as $row) {
                    if ($days >= intval($row['from']) && $days <= intval($row['to'])) {
                        $premium = floatval($row['premium']);
                        break;
                    }
                }
            }

            $current_user = wp_get_current_user();
            $user_role = ($current_user->exists()) ? $current_user->roles[0] : '';
            $is_agent = ($user_role === 'insurer');

            // Générer un numéro de police unique
            $policy_number = 'POL-' . date('Ymd') . '-' . mt_rand(1000, 9999);
            
            $result = $wpdb->insert($table, [
                'policy_id'         => $policy_id,
                'policy_number'     => $policy_number, // Généré automatiquement
                'region'            => $region_name,
                'premium'           => $premium,
                'days'              => $days,
                'departure'         => sanitize_text_field($departure),
                'return'            => sanitize_text_field($return),
                'insured_names'     => sanitize_text_field($_POST['insured_names']),
                'insured_dob'       => sanitize_text_field($_POST['insured_dob']),
                'passport_number'   => sanitize_text_field($_POST['passport_number']),
                'national_id'       => sanitize_text_field($_POST['national_id']),
                'insured_phone'     => sanitize_text_field($_POST['insured_phone']),
                'insured_email'     => sanitize_email($_POST['insured_email']),
                'insured_address'   => sanitize_text_field($_POST['insured_address']),
                'country_of_origin' => sanitize_text_field($_POST['country_of_origin']),
                'agent_id'          => get_current_user_id(),
                'agent_name'        => $is_agent ? $current_user->display_name : '',
                'amount_paid'       => $premium,
                'payment_reference' => sanitize_text_field($_POST['payment_reference'] ?? ''),
                'payment_status'    => 'pending',
                'policy_status'     => 'unconfirmed',
                'terms'             => isset($_POST['accept_terms']) ? 1 : 0, // Acceptation des conditions
            ]);
            if ($result) {
                // Succès : redirige avec un paramètre de succès
                wp_redirect(add_query_arg('sale_success', '1', wp_get_referer() ?: home_url()));
                exit;
            } else {
                // Échec : redirige avec un paramètre d’erreur
                wp_redirect(add_query_arg('sale_error', '1', wp_get_referer() ?: home_url()));
                exit;
            }
        }
    }
}
new Maljani_Sales_Page();