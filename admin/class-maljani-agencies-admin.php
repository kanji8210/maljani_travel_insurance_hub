<?php

class Maljani_Agencies_Admin {

    public static function render_page() {
        if (!current_user_can('manage_maljani_agencies')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'maljani_agencies';

        // Handle edits
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maljani_agency_action']) && wp_verify_nonce($_POST['_wpnonce'], 'maljani_edit_agency')) {
            if ($_POST['maljani_agency_action'] === 'update_commission') {
                $agency_id = intval($_POST['agency_id']);
                $commission = floatval($_POST['commission_percent']);
                $wpdb->update($table, ['commission_percent' => $commission, 'updated_at' => current_time('mysql', 1)], ['id' => $agency_id]);
                echo '<div class="notice notice-success is-dismissible"><p>Commission updated successfully.</p></div>';
            }
        }

        $agencies = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Manage Agencies</h1>';
        echo '<hr class="wp-header-end">';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Agency Name</th><th>Contact Name</th><th>Contact Email</th><th>Commission %</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        if (empty($agencies)) {
            echo '<tr><td colspan="5">No agencies found.</td></tr>';
        } else {
            foreach ($agencies as $a) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($a->agency_name) . '</strong></td>';
                echo '<td>' . esc_html($a->contact_name) . '</td>';
                echo '<td><a href="mailto:' . esc_attr($a->contact_email) . '">' . esc_html($a->contact_email) . '</a></td>';
                echo '<td>';
                echo '<form method="post" style="display:inline-flex; align-items:center; gap:5px;">';
                wp_nonce_field('maljani_edit_agency');
                echo '<input type="hidden" name="maljani_agency_action" value="update_commission">';
                echo '<input type="hidden" name="agency_id" value="' . esc_attr($a->id) . '">';
                echo '<input type="number" step="0.01" name="commission_percent" value="' . esc_attr($a->commission_percent) . '" style="width:70px;"> % ';
                echo '<button type="submit" class="button button-small">Save</button>';
                echo '</form>';
                echo '</td>';
                echo '<td><a href="#" class="button">View Performance</a></td>'; // To be expanded
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }
}
