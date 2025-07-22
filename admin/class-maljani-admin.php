<?php

/**
 * Fonctionnalités spécifiques à l'administration du plugin.
 *
 * @link       https://kipdevwp.tech
 * @since      1.0.0
 *
 * @package    Maljani
 * @subpackage Maljani/admin
 */

/**
 * Classe pour la gestion de l'administration du plugin.
 *
 * Définit le nom du plugin, la version, et les hooks pour l'administration.
 *
 * @author     Dennis kip <denisdekemet@gmail.com>
 */
class Maljani_Admin {

    /**
     * ID du plugin.
     *
     * @var string
     */
    private $plugin_name;

    /**
     * Version du plugin.
     *
     * @var string
     */
    private $version;

    /**
     * Constructeur : initialise les propriétés.
     *
     * @param string $plugin_name Nom du plugin.
     * @param string $version     Version du plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Enregistre les hooks nécessaires pour l'administration.
     *
     * À appeler depuis la classe principale pour lier les hooks.
     *
     * @param Maljani_Loader $loader        Instance du loader.
     * @param Maljani_Admin  $plugin_admin  Instance de cette classe.
     */
    public function register_admin_hooks( $loader, $plugin_admin ) {
        // CPT Insurer Profile
        $loader->add_action( 'init', $plugin_admin, 'register_insurer_profile_cpt' );
        // CPT Policy
        $loader->add_action( 'init', $plugin_admin, 'register_policy_cpt' );
        // Styles et scripts admin
        $loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
    }

    /**
     * Enregistre le CPT "Insurer Profile".
     */
    public function register_insurer_profile_cpt() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-insurer-profile-cpt.php';
        $insurer_profile_cpt = new Insurer_Profile_CPT();
        $insurer_profile_cpt->register_Insurer();
    }

    /**
     * Enregistre le CPT "Policy".
     */
    public function register_policy_cpt() {
        require_once plugin_dir_path( __FILE__ ) . 'class-policy-cpt.php';
        $policy_cpt = new Policy_CPT();
        $policy_cpt->register_policy();
    }

    /**
     * Enregistre les styles pour l'admin.
     */
    public function enqueue_styles() {
        // Style principal du plugin
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'css/maljani-admin.css',
            array(),
            $this->version,
            'all'
        );
        
        // CSS pour masquer les notifications de fichiers modifiés
        wp_enqueue_style(
            $this->plugin_name . '-hide-modified',
            plugin_dir_url( __FILE__ ) . 'css/hide-modified-files.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Enregistre les scripts pour l'admin.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'js/maljani-admin.js',
            array( 'jquery' ),
            $this->version,
            false
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
            wp_enqueue_media();
        }
    }
}

