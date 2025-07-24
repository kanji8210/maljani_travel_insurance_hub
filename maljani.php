<?php
/**
 * Plugin Name:       maljani travel hub
 * GitLab Plugin URI: https://github.com/kanji8210/maljani_insuarance_agregator
*GitLab Branch: main
 * Plugin URI:        https://github.com/kanji8210/maljani_insuarance_agregator
 * Description:       Maljani Insurance Aggregator is a modular WordPress plugin designed to streamline the management of insurers and policies within a centralized admin interface. It allows administrators to register, display, and manage custom post types for insurer profiles and insurance policies, complete with logo uploads, product listings, and detailed descriptions. Built on a scalable boilerplate architecture, it supports CRUD operations, role-based permissions, and frontend submissions. The plugin also lays the groundwork for REST API integration and dynamic premium calculations, making it ideal for organizations aiming to offer user-friendly insurance comparison tools while maintaining clean code and extendability.
 * Version:           1.0.0
 * Author:            Dennis kip
 * Author URI:        https://kipdevwp.tech/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       maljani
 * Domain Path:       /languages
 */

// Sécurité : empêche l'accès direct au fichier
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Définition de la version du plugin
define( 'MALJANI_VERSION', '1.0.0' );

// ==========================
// INCLUSIONS PRINCIPALES
// ==========================

// Classe principale du plugin
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani.php';
// Filtres et AJAX pour les policies
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-filter.php';
// Réglages du plugin
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-settings.php';
//maljani sales page
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-sales-page.php';
//management of policy sals
require_once plugin_dir_path( __FILE__ ) . 'admin/class-maljani-policy-sales.php';
//add admin menu
require_once plugin_dir_path( __FILE__ ) . 'admin/class-maljani-admin-menu.php';
// Add agent registration
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-agent-registration.php';
// Add user dashboard
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-user-dashboard.php';
// Add diagnostic tool
require_once plugin_dir_path( __FILE__ ) . 'templates/diagnostic.php';

// ==========================
// HOOKS D'ACTIVATION/DÉSACTIVATION
// ==========================

// Appelle les classes d'activation/désactivation (pour setup DB, options, etc.)
function activate_maljani() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-activator.php';
    Maljani_Activator::activate();
}
function deactivate_maljani() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-deactivator.php';
    Maljani_Deactivator::deactivate();
}
register_activation_hook( __FILE__, 'activate_maljani' );
register_deactivation_hook( __FILE__, 'deactivate_maljani' );

// Ajoute les rôles personnalisés à l'activation
register_activation_hook(__FILE__, function() {
    add_role('agent', 'Agent', [
        'read' => true,
        // Ajoute d'autres capacités si besoin
    ]);
    add_role('insurer', 'Insurer', [
        'read' => true,
        // Ajoute d'autres capacités si besoin
    ]);
});

// Supprime les rôles personnalisés à la désactivation
register_deactivation_hook(__FILE__, function() {
    remove_role('agent');
    remove_role('insurer');
});

// ==========================
// ENREGISTREMENT DES CUSTOM POST TYPES
// ==========================

// Déclare les CPT "insurer_profile" et "policy"
function maljani_register_custom_post_types() {
    // CPT pour les assureurs
    require_once plugin_dir_path(__FILE__) . 'admin/class-insurer-profile-cpt.php';
    $insurer_profile_cpt = new Insurer_Profile_CPT();
    $insurer_profile_cpt->register_Insurer();

    // CPT pour les policies
    require_once plugin_dir_path(__FILE__) . 'admin/class-maljani_policy-cpt.php';
    if (class_exists('Policy_CPT')) {
        $insurance_policy_cpt = new Policy_CPT();
        if (method_exists($insurance_policy_cpt, 'register_Insurance_Policy')) {
            $insurance_policy_cpt->register_Insurance_Policy();
        }
    }
}
add_action('init', 'maljani_register_custom_post_types');

// ==========================
// LANCEMENT DU PLUGIN PRINCIPAL
// ==========================

// Instancie et lance la classe principale
function run_maljani() {
    $plugin = new Maljani();
    $plugin->run();
}
run_maljani();

// ==========================
// TEMPLATE OVERRIDE POUR LES CPT
// ==========================

// Utilise les templates du plugin pour les single policy et single insurer_profile
add_filter('template_include', function($template) {
    if (is_singular('policy')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/single-policy.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    if (is_singular('insurer_profile')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/single-profile.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
});
