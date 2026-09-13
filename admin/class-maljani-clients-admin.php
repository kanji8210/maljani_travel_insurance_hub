<?php

class Maljani_Clients_Admin {

    public static function render_page() {
        if (!current_user_can('edit_maljani_policies')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $clients_table = $wpdb->prefix . 'maljani_clients';
        $agencies_table = $wpdb->prefix . 'maljani_agencies';
        $sales_table = $wpdb->prefix . 'policy_sale';

        $ledger_clients = $wpdb->get_results("
            SELECT c.*, COALESCE(NULLIF(a.name, ''), a.agency_name) AS agency_name
            FROM $clients_table c 
            LEFT JOIN $agencies_table a ON c.agency_id = a.id 
            ORDER BY c.created_at DESC
        ");
        $sales = $wpdb->get_results("
            SELECT s.id AS sale_id, s.insured_names, s.insured_email, s.insured_phone,
                   s.passport_number, s.national_id, s.agency_id, s.amount_paid,
                   s.payment_status, s.policy_status, s.created_at,
                   COALESCE(NULLIF(a.name, ''), a.agency_name) AS agency_name
            FROM $sales_table s
            LEFT JOIN $agencies_table a ON s.agency_id = a.id
            ORDER BY s.created_at DESC
        ");

        $clients = [];
        foreach ($ledger_clients as $client) {
            $identity = self::client_identity($client->email, $client->passport_number, $client->phone, 'client-' . $client->id);
            $clients[$identity] = (object) [
                'name'            => trim($client->first_name . ' ' . $client->last_name),
                'email'           => $client->email,
                'phone'           => $client->phone,
                'passport_number' => $client->passport_number,
                'national_id'     => $client->national_id,
                'agency_name'     => $client->agency_name ?: 'Direct',
                'policy_count'    => 0,
                'total_premium'   => 0,
                'latest_status'   => 'No policy yet',
                'latest_date'     => $client->created_at,
            ];
        }
        foreach ($sales as $sale) {
            $identity = self::client_identity($sale->insured_email, $sale->passport_number, $sale->insured_phone, 'sale-' . $sale->sale_id);
            if (!isset($clients[$identity])) {
                $clients[$identity] = (object) [
                    'name'            => $sale->insured_names,
                    'email'           => $sale->insured_email,
                    'phone'           => $sale->insured_phone,
                    'passport_number' => $sale->passport_number,
                    'national_id'     => $sale->national_id,
                    'agency_name'     => $sale->agency_name ?: 'Direct',
                    'policy_count'    => 0,
                    'total_premium'   => 0,
                    'latest_status'   => $sale->policy_status,
                    'latest_date'     => $sale->created_at,
                ];
            }
            $clients[$identity]->policy_count++;
            $clients[$identity]->total_premium += (float) $sale->amount_paid;
            if (empty($clients[$identity]->latest_date) || $sale->created_at >= $clients[$identity]->latest_date) {
                $clients[$identity]->latest_status = $sale->policy_status;
                $clients[$identity]->latest_date = $sale->created_at;
                $clients[$identity]->agency_name = $sale->agency_name ?: 'Direct';
            }
        }
        $clients = array_values($clients);
        usort($clients, static function ($left, $right) {
            return strcmp((string) $right->latest_date, (string) $left->latest_date);
        });

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Manage Clients</h1>';
        echo '<hr class="wp-header-end">';
        echo '<p>' . esc_html(count($clients)) . ' unique clients from CRM records and policy sales.</p>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Name</th><th>Email / Phone</th><th>Passport / ID</th><th>Agency</th><th>Policies</th><th>Latest Status</th></tr></thead>';
        echo '<tbody>';

        if (empty($clients)) {
            echo '<tr><td colspan="6">No clients found in CRM records or policy sales.</td></tr>';
        } else {
            foreach ($clients as $c) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($c->name ?: 'Unnamed client') . '</strong></td>';
                echo '<td>' . esc_html($c->email) . '<br/>' . esc_html($c->phone) . '</td>';
                echo '<td>' . esc_html($c->passport_number ?: $c->national_id ?: '—') . '</td>';
                echo '<td>' . esc_html($c->agency_name ?: 'Direct') . '</td>';
                echo '<td><strong>' . intval($c->policy_count) . '</strong><br><small>KES ' . esc_html(number_format($c->total_premium, 2)) . '</small></td>';
                echo '<td>' . esc_html(ucwords(str_replace('_', ' ', $c->latest_status))) . '<br><small>' . esc_html($c->latest_date ? date_i18n(get_option('date_format'), strtotime($c->latest_date)) : '') . '</small></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    private static function client_identity($email, $passport, $phone, $fallback) {
        $email = strtolower(trim((string) $email));
        if ($email !== '') return 'email:' . $email;

        $passport = strtoupper(preg_replace('/\s+/', '', (string) $passport));
        if ($passport !== '') return 'passport:' . $passport;

        $phone = preg_replace('/\D+/', '', (string) $phone);
        return $phone !== '' ? 'phone:' . $phone : $fallback;
    }
}
