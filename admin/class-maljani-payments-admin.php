<?php

class Maljani_Payments_Admin {

    public static function render_page() {
        if (!current_user_can('manage_maljani_payments')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $payments_table = $wpdb->prefix . 'maljani_payments';
        $agencies_table = $wpdb->prefix . 'maljani_agencies';
        $policy_table = $wpdb->prefix . 'policy_sale';

        // Handle edits
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maljani_payment_action']) && wp_verify_nonce($_POST['_wpnonce'], 'maljani_edit_payment')) {
            if ($_POST['maljani_payment_action'] === 'update_status') {
                $payment_id = intval($_POST['payment_id']);
                $new_status = sanitize_text_field($_POST['status']);
                $wpdb->update($payments_table, ['status' => $new_status, 'updated_at' => current_time('mysql', 1)], ['id' => $payment_id]);
                echo '<div class="notice notice-success is-dismissible"><p>Payment status updated.</p></div>';
            }
        }

        $payments = $wpdb->get_results("
            SELECT p.*, a.agency_name, s.policy_number 
            FROM $payments_table p 
            LEFT JOIN $agencies_table a ON p.agency_id = a.id 
            LEFT JOIN $policy_table s ON p.policy_id = s.id
            ORDER BY p.created_at DESC
        ");

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Manage Payments</h1>';
        echo '<hr class="wp-header-end">';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Date</th><th>Agency</th><th>Policy #</th><th>Amount</th><th>Reference</th><th>Status</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        if (empty($payments)) {
            echo '<tr><td colspan="7">No payment records found.</td></tr>';
        } else {
            foreach ($payments as $p) {
                echo '<tr>';
                echo '<td>' . esc_html($p->created_at) . '</td>';
                echo '<td>' . esc_html($p->agency_name ?: 'System') . '</td>';
                echo '<td>' . esc_html($p->policy_number ?: 'Draft #' . $p->policy_id) . '</td>';
                echo '<td>$' . esc_html($p->amount) . '</td>';
                echo '<td>' . esc_html($p->reference) . '</td>';
                
                $status_color = $p->status === 'verified' ? 'green' : ($p->status === 'forwarded' ? 'blue' : 'orange');
                echo '<td><span style="color:' . $status_color . '; font-weight:bold;">' . esc_html(strtoupper($p->status)) . '</span></td>';
                
                echo '<td>';
                echo '<form method="post" style="display:inline-flex; gap: 5px;">';
                wp_nonce_field('maljani_edit_payment');
                echo '<input type="hidden" name="maljani_payment_action" value="update_status">';
                echo '<input type="hidden" name="payment_id" value="' . esc_attr($p->id) . '">';
                echo '<select name="status">';
                echo '<option value="pending" ' . selected($p->status, 'pending', false) . '>Pending</option>';
                echo '<option value="verified" ' . selected($p->status, 'verified', false) . '>Received & Verified</option>';
                echo '<option value="forwarded" ' . selected($p->status, 'forwarded', false) . '>Forwarded to Insurer</option>';
                echo '</select> ';
                echo '<button type="submit" class="button button-small">Update</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }
}
