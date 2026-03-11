<?php
/**
 * Plugin Name:       maljani travel hub
 * Plugin URI: https://github.com/kanji8210/maljani_travel_insurance_hub 
 * Description:       Maljani Insurance Aggregator is a modular WordPress plugin designed to streamline the management of insurers and policies within a centralized admin interface. It allows administrators to register, display, and manage custom post types for insurer profiles and insurance policies, complete with logo uploads, product listings, and detailed descriptions. Built on a scalable boilerplate architecture, it supports CRUD operations, role-based permissions, and frontend submissions. The plugin also lays the groundwork for REST API integration and dynamic premium calculations, making it ideal for organizations aiming to offer user-friendly insurance comparison tools while maintaining clean code and extendability. Available shortcodes: [maljani_policy_sale], [maljani_user_dashboard], [maljani_agent_register]. See SHORTCODES.md for complete documentation.
 * Version:           1.0.2
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
define( 'MALJANI_VERSION', '1.0.2' );

// ==========================
// INCLUSIONS PRINCIPALES
// ==========================

// Logger and Cache (load first)
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-cache.php';

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
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-insured-reg.php';
// Add user dashboard
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-user-dashboard.php';
// Add client dashboard
require_once plugin_dir_path(__FILE__) . 'includes/class-maljani-client-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-maljani-registration.php';

// Initialize
// Maljani_Registration::init(); // Handled in the class file itself

// Add icons shortcode
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-icons.php';
// Add diagnostic tool
require_once plugin_dir_path( __FILE__ ) . 'templates/diagnostic.php';

// ==========================
// HOOKS D'ACTIVATION/DÉSACTIVATION
// ==========================

// Appelle les classes d'activation/désactivation (pour setup DB, options, etc.)
function activate_maljani() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-activator.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-policy-verification.php';
    Maljani_Activator::activate();
    Maljani_Policy_Verification::activate();
}
function deactivate_maljani() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-deactivator.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-policy-verification.php';
    Maljani_Deactivator::deactivate();
    Maljani_Policy_Verification::deactivate();
}
register_activation_hook( __FILE__, 'activate_maljani' );
register_deactivation_hook( __FILE__, 'deactivate_maljani' );

// Ajoute les rôles personnalisés à l'activation
register_activation_hook(__FILE__, function() {
    // Role: Agent (Agency)
    add_role('agent', 'Agency', [
        'read' => true,
        'maljani_agency_dashboard' => true // Custom capability for the new CRM dashboard
    ]);
    
    // Role: Insured (Client)
    add_role('insured', 'Client', [
        'read' => true,
        'maljani_client_dashboard' => true
    ]);

    // Role: Insurer (existing)
    add_role('insurer', 'Insurer', [
        'read' => true,
        'review_maljani_policies' => true
    ]);

    // Role: Maljani Editor / Moderator
    add_role('maljani_editor', 'Maljani Editor', [
        'read' => true,
        'edit_maljani_policies' => true // Can perform initial reviews and forward to insurer
    ]);

    // Role: Maljani Admin
    add_role('maljani_admin', 'Maljani Admin', [
        'read' => true,
        'edit_maljani_policies' => true,
        'manage_maljani_agencies' => true,
        'manage_maljani_payments' => true,
        'activate_maljani_policies' => true // Can perform final activation and doc uploads
    ]);

    // Role: Maljani Super Admin
    add_role('maljani_super_admin', 'Maljani Super Admin', [
        'read' => true,
        'edit_maljani_policies' => true,
        'manage_maljani_agencies' => true,
        'manage_maljani_payments' => true,
        'activate_maljani_policies' => true,
        'manage_maljani_roles' => true, // Can assign our custom roles to WP users
        'manage_options' => true // Can access general WP settings if needed
    ]);

    // Also grant super admin powers to actual WordPress Administrators
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('edit_maljani_policies');
        $admin_role->add_cap('manage_maljani_agencies');
        $admin_role->add_cap('manage_maljani_payments');
        $admin_role->add_cap('activate_maljani_policies');
        $admin_role->add_cap('manage_maljani_roles');
    }
});

// Supprime les rôles personnalisés à la désactivation
register_deactivation_hook(__FILE__, function() {
    remove_role('agent');
    remove_role('insured');
    remove_role('insurer');
    remove_role('maljani_editor');
    remove_role('maljani_admin');
    remove_role('maljani_super_admin');
    
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('edit_maljani_policies');
        $admin_role->remove_cap('manage_maljani_agencies');
        $admin_role->remove_cap('manage_maljani_payments');
        $admin_role->remove_cap('activate_maljani_policies');
        $admin_role->remove_cap('manage_maljani_roles');
    }
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

// Include style isolation manager
require_once plugin_dir_path(__FILE__) . 'includes/class-maljani-style-isolation.php';

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

// Include PDF generator and register admin action for secure generation
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-pdf.php';

// Support chat
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-live-chat.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/class-maljani-live-chat-admin.php';

// Agency CRM & Workflow
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-crm.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-workflow.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-notifications.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-maljani-crm-dashboard.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/class-maljani-crm-admin.php';

add_action('admin_post_maljani_verify_policy', 'maljani_handle_verify_policy');
function maljani_handle_verify_policy() {
    if ( ! isset( $_GET['sale_id'] ) || ! isset( $_GET['token'] ) ) {
        wp_die( 'Missing parameters.' );
    }
    $sale_id = intval( $_GET['sale_id'] );
    $token = wp_unslash( $_GET['token'] );

    $nonce = isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'maljani_verify_policy_' . $sale_id ) ) {
        wp_die( 'Invalid nonce.' );
    }

    // Use verifier to validate token and display a small verification page
    Maljani_PDF_Generator::verify_and_display( $sale_id, $token );
}
