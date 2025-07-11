<?php
// filepath: c:\xampp\htdocs\wordpress\wp-content\plugins\maljani_travel_insurance_hub\includes\class-maljani-settings.php

class Maljani_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page() {
        add_menu_page(
            'Maljani Settings',
            'Maljani Settings',
            'manage_options',
            'maljani-settings',
            [$this, 'render_settings_page'],
            'dashicons-admin-generic'
        );
    }

    public function register_settings() {
        register_setting('maljani_settings_group', 'maljani_agent_page');
        register_setting('maljani_settings_group', 'maljani_insured_page');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Maljani Plugin Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('maljani_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Agent Login/Register Page</th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name' => 'maljani_agent_page',
                                'selected' => get_option('maljani_agent_page'),
                                'show_option_none' => '-- Select a page --'
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Insured Login/Register Page</th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name' => 'maljani_insured_page',
                                'selected' => get_option('maljani_insured_page'),
                                'show_option_none' => '-- Select a page --'
                            ]);
                            ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
new Maljani_Settings();