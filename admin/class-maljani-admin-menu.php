<?php
class Maljani_Admin_Menu {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        // Menu principal
        add_menu_page(
            'Maljani Travel',
            'Maljani Travel',
            'manage_options',
            'maljani_travel',
            [$this, 'render_dashboard'],
            'dashicons-admin-site',
            2
        );

        // Sous-menu Settings
        add_submenu_page(
            'maljani_travel',
            'Settings',
            'Settings',
            'manage_options',
            'maljani_settings',
            [$this, 'render_settings']
        );

        // Sous-menu Policies
        add_submenu_page(
            'maljani_travel',
            'Policies',
            'Policies',
            'manage_options',
            'edit.php?post_type=policy'
        );
        add_submenu_page(
            'maljani_travel',
            'Add New Policy',
            'Add New Policy',
            'manage_options',
            'post-new.php?post_type=policy'
        );
        add_submenu_page(
            'maljani_travel',
            'Policy Sales',
            'Policy Sales',
            'manage_options',
            'policy_sales',
            [$this, 'render_policy_sales']
        );

        // Sous-menu Insurer Profiles
        add_submenu_page(
            'maljani_travel',
            'Insurer Profiles',
            'Insurer Profiles',
            'manage_options',
            'edit.php?post_type=insurer_profile'
        );
        add_submenu_page(
            'maljani_travel',
            'Add New Insurer',
            'Add New Insurer',
            'manage_options',
            'post-new.php?post_type=insurer_profile'
        );

        // Sous-menu Database Tools
        add_submenu_page(
            'maljani_travel',
            'Database Tools',
            'Database Tools',
            'manage_options',
            'maljani_database_tools',
            [$this, 'render_database_tools']
        );
    }

    public function render_dashboard() {
        echo '<h1>Maljani Travel Dashboard</h1>';
    }

    public function render_settings() {
        //render the settings page
        echo '<h1>Maljani Settings</h1>';
        Maljani_Settings::render_settings_page();
    }

    public function render_policy_sales() {
        echo '<h1>Policy Sales</h1>';
        // render maljani sales page
        Maljani_Policy_Sales_Admin::render_sales_table();
    }

    public function render_database_tools() {
        if (!class_exists('Maljani_Database_Tools')) {
            require_once plugin_dir_path(__FILE__) . 'class-maljani-database-tools.php';
        }
        Maljani_Database_Tools::render_database_tools_page();
    }
}
new Maljani_Admin_Menu();