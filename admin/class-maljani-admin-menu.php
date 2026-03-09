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

        // Sous-menu Page Management
        add_submenu_page(
            'maljani_travel',
            'Page Management',
            '📄 Page Management',
            'manage_options',
            'maljani_pages_admin',
            [$this, 'render_pages_admin']
        );

        // Sous-menu Diagnostics
        add_submenu_page(
            'maljani_travel',
            'Diagnostics',
            '🔍 Diagnostics',
            'manage_options',
            'maljani_diagnostics',
            [$this, 'render_diagnostics']
        );


        add_submenu_page(
            'maljani_travel',
            'Policy Sales',
            'Policy Sales',
            'manage_options',
            'policy_sales',
            [$this, 'render_policy_sales']
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

        // --- NEW CRM & ROLE MANAGEMENT MENUS ---
        
        add_submenu_page(
            'maljani_travel',
            'Manage Agencies',
            'Manage Agencies',
            'manage_maljani_agencies',
            'maljani_agencies_admin',
            [$this, 'render_agencies_admin']
        );

        add_submenu_page(
            'maljani_travel',
            'Manage Clients',
            'Manage Clients',
            'edit_maljani_policies', // Editors and above can manage clients
            'maljani_clients_admin',
            [$this, 'render_clients_admin']
        );

        add_submenu_page(
            'maljani_travel',
            'Manage Payments',
            'Manage Payments',
            'manage_maljani_payments',
            'maljani_payments_admin',
            [$this, 'render_payments_admin']
        );

        // --- PER-ROLE USER MANAGEMENT PAGES ---
        add_submenu_page(
            'maljani_travel',
            'Maljani Team',
            '🛡️ Maljani Team',
            'manage_options',
            'maljani_users_maljani_team',
            [$this, 'render_users_maljani_team']
        );
        add_submenu_page(
            'maljani_travel',
            'Agencies',
            '🏢 Agencies',
            'manage_options',
            'maljani_users_agencies',
            [$this, 'render_users_agencies']
        );
        add_submenu_page(
            'maljani_travel',
            'Insurers',
            '🏦 Insurers',
            'manage_options',
            'maljani_users_insurers',
            [$this, 'render_users_insurers']
        );
        add_submenu_page(
            'maljani_travel',
            'Clients',
            '👤 Clients',
            'manage_options',
            'maljani_users_clients',
            [$this, 'render_users_clients']
        );

        add_submenu_page(
            'maljani_travel',
            'Manage Roles',
            'Manage Roles',
            'manage_options', // WP Admins always have this; custom maljani_super_admin gets it too
            'maljani_roles_admin',
            [$this, 'render_roles_admin']
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

    public function render_agencies_admin() {
        if (!class_exists('Maljani_Agencies_Admin')) {
            require_once plugin_dir_path(__FILE__) . 'class-maljani-agencies-admin.php';
        }
        Maljani_Agencies_Admin::render_page();
    }

    public function render_clients_admin() {
        if (!class_exists('Maljani_Clients_Admin')) {
            require_once plugin_dir_path(__FILE__) . 'class-maljani-clients-admin.php';
        }
        Maljani_Clients_Admin::render_page();
    }

    public function render_payments_admin() {
        if (!class_exists('Maljani_Payments_Admin')) {
            require_once plugin_dir_path(__FILE__) . 'class-maljani-payments-admin.php';
        }
        Maljani_Payments_Admin::render_page();
    }

    public function render_roles_admin() {
        if (!class_exists('Maljani_Roles_Admin')) {
            require_once plugin_dir_path(__FILE__) . 'class-maljani-roles-admin.php';
        }
        Maljani_Roles_Admin::render_page();
    }

    private function load_users_admin() {
        if (!class_exists('Maljani_Users_Admin')) {
            require_once plugin_dir_path(__FILE__) . 'class-maljani-users-admin.php';
        }
    }
    public function render_users_maljani_team() {
        $this->load_users_admin();
        Maljani_Users_Admin::render_page('maljani_team');
    }
    public function render_users_agencies() {
        $this->load_users_admin();
        Maljani_Users_Admin::render_page('agencies');
    }
    public function render_users_insurers() {
        $this->load_users_admin();
        Maljani_Users_Admin::render_page('insurers');
    }
    public function render_users_clients() {
        $this->load_users_admin();
        Maljani_Users_Admin::render_page('clients');
    }

    public function render_pages_admin() {
        if (!class_exists('Maljani_Pages_Admin')) {
            require_once plugin_dir_path(__FILE__) . 'class-maljani-pages-admin.php';
        }
        Maljani_Pages_Admin::render_page();
    }

    public function render_diagnostics() {
        if (!class_exists('Maljani_Diagnostics')) {
            require_once plugin_dir_path(__FILE__) . 'class-maljani-diagnostics.php';
        }
        Maljani_Diagnostics::render_page();
    }
}
new Maljani_Admin_Menu();

