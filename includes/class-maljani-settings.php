<?php
//includes\class-maljani-settings.php

class Maljani_Settings {
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('update_option_maljani_user_registration_page', [$this, 'maybe_add_registration_shortcode'], 10, 2);
        add_action('update_option_maljani_user_dashboard_page', [$this, 'maybe_add_dashboard_shortcode'], 10, 2);
        add_action('update_option_maljani_policy_sale_page', [$this, 'maybe_add_policy_sale_shortcode'], 10, 2);
    }

    public function register_settings() {
        register_setting('maljani_settings_group', 'maljani_user_registration_page');
        register_setting('maljani_settings_group', 'maljani_user_dashboard_page');
        register_setting('maljani_settings_group', 'maljani_policy_sale_page');
        register_setting('maljani_settings_group', 'maljani_hide_modified_files', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
    }

    public function maybe_add_registration_shortcode($new_page_id, $old_page_id) {
        if ($new_page_id && get_post_type($new_page_id) === 'page') {
            $content = get_post_field('post_content', $new_page_id);
            if (strpos($content, '[maljani_user_registration]') === false) {
                $content .= "\n\n[maljani_user_registration]";
                wp_update_post([
                    'ID' => $new_page_id,
                    'post_content' => $content,
                ]);
            }
        }
    }

    public function maybe_add_dashboard_shortcode($new_page_id, $old_page_id) {
        if ($new_page_id && get_post_type($new_page_id) === 'page') {
            $content = get_post_field('post_content', $new_page_id);
            if (strpos($content, '[maljani_user_dashboard]') === false) {
                $content .= "\n\n[maljani_user_dashboard]";
                wp_update_post([
                    'ID' => $new_page_id,
                    'post_content' => $content,
                ]);
            }
        }
    }

    public function maybe_add_policy_sale_shortcode($new_page_id, $old_page_id) {
        if ($new_page_id && get_post_type($new_page_id) === 'page') {
            $content = get_post_field('post_content', $new_page_id);
            if (strpos($content, '[maljani_policy_sale]') === false) {
                $content .= "\n\n[maljani_policy_sale]";
                wp_update_post([
                    'ID' => $new_page_id,
                    'post_content' => $content,
                ]);
            }
        }
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Choose pages to correspond functions</h1>
            <form method="post" action="options.php">
                <?php settings_fields('maljani_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">User Registration Page</th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name' => 'maljani_user_registration_page',
                                'selected' => get_option('maljani_user_registration_page'),
                                'show_option_none' => '-- Select a page --'
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">User Dashboard Page</th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name' => 'maljani_user_dashboard_page',
                                'selected' => get_option('maljani_user_dashboard_page'),
                                'show_option_none' => '-- Select a page --'
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Policy Sale Page</th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name' => 'maljani_policy_sale_page',
                                'selected' => get_option('maljani_policy_sale_page'),
                                'show_option_none' => '-- Select a page --'
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Hide Modified Files Notices</th>
                        <td>
                            <label>
                                <input type="checkbox" name="maljani_hide_modified_files" value="1" <?php checked(get_option('maljani_hide_modified_files', true)); ?> />
                                Masquer les notifications de fichiers modifiés dans l'administration
                            </label>
                            <p class="description">Cette option masque les notifications concernant les fichiers modifiés du plugin dans le tableau de bord WordPress.</p>
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