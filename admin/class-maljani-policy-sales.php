<?php
class Maljani_Policy_Sales_Admin {
    public function __construct() {
        add_action('admin_init', [$this, 'handle_actions']);
    }

    public function handle_actions() {
        // Action : Archiver une vente
        if (isset($_GET['action'], $_GET['sale_id']) && $_GET['action'] === 'archive_policy' && current_user_can('manage_options')) {
            global $wpdb;
            $table = $wpdb->prefix . 'policy_sale';
            $wpdb->update($table, ['policy_status' => 'archived'], ['id' => intval($_GET['sale_id'])]);
            wp_redirect(remove_query_arg(['action', 'sale_id']));
            exit;
        }
        // Action : Générer le document (à adapter selon ta logique)
        if (isset($_GET['action'], $_GET['sale_id']) && $_GET['action'] === 'generate_doc' && current_user_can('manage_options')) {
            // Ici tu ajoutes ta logique de génération de document PDF ou autre
            // Exemple : maljani_generate_policy_doc(intval($_GET['sale_id']));
            wp_redirect(remove_query_arg(['action', 'sale_id']));
            exit;
        }
    }

    public static function render_sales_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';

        // Pagination simple
        $per_page = 20;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $per_page;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $sales = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset));

        echo '<h2>Policy Sales</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>
            <th>ID</th>
            <th>Policy</th>
            <th>Client</th>
            <th>Dates</th>
            <th>Premium</th>
            <th>Amount Paid</th>
            <th>Payment Status</th>
            <th>Policy Status</th>
            <th>Terms</th>
            <th>Agent</th>
            <th>Actions</th>
        </tr></thead><tbody>';

        foreach ($sales as $sale) {
            $policy_title = get_the_title($sale->policy_id);
            echo '<tr>';
            echo '<td>' . esc_html($sale->id) . '</td>';
            echo '<td>' . esc_html($policy_title) . '</td>';
            echo '<td>' . esc_html($sale->insured_names) . '<br><small>' . esc_html($sale->insured_email) . '</small></td>';
            echo '<td>' . esc_html($sale->departure) . ' → ' . esc_html($sale->return) . '<br>' . esc_html($sale->days) . ' days</td>';
            echo '<td>' . esc_html($sale->premium) . '</td>';
            echo '<td>' . esc_html($sale->amount_paid) . '</td>';
            echo '<td>' . esc_html($sale->payment_status) . '</td>';
            echo '<td>' . esc_html($sale->policy_status) . '</td>';
            echo '<td style="max-width:180px;overflow:auto;">' . esc_html(wp_trim_words($sale->terms, 15, '...')) . '</td>';
            echo '<td>' . esc_html($sale->agent_name) . '</td>';
            echo '<td>
                <a href="' . esc_url(add_query_arg(['action' => 'edit_policy', 'sale_id' => $sale->id])) . '">Edit</a> | 
                <a href="' . esc_url(add_query_arg(['action' => 'generate_doc', 'sale_id' => $sale->id])) . '">Generate Doc</a> | 
                <a href="' . esc_url(add_query_arg(['action' => 'archive_policy', 'sale_id' => $sale->id])) . '" onclick="return confirm(\'Archive this sale?\')">Archive</a>
            </td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        // Pagination
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == $paged) {
                    echo '<span class="tablenav-page-num current">' . $i . '</span> ';
                } else {
                    echo '<a class="tablenav-page-num" href="' . esc_url(add_query_arg('paged', $i)) . '">' . $i . '</a> ';
                }
            }
            echo '</div></div>';
        }

        // Edition inline (simple)
        if (isset($_GET['action'], $_GET['sale_id']) && $_GET['action'] === 'edit_policy') {
            $sale_id = intval($_GET['sale_id']);
            $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $sale_id));
            if ($sale) {
                echo '<h3>Edit Policy Sale #' . esc_html($sale->id) . '</h3>';
                echo '<form method="post">';
                wp_nonce_field('edit_policy_sale_' . $sale->id);
                echo '<input type="hidden" name="sale_id" value="' . esc_attr($sale->id) . '">';
                echo '<table class="form-table">';
                echo '<tr><th>Insured Name</th><td><input type="text" name="insured_names" value="' . esc_attr($sale->insured_names) . '"></td></tr>';
                echo '<tr><th>Email</th><td><input type="email" name="insured_email" value="' . esc_attr($sale->insured_email) . '"></td></tr>';
                echo '<tr><th>Phone</th><td><input type="text" name="insured_phone" value="' . esc_attr($sale->insured_phone) . '"></td></tr>';
                echo '<tr><th>Policy Status</th><td>
                    <select name="policy_status">
                        <option value="unconfirmed" ' . selected($sale->policy_status, 'unconfirmed', false) . '>Unconfirmed</option>
                        <option value="confirmed" ' . selected($sale->policy_status, 'confirmed', false) . '>Confirmed</option>
                        <option value="approved" ' . selected($sale->policy_status, 'approved', false) . '>Approved</option>
                        <option value="active" ' . selected($sale->policy_status, 'active', false) . '>Active</option>
                        <option value="claimed" ' . selected($sale->policy_status, 'claimed', false) . '>Claimed</option>
                        <option value="expired" ' . selected($sale->policy_status, 'expired', false) . '>Expired</option>
                        <option value="archived" ' . selected($sale->policy_status, 'archived', false) . '>Archived</option>
                    </select>
                </td></tr>';
                echo '</table>';
                echo '<p><input type="submit" name="save_policy_sale" class="button-primary" value="Save"></p>';
                echo '</form>';
            }
        }

        // Traitement de l'édition
        if (isset($_POST['save_policy_sale'], $_POST['sale_id']) && check_admin_referer('edit_policy_sale_' . intval($_POST['sale_id']))) {
            $wpdb->update($table, [
                'insured_names' => sanitize_text_field($_POST['insured_names']),
                'insured_email' => sanitize_email($_POST['insured_email']),
                'insured_phone' => sanitize_text_field($_POST['insured_phone']),
                'policy_status' => sanitize_text_field($_POST['policy_status']),
            ], ['id' => intval($_POST['sale_id'])]);
            echo '<div class="updated notice"><p>Sale updated.</p></div>';
        }
    }
}
new Maljani_Policy_Sales_Admin();