<?php

class Maljani_Payments_Admin {

    public static function render_page() {
        if (!current_user_can('manage_maljani_payments')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $policy_table = $wpdb->prefix . 'policy_sale';
        $agencies_table = $wpdb->prefix . 'maljani_agencies';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maljani_settlement_action']) && wp_verify_nonce($_POST['_wpnonce'], 'maljani_edit_settlement')) {
            if ($_POST['maljani_settlement_action'] === 'update_insurer_settlement') {
                $sale_id = intval($_POST['sale_id']);
                $allowed_statuses = ['not_due', 'due', 'paid', 'failed'];
                $new_status = sanitize_text_field($_POST['insurer_payment_status']);

                if (!in_array($new_status, $allowed_statuses, true)) {
                    echo '<div class="notice notice-error is-dismissible"><p>Invalid settlement status.</p></div>';
                } else {
                    $payment_date = sanitize_text_field($_POST['insurer_payment_date'] ?? '');
                    if ($new_status === 'paid' && $payment_date === '') {
                        $payment_date = current_time('mysql', 1);
                    }

                    $wpdb->update($policy_table, [
                        'insurer_payment_status' => $new_status,
                        'insurer_payment_reference' => sanitize_text_field($_POST['insurer_payment_reference'] ?? ''),
                        'insurer_payment_date' => $payment_date ?: null,
                        'insurer_payment_note' => sanitize_textarea_field($_POST['insurer_payment_note'] ?? ''),
                        'updated_at' => current_time('mysql', 1),
                    ], ['id' => $sale_id]);

                    echo '<div class="notice notice-success is-dismissible"><p>Insurer settlement updated for Sale #' . esc_html($sale_id) . '.</p></div>';
                }
            }
        }

        $sales = $wpdb->get_results("
            SELECT s.*, COALESCE(a.name, a.agency_name) AS agency_display_name
            FROM $policy_table s
            LEFT JOIN $agencies_table a ON s.agency_id = a.id
            WHERE s.payment_status = 'confirmed'
            ORDER BY FIELD(COALESCE(s.insurer_payment_status, 'due'), 'due', 'failed', 'not_due', 'paid'), s.updated_at DESC
            LIMIT 500
        ");

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Manual Insurer Settlements</h1>';
        echo '<p>Track manual transfers from TIC-Kenya to insurers. Customer payment collection remains recorded on each policy sale.</p>';
        echo '<hr class="wp-header-end">';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Sale / Date</th><th>Agency</th><th>Client / Policy</th><th>Client Paid</th><th>Amount to Insurer</th><th>Customer Payment Ref</th><th>Insurer Settlement</th></tr></thead>';
        echo '<tbody>';

        if (empty($sales)) {
            echo '<tr><td colspan="7">No confirmed sales are ready for insurer settlement.</td></tr>';
        } else {
            foreach ($sales as $sale) {
                $status = $sale->insurer_payment_status ?: 'due';
                $status_labels = [
                    'not_due' => 'Not Due',
                    'due' => 'Due to Insurer',
                    'paid' => 'Paid to Insurer',
                    'failed' => 'Issue / Failed',
                ];
                $status_color = $status === 'paid' ? 'green' : ($status === 'failed' ? 'red' : ($status === 'due' ? 'orange' : 'gray'));

                echo '<tr>';
                echo '<td>#' . esc_html($sale->id) . '<br><small>' . esc_html($sale->updated_at) . '</small></td>';
                echo '<td>' . esc_html($sale->agency_display_name ?: 'Direct/System') . '</td>';
                echo '<td>' . esc_html($sale->insured_names) . '<br><small>' . esc_html($sale->policy_number ?: 'Draft #' . $sale->id) . '</small></td>';
                echo '<td>KES ' . esc_html(number_format(floatval($sale->amount_paid), 2)) . '</td>';
                echo '<td><strong>KES ' . esc_html(number_format(floatval($sale->net_to_insurer), 2)) . '</strong></td>';
                echo '<td>' . esc_html($sale->payment_reference ?: '-') . '</td>';
                echo '<td>';
                echo '<span style="color:' . esc_attr($status_color) . '; font-weight:bold;">' . esc_html($status_labels[$status] ?? strtoupper($status)) . '</span>';
                if (!empty($sale->insurer_payment_date)) {
                    echo '<br><small>Paid: ' . esc_html($sale->insurer_payment_date) . '</small>';
                }
                if (!empty($sale->insurer_payment_reference)) {
                    echo '<br><small>Ref: ' . esc_html($sale->insurer_payment_reference) . '</small>';
                }

                echo '<form method="post" style="margin-top:8px;display:grid;gap:6px;max-width:280px;">';
                wp_nonce_field('maljani_edit_settlement');
                echo '<input type="hidden" name="maljani_settlement_action" value="update_insurer_settlement">';
                echo '<input type="hidden" name="sale_id" value="' . esc_attr($sale->id) . '">';
                echo '<select name="insurer_payment_status">';
                foreach ($status_labels as $value => $label) {
                    echo '<option value="' . esc_attr($value) . '" ' . selected($status, $value, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                echo '<input type="text" name="insurer_payment_reference" value="' . esc_attr($sale->insurer_payment_reference ?? '') . '" placeholder="Bank/M-Pesa reference">';
                echo '<input type="datetime-local" name="insurer_payment_date" value="' . esc_attr(!empty($sale->insurer_payment_date) ? date('Y-m-d\TH:i', strtotime($sale->insurer_payment_date)) : '') . '">';
                echo '<textarea name="insurer_payment_note" rows="2" placeholder="Optional note">' . esc_textarea($sale->insurer_payment_note ?? '') . '</textarea>';
                echo '<button type="submit" class="button button-small">Update Settlement</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }
}
