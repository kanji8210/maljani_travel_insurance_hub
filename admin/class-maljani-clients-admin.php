<?php

class Maljani_Clients_Admin {

    public static function render_page() {
        if (!current_user_can('edit_maljani_policies')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $clients_table = $wpdb->prefix . 'maljani_clients';
        $agencies_table = $wpdb->prefix . 'maljani_agencies';

        $clients = $wpdb->get_results("
            SELECT c.*, a.agency_name 
            FROM $clients_table c 
            LEFT JOIN $agencies_table a ON c.agency_id = a.id 
            ORDER BY c.created_at DESC
        ");

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Manage Clients</h1>';
        echo '<hr class="wp-header-end">';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Name</th><th>Email / Phone</th><th>Passport</th><th>National ID</th><th>Agency</th></tr></thead>';
        echo '<tbody>';

        if (empty($clients)) {
            echo '<tr><td colspan="5">No clients found.</td></tr>';
        } else {
            foreach ($clients as $c) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($c->first_name . ' ' . $c->last_name) . '</strong></td>';
                echo '<td>' . esc_html($c->email) . '<br/>' . esc_html($c->phone) . '</td>';
                echo '<td>' . esc_html($c->passport_number) . '</td>';
                echo '<td>' . esc_html($c->national_id) . '</td>';
                echo '<td>' . esc_html($c->agency_name ?: 'Direct') . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }
}
