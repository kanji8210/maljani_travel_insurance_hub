<?php
//includes\class-maljani-sales-page.php

/**
 * Maljani Sales Page - Système de Formulaire de Vente
 * 
 * Classe principale gérant le processus de vente d'assurance voyage en 4+ étapes :
 * 1. Saisie des dates de voyage
 * 2. Sélection de la région de destination  
 * 3. Choix de la police d'assurance (filtrée par région)
 * 4. Formulaire complet de souscription
 * 
 * Assets intégrés : Plus de dépendance aux fichiers CSS/JS externes
 * Shortcode : [maljani_policy_sale]
 * 
 * @since 1.0.0
 * @version 2.0.0 - Migration vers assets intégrés (22/07/2025)
 */

class Maljani_Sales_Page
{
    public function __construct()
    {
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

    /**
     * Validate travel dates
     *
     * @param string $departure Departure date in Y-m-d format
     * @param string $return Return date in Y-m-d format
     * @return bool|string True if valid, error message if invalid
     */
    private function validate_dates($departure, $return)
    {
        // Check format
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $departure) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $return)
        ) {
            return 'Invalid date format. Please use the date picker.';
        }

        // Check that dates are valid
        $dep_time = strtotime($departure);
        $ret_time = strtotime($return);

        if ($dep_time === false || $ret_time === false) {
            return 'Invalid dates provided.';
        }

        // Check that departure is before return
        if ($dep_time >= $ret_time) {
            return 'Return date must be after departure date.';
        }

        // Check that departure is not in the past (allow today)
        if ($dep_time < strtotime('today')) {
            return 'Departure date cannot be in the past.';
        }

        // Check that the trip is not too long (e.g., max 365 days)
        $days = ceil(($ret_time - $dep_time) / (60 * 60 * 24));
        if ($days > 365) {
            return 'Trip duration cannot exceed 365 days.';
        }

        return true;
    }

    // Nouvelle fonction pour gérer les redirections de sélection de police
    public function handle_policy_redirection()
    {
        // Ne pas rediriger automatiquement - laisser le formulaire fonctionner naturellement
        // Cette fonction est maintenant désactivée pour éviter les boucles de redirection
        return;
    }

    // Injecte le formulaire de vente dans le contenu si maljani_sales=1 est défini
    public function maybe_inject_sales_form($content)
    {
        // Ne pas injecter si nous sommes sur une page qui contient déjà le shortcode
        if (has_shortcode($content, 'maljani_policy_sale') || has_shortcode($content, 'maljani_sales_form')) {
            return $content;
        }

        // Ne pas injecter si nous sommes sur la page de vente configurée
        $sale_page_id = get_option('maljani_policy_sale_page');
        if ($sale_page_id && is_page($sale_page_id)) {
            return $content;
        }

        // Afficher le formulaire seulement si maljani_sales=1 OU si nous avons des paramètres de police/région
        if (
            (isset($_GET['maljani_sales']) && $_GET['maljani_sales']) ||
            (isset($_GET['policy_id']) || isset($_GET['region_id']) || (isset($_GET['departure']) && isset($_GET['return'])))
        ) {
            $form = $this->render_sales_form();
            return $content . '<div class="maljani-injected-sales-form">' . $form . '</div>';
        }
        return $content;
    }

    public function check_sales_page_configured()
    {
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

    public function check_and_create_table()
    {
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

    public function enqueue_style()
    {
        // Charge les styles/scripts seulement si le shortcode est utilisé OU si c'est la page de vente configurée OU si on a des paramètres de police
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
            // Ou si nous avons des paramètres de vente ET que nous ne sommes PAS sur une page avec shortcode
            elseif ($has_sales_params && !has_shortcode(get_post()->post_content ?? '', 'maljani_policy_sale') && !has_shortcode(get_post()->post_content ?? '', 'maljani_sales_form')) {
                $should_enqueue = true;
            }
        }

        if ($should_enqueue) {
            // Charge le fichier CSS du formulaire de vente
            wp_enqueue_style(
                'maljani-sales-form',
                plugin_dir_url(__FILE__) . 'css/sales_form.css',
                [],
                '1.0.0'
            );

            // Ajoute les scripts JavaScript pour l'interactivité
            wp_add_inline_script('jquery', $this->get_inline_sales_scripts());
        }
    }

    private function get_inline_sales_scripts()
    {
        return '
        // Scripts intégrés pour le formulaire de vente Maljani
        console.log("Maljani Sales Form scripts loaded");
        
        // Validation en temps réel des dates
        jQuery(document).on("change", "input[name=departure], input[name=return]", function() {
            const departure = jQuery("input[name=departure]").val();
            const return_date = jQuery("input[name=return]").val();
            
            if (departure && return_date) {
                const dep = new Date(departure);
                const ret = new Date(return_date);
                
                if (dep >= ret) {
                    jQuery(this).css("border-color", "red");
                    alert("La date de retour doit être postérieure à la date de départ.");
                } else {
                    jQuery("input[name=departure], input[name=return]").css("border-color", "");
                    
                    // Calcul automatique des jours
                    const diffTime = Math.abs(ret - dep);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    jQuery("#days-covered").text(diffDays);
                    
                    // Mise à jour du premium si disponible
                    if (window.maljaniPremiums && diffDays > 0) {
                        let newPremium = "";
                        window.maljaniPremiums.forEach(function(row) {
                            if (diffDays >= parseInt(row.from) && diffDays <= parseInt(row.to)) {
                                newPremium = row.premium;
                            }
                        });
                        if (newPremium) {
                            jQuery("#premium-amount").text(newPremium);
                            jQuery("input[name=amount_paid]").val(newPremium);
                            if (window.maljaniCurrency) {
                                jQuery("#premium-currency").text(window.maljaniCurrency);
                            }
                        }
                        }
                    }
                }
            }
        });
        
        // Confirmation avant soumission finale
        jQuery(document).on("submit", "form[method=post]", function(e) {
            const acceptTerms = jQuery("input[name=accept_terms]").is(":checked");
            const paymentRef = jQuery("input[name=payment_reference]").val();
            
            if (!acceptTerms) {
                e.preventDefault();
                alert("Veuillez accepter les conditions générales.");
                return false;
            }
            
            if (!paymentRef || paymentRef.trim() === "") {
                e.preventDefault();
                alert("Veuillez entrer une référence de paiement.");
                return false;
            }
            
            return confirm("Confirmer la soumission de cette demande d\'assurance ?");
        });
        ';
    }

    public function render_sales_form()
    {
        // Protection contre les rendus multiples
        static $form_rendered = false;
        if ($form_rendered) {
            return '';
        }
        $form_rendered = true;

        // Start output buffering
        ob_start();

        // Vérification de configuration pour page d'accueil
        if (is_front_page() && isset($_GET['maljani_sales']) && $_GET['maljani_sales']) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Attention:</strong> Vous utilisez la page d\'accueil comme page de vente, ce qui n\'est pas recommandé. Veuillez configurer une page dédiée avec le shortcode [maljani_policy_sale] dans les paramètres du plugin.';
            echo '</p></div>';
        }

        // Messages de notification
        if (isset($_GET['sale_success'])) {
            if (isset($_GET['new_account'])) {
                echo '<div class="notice notice-success"><p>';
                echo 'Thank you! We have received your purchase. We will review and notify you ASAP. A new account has been created for you - check your email for login details and further instructions.';
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>';
                echo 'Thank you! We have received your purchase. We will review and notify you ASAP. Check your email for further instructions.';
                echo '</p></div>';
            }
        }
        if (isset($_GET['sale_error'])) {
            if ($_GET['sale_error'] === 'account_creation_failed') {
                echo '<div class="notice notice-error"><p>';
                echo 'Purchase saved but failed to create account. Please contact support.';
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>';
                echo 'An error occurred. Please try again.';
                echo '</p></div>';
            }
        }
        if (isset($_GET['message']) && $_GET['message'] === 'account_exists') {
            echo '<div class="notice notice-info"><p>';
            echo 'Thank you! We have received your purchase. We will review and notify you ASAP. An account with this email already exists - please log in to view your policies and check your email for further instructions.';
            echo '</p></div>';
        }

        // Données utilisateur et rôles
        $current_user = wp_get_current_user();
        $user_role = ($current_user->exists()) ? $current_user->roles[0] : '';
        $is_agent = ($user_role === 'agent');
        $is_insured = ($user_role === 'insured');

        // Récupération et validation des paramètres GET
        $policy_id = isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0;
        $region_id = isset($_GET['region_id']) ? intval($_GET['region_id']) : 0;
        $departure = isset($_GET['departure']) ? sanitize_text_field($_GET['departure']) : '';
        $return = isset($_GET['return']) ? sanitize_text_field($_GET['return']) : '';
        $buying_for_self = isset($_GET['buying_for_self']) ? sanitize_text_field($_GET['buying_for_self']) : '';
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
        $currency = '';
        if ($policy_id && $days > 0) {
            $currency = get_post_meta($policy_id, '_policy_currency', true);
            if (empty($currency))
                $currency = 'KSH';

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
                window.maljaniCurrency = ' . json_encode($currency) . ';
                window.policyId = ' . json_encode($policy_id) . ';
                window.departureDateValue = ' . json_encode($departure) . ';
                window.returnDateValue = ' . json_encode($return) . ';
                window.daysValue = ' . json_encode($days) . ';
                window.premiumValue = ' . json_encode($premium) . ';
            </script>';
        }

        // Préremplissage pour utilisateurs connectés qui achètent pour eux-mêmes
        // Pour les "insured", on prérempli automatiquement si pas de buying_for_self défini ou si buying_for_self='yes'
        // Pour les "agents", on prérempli seulement si buying_for_self='yes'
        $should_prefill = false;
        if ($is_insured) {
            // Pour insured : préremplir par défaut ou si buying_for_self='yes'
            $should_prefill = (!$buying_for_self || $buying_for_self === 'yes');
        } elseif ($is_agent) {
            // Pour agents : préremplir seulement si buying_for_self='yes'
            $should_prefill = ($buying_for_self === 'yes');
        }

        $client_data = [
            'full_name' => $should_prefill ? $current_user->display_name : '',
            'dob' => $should_prefill ? get_user_meta($current_user->ID, 'dob', true) : '',
            'passport' => $should_prefill ? get_user_meta($current_user->ID, 'passport_number', true) : '',
            'national_id' => $should_prefill ? get_user_meta($current_user->ID, 'national_id', true) : '',
            'phone' => $should_prefill ? get_user_meta($current_user->ID, 'phone', true) : '',
            'email' => $should_prefill ? $current_user->user_email : '',
            'address' => $should_prefill ? get_user_meta($current_user->ID, 'address', true) : '',
            'country' => $should_prefill ? get_user_meta($current_user->ID, 'country', true) : '',
        ];

        // Récupère les conditions générales
        $terms = $policy_id ? get_post_meta($policy_id, '_policy_terms', true) : '';

        // Récupère les détails de paiement de la police
        $payment_details = $policy_id ? get_post_meta($policy_id, '_policy_payment_details', true) : '';

        ob_start();
        ?>
        <div class="maljani-sales-form-container">

            <!-- Statut de connexion utilisateur -->
            <?php if ($current_user->exists()): ?>
                <div class="user-status-banner">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <?php if ($is_agent): ?>
                            <span style="font-size: 20px;">🏢</span>
                            <span><strong>You are logged in as:</strong> Agent -
                                <?php echo esc_html($current_user->display_name); ?></span>
                        <?php elseif ($is_insured): ?>
                            <span style="font-size: 20px;">👤</span>
                            <span><strong>You are logged in as:</strong> Insured Member -
                                <?php echo esc_html($current_user->display_name); ?></span>
                        <?php else: ?>
                            <span style="font-size: 20px;">👤</span>
                            <span><strong>You are logged in as:</strong> <?php echo esc_html($current_user->display_name); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($should_prefill): ?>
                        <div class="prefill-note">
                            Your profile information will be used to pre-fill the form
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php
            // Logique de calcul de l'étape actuelle
            $current_step = 1;
            if ($departure && $return && $days > 0) {
                if ($region_id) {
                    if ($policy_id) {
                        $current_step = 4;
                    } else {
                        $current_step = 3;
                    }
                } else {
                    $current_step = 2;
                }
            }
            ?>

            <!-- Progress Stepper -->
            <div class="maljani-stepper">
                <div class="maljani-stepper-line"></div>
                <div class="maljani-step <?php echo $current_step >= 1 ? ($current_step > 1 ? 'completed' : 'active') : ''; ?>">
                    <div class="maljani-step-circle">1</div>
                    <div class="maljani-step-label">Dates</div>
                </div>
                <div class="maljani-step <?php echo $current_step >= 2 ? ($current_step > 2 ? 'completed' : 'active') : ''; ?>">
                    <div class="maljani-step-circle">2</div>
                    <div class="maljani-step-label">Region</div>
                </div>
                <div class="maljani-step <?php echo $current_step >= 3 ? ($current_step > 3 ? 'completed' : 'active') : ''; ?>">
                    <div class="maljani-step-circle">3</div>
                    <div class="maljani-step-label">Policy</div>
                </div>
                <div class="maljani-step <?php echo $current_step >= 4 ? 'active' : ''; ?>">
                    <div class="maljani-step-circle">4</div>
                    <div class="maljani-step-label">Confirm</div>
                </div>
            </div>

            <div class="maljani-sales-form">
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
                        <?php if ($buying_for_self): ?>
                            <input type="hidden" name="buying_for_self" value="<?php echo esc_attr($buying_for_self); ?>">
                        <?php endif; ?>
                        <!-- Préservation des autres paramètres GET de l'URL -->
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'departure' && $key !== 'return' && $key !== 'policy_id' && $key !== 'region_id' && $key !== 'buying_for_self'): ?>
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
                            <span><?php echo ($policy_id || $region_id) ? 'Continue' : 'Show available regions'; ?></span>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Étape 1.5 : Pour les utilisateurs connectés - demander s'ils achètent pour eux-mêmes -->
                <!-- Pour les insured, on présume qu'ils achètent pour eux-mêmes sauf s'ils disent explicitement non -->
                <?php if ($is_agent && $departure && $return && $days > 0 && !$buying_for_self && !$region_id && !$policy_id): ?>
                    <!-- Agents doivent toujours choisir -->
                    <form method="get" class="maljani-sales-form" autocomplete="off">
                        <input type="hidden" name="departure" value="<?php echo esc_attr($departure); ?>">
                        <input type="hidden" name="return" value="<?php echo esc_attr($return); ?>">

                        <!-- Préservation des autres paramètres GET de l'URL -->
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'departure' && $key !== 'return' && $key !== 'buying_for_self'): ?>
                                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="maljani-form-group">
                            <h3>Who are you buying this policy for?</h3>
                            <div style="margin: 15px 0;">
                                <label style="display: block; margin-bottom: 10px; cursor: pointer;">
                                    <input type="radio" name="buying_for_self" value="yes" style="margin-right: 8px;" required>
                                    For myself (your personal details will be pre-filled)
                                </label>
                                <label style="display: block; cursor: pointer;">
                                    <input type="radio" name="buying_for_self" value="no" style="margin-right: 8px;" required>
                                    For someone else (enter their details manually)
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="maljani-sales-btn">Continue</button>
                    </form>
                <?php elseif ($is_insured && $departure && $return && $days > 0 && !$buying_for_self && !$region_id && !$policy_id): ?>
                    <!-- Insured peuvent choisir mais avec option par défaut pour eux-mêmes -->
                    <form method="get" class="maljani-sales-form" autocomplete="off">
                        <input type="hidden" name="departure" value="<?php echo esc_attr($departure); ?>">
                        <input type="hidden" name="return" value="<?php echo esc_attr($return); ?>">

                        <!-- Préservation des autres paramètres GET de l'URL -->
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'departure' && $key !== 'return' && $key !== 'buying_for_self'): ?>
                                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="maljani-form-group">
                            <h3>Who are you buying this policy for?</h3>
                            <div style="margin: 15px 0;">
                                <label style="display: block; margin-bottom: 10px; cursor: pointer;">
                                    <input type="radio" name="buying_for_self" value="yes" style="margin-right: 8px;" checked
                                        required>
                                    For myself (your personal details will be pre-filled)
                                </label>
                                <label style="display: block; cursor: pointer;">
                                    <input type="radio" name="buying_for_self" value="no" style="margin-right: 8px;" required>
                                    For someone else (enter their details manually)
                                </label>
                            </div>
                            <p style="background:#e7f3ff;padding:8px;border-radius:4px;color:#0073aa;font-size:14px;">
                                <strong>Note:</strong> As an insured member, we recommend selecting "For myself" to use your
                                existing profile information.
                            </p>
                            <div style="text-align: center; margin-top: 15px;">
                                <button type="submit" class="maljani-sales-btn">Continue</button>
                                <div style="margin-top: 10px;">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['buying_for_self' => 'yes'])); ?>"
                                        style="background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-size: 14px;">
                                        Quick: Continue for myself
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Étape 2 : Choix de la région -->
                <?php
                // Conditions d'affichage :
                // - Visiteurs non connectés
                // - Agents qui ont choisi (buying_for_self défini)
                // - Insured qui ont choisi OU insured sans choix (présumé pour eux-mêmes)
                $show_region_step = $departure && $return && $days > 0 && !$region_id && !$policy_id;
                $show_region_step = $show_region_step && (
                    (!$is_agent && !$is_insured) || // visiteurs
                    ($is_agent && $buying_for_self) || // agents avec choix
                    ($is_insured && ($buying_for_self || !isset($_GET['buying_for_self']))) // insured avec choix ou sans choix
                );
                ?>
                <?php if ($show_region_step): ?>
                    <form method="get" class="maljani-sales-form" autocomplete="off">
                        <input type="hidden" name="departure" value="<?php echo esc_attr($departure); ?>">
                        <input type="hidden" name="return" value="<?php echo esc_attr($return); ?>">
                        <?php if ($buying_for_self): ?>
                            <input type="hidden" name="buying_for_self" value="<?php echo esc_attr($buying_for_self); ?>">
                        <?php endif; ?>

                        <!-- Préservation des autres paramètres GET de l'URL -->
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'departure' && $key !== 'return' && $key !== 'region_id' && $key !== 'policy_id' && $key !== 'buying_for_self'): ?>
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
                                    if (isset($_GET['region_id']) && $_GET['region_id'] == $region->term_id)
                                        echo ' selected';
                                    echo '>' . esc_html($region->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="maljani-sales-btn" id="select-region-btn">Continue to Policies</button>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
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

                                regionSelect.addEventListener('change', function () {
                                    if (this.value) {
                                        // Soumettre automatiquement le formulaire lorsqu'une région est sélectionnée
                                        setTimeout(() => {
                                            document.getElementById('select-region-btn').click();
                                        }, 100);
                                    }
                                });

                                regionForm.addEventListener('submit', function (e) {
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
                <?php
                // Même logique que l'étape 2
                $show_policy_step = $departure && $return && $days > 0 && $region_id && !$policy_id;
                $show_policy_step = $show_policy_step && (
                    (!$is_agent && !$is_insured) || // visiteurs
                    ($is_agent && $buying_for_self) || // agents avec choix
                    ($is_insured && ($buying_for_self || !isset($_GET['buying_for_self']))) // insured avec choix ou sans choix
                );
                ?>
                <?php if ($show_policy_step): ?>
                    <form method="get" class="maljani-sales-form" autocomplete="off">
                        <input type="hidden" name="departure" value="<?php echo esc_attr($departure); ?>">
                        <input type="hidden" name="return" value="<?php echo esc_attr($return); ?>">
                        <input type="hidden" name="region_id" value="<?php echo esc_attr($region_id); ?>">
                        <?php if ($buying_for_self): ?>
                            <input type="hidden" name="buying_for_self" value="<?php echo esc_attr($buying_for_self); ?>">
                        <?php endif; ?>

                        <!-- Préservation des autres paramètres GET de l'URL -->
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'departure' && $key !== 'return' && $key !== 'region_id' && $key !== 'policy_id' && $key !== 'buying_for_self'): ?>
                                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="maljani-sales-summary">
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
                                            'field' => 'term_id',
                                            'terms' => $region_id,
                                        ],
                                    ]
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
                                    if (isset($_GET['policy_id']) && $_GET['policy_id'] == $p->ID)
                                        echo ' selected';
                                    echo '>' . esc_html($p->post_title);
                                    if ($insurer_name)
                                        echo ' | ' . esc_html($insurer_name);
                                    if ($policy_premium)
                                        echo ' | Premium: ' . esc_html($policy_premium);
                                    echo '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="maljani-sales-btn" id="select-policy-btn">
                            <span>Continue</span>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const policySelect = document.getElementById('policy_id');
                                const policyForm = policySelect.closest('form');

                                policySelect.addEventListener('change', function () {
                                    if (this.value) {
                                        // Soumettre automatiquement le formulaire lorsqu'une police est sélectionnée
                                        setTimeout(() => {
                                            document.getElementById('select-policy-btn').click();
                                        }, 100);
                                    }
                                });

                                policyForm.addEventListener('submit', function (e) {
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
                            <?php
                            // Calculate fee display values for the summary
                            $fee_pct_display    = floatval(get_post_meta($policy_id, '_policy_client_fee_pct', true) ?: 0);
                            $service_fee_disp   = $fee_pct_display > 0 ? round(($premium * $fee_pct_display) / 100, 2) : 0;
                            $total_client_disp  = $is_agent ? $premium : round($premium + $service_fee_disp, 2);
                            ?>
                            <p><strong>Premium (Base):</strong> <span id="premium-currency"><?php echo esc_html($currency); ?></span> <span id="premium-amount"><?php echo esc_html($premium); ?></span></p>
                            <?php if ($service_fee_disp > 0): ?>
                                <?php if ($is_agent): ?>
                                    <p style="color:#6366f1;font-size:13px;"><strong>Service Fee:</strong> <?php echo esc_html($currency); ?> <?php echo number_format($service_fee_disp, 2); ?> <em>(paid by agency — not added to client price)</em></p>
                                <?php else: ?>
                                    <p><strong>Service Fee:</strong> <?php echo esc_html($currency); ?> <?php echo number_format($service_fee_disp, 2); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                            <p style="font-size:16px;font-weight:700;border-top:2px solid #e5e7eb;padding-top:8px;margin-top:4px;"><strong>Total to Pay:</strong> <?php echo esc_html($currency); ?> <?php echo number_format($total_client_disp, 2); ?></p>
                            <p><strong>Days covered:</strong> <span id="days-covered"><?php echo esc_html($days); ?></span></p>
                            <?php if ($should_prefill): ?>
                                <?php if ($is_insured && !$buying_for_self): ?>
                                    <p style="background:#e7f3ff;padding:8px;border-radius:4px;color:#0073aa;">
                                        <strong>Note:</strong> Using your profile information as an insured member
                                    </p>
                                <?php elseif ($buying_for_self === 'yes'): ?>
                                    <p style="background:#e7f3ff;padding:8px;border-radius:4px;color:#0073aa;">
                                        <strong>Note:</strong> Purchasing for yourself - details pre-filled from your profile
                                    </p>
                                <?php endif; ?>
                            <?php elseif ($buying_for_self === 'no'): ?>
                                <p style="background:#fff3cd;padding:8px;border-radius:4px;color:#856404;">
                                    <strong>Note:</strong> Purchasing for someone else - enter their details below
                                </p>
                            <?php elseif (!$current_user->exists()): ?>
                                <p style="background:#f8f9fa;padding:8px;border-radius:4px;color:#495057;">
                                    <strong>Note:</strong> An account will be created automatically for the insured person
                                </p>
                            <?php endif; ?>
                        </div>

                        <div id="insured-fields">
                            <?php if ($should_prefill && ($is_insured || $buying_for_self === 'yes')): ?>
                                <div
                                    style="background:#d4edda;padding:10px;border-radius:4px;margin-bottom:15px;font-size:14px;color:#155724;">
                                    <strong>📋 Pre-filled Information</strong><br>
                                    Your profile information has been automatically filled below. Please review and update if needed.
                                </div>
                            <?php endif; ?>
                            <div class="maljani-form-group">
                                <label>Full name (as it appears on passport)</label>
                                <input type="text" name="insured_names" placeholder="Full name"
                                    value="<?php echo esc_attr($client_data['full_name']); ?>" required>
                            </div>

                            <div class="maljani-form-group">
                                <label>Date of birth (DD/MM/YYYY)</label>
                                <input type="date" name="insured_dob" value="<?php echo esc_attr($client_data['dob']); ?>" required>
                            </div>

                            <div class="maljani-form-group">
                                <label>Passport number</label>
                                <input type="text" name="passport_number" placeholder="Passport number"
                                    value="<?php echo esc_attr($client_data['passport']); ?>" required>
                            </div>

                            <div class="maljani-form-group">
                                <label>National ID or PIN number</label>
                                <input type="text" name="national_id" placeholder="National ID or PIN number"
                                    value="<?php echo esc_attr($client_data['national_id']); ?>" required>
                            </div>

                            <div class="maljani-form-group">
                                <label>Phone number</label>
                                <input type="text" name="insured_phone" placeholder="Phone number"
                                    value="<?php echo esc_attr($client_data['phone']); ?>" required>
                            </div>

                            <div class="maljani-form-group">
                                <label>Email address</label>
                                <input type="email" name="insured_email" placeholder="Email address"
                                    value="<?php echo esc_attr($client_data['email']); ?>" required>
                            </div>

                            <div class="maljani-form-group">
                                <label>Residential address</label>
                                <input type="text" name="insured_address" placeholder="Residential address"
                                    value="<?php echo esc_attr($client_data['address']); ?>" required>
                            </div>

                            <div class="maljani-form-group">
                                <label>Country of origin</label>
                                <input type="text" name="country_of_origin" placeholder="Country of origin"
                                    value="<?php echo esc_attr($client_data['country']); ?>" required>
                            </div>
                        </div>

                        <div class="maljani-form-group">
                            <label>Amount to pay</label>
                            <input type="text" name="amount_paid" value="<?php echo esc_attr($premium); ?>" readonly>
                        </div>

                        <?php if (!empty($payment_details)): ?>
                            <div class="maljani-form-group">
                                <div class="notice notice-info">
                                    <h4 style="margin:0 0 10px 0;">💳 Payment Instructions</h4>
                                    <div style="font-size:14px;line-height:1.5;">
                                        <?php echo wpautop(esc_html($payment_details)); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="maljani-form-group">
                            <label>Payment reference</label>
                            <input type="text" name="payment_reference" placeholder="Enter payment reference" required>
                        </div>
                        <div class="maljani-form-group"
                            style="max-height:120px;overflow:auto;background:#f7f7f7;padding:10px;border-radius:6px;margin-bottom:8px;">
                            <?php echo wpautop(esc_html($terms)); ?>
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
                document.addEventListener('DOMContentLoaded', function () {
                    // Validation des formulaires GET
                    const getForms = document.querySelectorAll('form[method="get"]');
                    getForms.forEach(form => {
                        form.addEventListener('submit', function (e) {
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

                    // Calcul dynamique du premium and des jours
                    function updateDaysAndPremium() {
                        const dep = document.getElementById('departure');
                        const ret = document.getElementById('return');
                        const daysField = document.getElementById('days_covered');
                        const amountField = document.querySelector('input[name="amount_paid"]');
                        const premiumSpan = document.getElementById('premium-amount');
                        const daysSpan = document.getElementById('days-covered');
                        if (dep && ret && daysField && amountField) {
                            const d1 = new Date(dep.value);
                            const d2 = new Date(ret.value);
                            const diff = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24));
                            daysField.value = (dep.value && ret.value && diff > 0) ? diff : '';
                            if (daysSpan) daysSpan.textContent = daysField.value;
                            // Calcul premium
                            let premium = '';
                            if (window.maljaniPremiums && diff > 0) {
                                for (const row of window.maljaniPremiums) {
                                    if (diff >= parseInt(row.from) && diff <= parseInt(row.to)) {
                                        premium = row.premium;
                                        break;
                                    }
                                }
                            }
                            amountField.value = premium;
                            if (premiumSpan) premiumSpan.textContent = premium;
                        }
                    }
                    if (document.getElementById('departure')) document.getElementById('departure').addEventListener('change', updateDaysAndPremium);
                    if (document.getElementById('return')) document.getElementById('return').addEventListener('change', updateDaysAndPremium);
                });
            </script>
            <?php

            // Get the buffered content and return it
            return ob_get_clean();
    }

    public function handle_form_submission()
    {
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

            // --- Flexible Fee Configuration Helper ---
            // Reads new type/value meta; falls back to old _pct meta for backward compatibility
            $calc_fee = function(string $type_key, string $val_key, string $legacy_pct_key, float $base) use ($policy_id): float {
                $type = get_post_meta($policy_id, $type_key, true) ?: 'percent';
                $val  = get_post_meta($policy_id, $val_key, true);
                if ($val === '' || $val === false) {
                    // Fallback to legacy percent key
                    $val  = floatval(get_post_meta($policy_id, $legacy_pct_key, true) ?: 0);
                    $type = 'percent';
                } else {
                    $val = floatval($val);
                }
                if ($type === 'fixed') return round($val, 2);
                return round(($base * $val) / 100, 2);
            };

            $current_user = wp_get_current_user();
            $user_role    = ($current_user->exists()) ? $current_user->roles[0] : '';
            $is_agent     = ($user_role === 'agent');
            $is_insured   = ($user_role === 'insured');

            // Aggregator (Maljani) commission — earned from insurer, deducted from net
            $maljani_comm_amount = $calc_fee('_policy_aggregator_comm_type', '_policy_aggregator_comm_value', '_policy_aggregator_comm_pct', $premium);

            // Net to Insurer = premium minus aggregator commission
            $net_to_insurer = round($premium - $maljani_comm_amount, 2);

            // Service Fee: read from global settings (Settings → Global Fees).
            // Per-policy service fee meta is deprecated; we now use the global option.
            $global_svc_type  = get_option('maljani_fee_service_type',  'percent');
            $global_svc_val   = floatval(get_option('maljani_fee_service_value', 0));
            if ($global_svc_type === 'fixed') {
                $service_fee_amount = round($global_svc_val, 2);
            } else {
                $service_fee_amount = $global_svc_val > 0 ? round(($premium * $global_svc_val) / 100, 2) : 0;
            }
            $amount_tot_client = $is_agent ? $premium : round($premium + $service_fee_amount, 2);

            // Agency commission — paid by insurer directly to agency; Maljani tracks for visibility
            $agent_comm_amount = 0;
            if ($is_agent) {
                $agent_comm_amount = $calc_fee('_policy_agency_comm_type', '_policy_agency_comm_value', '_policy_agency_comm_pct', $premium);
            }

            // Générer un numéro de police unique
            $policy_number = 'POL-' . date('Ymd') . '-' . mt_rand(1000, 9999);

            $result = $wpdb->insert($table, [
                'policy_id' => $policy_id,
                'policy_number' => $policy_number, // Généré automatiquement
                'region' => $region_name,
                'premium' => $premium,
                'days' => $days,
                'departure' => sanitize_text_field($departure),
                'return' => sanitize_text_field($return),
                'insured_names' => sanitize_text_field($_POST['insured_names']),
                'insured_dob' => sanitize_text_field($_POST['insured_dob']),
                'passport_number' => sanitize_text_field($_POST['passport_number']),
                'national_id' => sanitize_text_field($_POST['national_id']),
                'insured_phone' => sanitize_text_field($_POST['insured_phone']),
                'insured_email' => sanitize_email($_POST['insured_email']),
                'insured_address' => sanitize_text_field($_POST['insured_address']),
                'country_of_origin' => sanitize_text_field($_POST['country_of_origin']),
                'agent_id' => get_current_user_id(),
                'agent_name' => ($is_agent || $is_insured) ? $current_user->display_name : '',
                'amount_paid' => $amount_tot_client,
                'service_fee_amount' => $service_fee_amount,
                'maljani_commission_amount' => $maljani_comm_amount,
                'agent_commission_amount' => $agent_comm_amount,
                'agent_commission_status' => 'unpaid',
                'net_to_insurer' => $net_to_insurer,
                'payment_reference' => sanitize_text_field($_POST['payment_reference'] ?? ''),
                'payment_status' => 'pending',
                'policy_status' => 'unconfirmed',
                'workflow_status' => 'draft',
                'terms' => isset($_POST['accept_terms']) ? 1 : 0 // Acceptation des conditions
            ]);
            if ($result) {
                $sale_id = $wpdb->insert_id;

                // --- Pesapal Integration Check ---
                $pesapal_key = get_option('maljani_pesapal_consumer_key');
                if (!empty($pesapal_key)) {
                    require_once plugin_dir_path(__FILE__) . 'api/class-maljani-pesapal-gateway.php';
                    $gateway = new Maljani_Pesapal_Gateway();
                    
                    $insurer_id = get_post_meta($policy_id, '_policy_insurer', true);
                    $insurer_pesapal_id = $insurer_id ? get_post_meta($insurer_id, '_insurer_pesapal_merchant_id', true) : '';

                    $split_payload = [];
                    if (!empty($insurer_pesapal_id)) {
                        // Split Payment: Premium to Insurer, rest to Maljani (implied)
                        $split_payload[] = [
                            'merchant_id' => $insurer_pesapal_id,
                            'amount'      => (float)$net_to_insurer
                        ];
                    }

                    $billing_info = [
                        'email_address' => sanitize_email($_POST['insured_email']),
                        'phone_number'  => sanitize_text_field($_POST['insured_phone']),
                        'first_name'    => explode(' ', sanitize_text_field($_POST['insured_names']))[0],
                        'last_name'     => ''
                    ];

                    $order_resp = $gateway->create_order(
                        $sale_id,
                        $amount_tot_client,
                        'Insurance Policy Purchase: ' . $policy_number,
                        $billing_info,
                        $split_payload
                    );

                    if (!is_wp_error($order_resp) && isset($order_resp['redirect_url'])) {
                        // Update sale with merchant reference if available
                        $wpdb->update($table, ['payment_reference' => $order_resp['order_tracking_id'] ?? ''], ['id' => $sale_id]);
                        wp_redirect($order_resp['redirect_url']);
                        exit;
                    }
                }

                // Fallback to existing manual redirect logic if Pesapal not active or failed
                if ($current_user->exists()) {
                    // Utilisateur connecté - rediriger vers le dashboard configuré
                    $dashboard_page_id = get_option('maljani_user_dashboard_page');
                    if ($dashboard_page_id && get_post($dashboard_page_id)) {
                        $dashboard_url = get_permalink($dashboard_page_id);
                        wp_redirect(add_query_arg('sale_success', '1', $dashboard_url));
                    } else {
                        // Fallback si pas de dashboard configuré
                        wp_redirect(add_query_arg('sale_success', '1', home_url()));
                    }
                    exit;
                } else {
                    // Utilisateur non connecté - vérifier si l'email existe
                    $insured_email = sanitize_email($_POST['insured_email']);
                    $insured_names = sanitize_text_field($_POST['insured_names']);

                    // Vérifier si l'email existe déjà
                    $existing_user = get_user_by('email', $insured_email);
                    if ($existing_user) {
                        // Utilisateur existe déjà - rediriger vers login avec message
                        $login_url = wp_login_url();
                        wp_redirect(add_query_arg([
                            'sale_success' => '1',
                            'message' => 'account_exists'
                        ], $login_url));
                    } else {
                        // Créer un nouveau compte insured
                        $username = sanitize_user($insured_email); // Utiliser l'email comme username
                        $password = wp_generate_password(12, false);

                        $user_id = wp_create_user($username, $password, $insured_email);

                        if (!is_wp_error($user_id)) {
                            // Assigner le rôle "insured"
                            wp_update_user(['ID' => $user_id, 'role' => 'insured']);

                            // Ajouter les métadonnées utilisateur
                            update_user_meta($user_id, 'full_name', $insured_names);
                            update_user_meta($user_id, 'phone', sanitize_text_field($_POST['insured_phone']));
                            update_user_meta($user_id, 'dob', sanitize_text_field($_POST['insured_dob']));
                            update_user_meta($user_id, 'passport_number', sanitize_text_field($_POST['passport_number']));
                            update_user_meta($user_id, 'national_id', sanitize_text_field($_POST['national_id']));
                            update_user_meta($user_id, 'address', sanitize_text_field($_POST['insured_address']));
                            update_user_meta($user_id, 'country', sanitize_text_field($_POST['country_of_origin']));

                            // Mettre à jour l'enregistrement de vente avec le nouvel ID utilisateur
                            $wpdb->update(
                                $table,
                                ['agent_id' => $user_id],
                                ['id' => $wpdb->insert_id]
                            );

                            // Connecter automatiquement l'utilisateur
                            wp_set_current_user($user_id);
                            wp_set_auth_cookie($user_id);

                            // Envoyer un email avec les informations de connexion
                            wp_new_user_notification($user_id, null, 'both');

                            // Rediriger vers le profil utilisateur ou dashboard
                            $dashboard_page_id = get_option('maljani_user_dashboard_page');
                            if ($dashboard_page_id && get_post($dashboard_page_id)) {
                                $redirect_url = get_permalink($dashboard_page_id);
                            } else {
                                $redirect_url = admin_url('profile.php');
                            }

                            wp_redirect(add_query_arg([
                                'sale_success' => '1',
                                'new_account' => '1'
                            ], $redirect_url));
                        } else {
                            // Erreur de création de compte - rediriger avec erreur
                            wp_redirect(add_query_arg('sale_error', 'account_creation_failed', wp_get_referer() ?: home_url()));
                        }
                    }
                }
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