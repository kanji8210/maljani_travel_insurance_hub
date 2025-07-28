<?php
class Maljani_Policy_Sales_Admin {
    public function __construct() {
        add_action('admin_init', [$this, 'handle_actions']);
        add_action('wp_ajax_maljani_get_policy_premium', [$this, 'ajax_get_policy_premium']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_style']); // On ne charge plus enqueue_scripts ici
    }

    //enqueue style uniquement
    public function enqueue_style() {
        try {
            wp_enqueue_style(
                'maljani-admin-style',
                plugin_dir_url(__FILE__) . 'css/maljani-admin.css',
                array(),
                null
            );
            
            // Rendre la variable ajaxurl disponible en JavaScript
            wp_localize_script('jquery', 'ajax_object', array(
                'ajax_url' => admin_url('admin-ajax.php')
            ));
            
            // Ajouter ajaxurl globalement pour le JavaScript inline
            echo '<script type="text/javascript">var ajaxurl = "' . admin_url('admin-ajax.php') . '";</script>';
            
        } catch (Exception $e) {
            error_log('Error registering admin style: ' . $e->getMessage());
        }
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
            // Exemple : maljani_generate_policy_doc(intval($_GET['sale_id']));
            wp_redirect(remove_query_arg(['action', 'sale_id']));
            exit;
        }
        
    }

    public function ajax_get_policy_premium() {
        $policy_id = intval($_POST['policy_id']);
        $days = intval($_POST['days']);
        $premiums = get_post_meta($policy_id, '_policy_day_premiums', true);
      $premium = '';
        if (is_array($premiums)) {
            foreach ($premiums as $row) {
                if ($days >= $row['from'] && $days <= $row['to']) {
                    $premium = $row['premium'];
                    break;
                }
            }
        }
    
        wp_send_json_success($premium);
    }


    public static function render_sales_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'policy_sale';

        // Traitement de la mise à jour
        if (isset($_POST['update_policy_sale'], $_POST['sale_id']) && check_admin_referer('update_policy_sale_' . intval($_POST['sale_id']))) {
            $wpdb->update($table, [
                'policy_id' => intval($_POST['policy_id']),
                'policy_number' => sanitize_text_field($_POST['policy_number']),
                'insured_names' => sanitize_text_field($_POST['insured_names']),
                'insured_email' => sanitize_email($_POST['insured_email']),
                'insured_phone' => sanitize_text_field($_POST['insured_phone']),
                'departure' => sanitize_text_field($_POST['departure']),
                'return' => sanitize_text_field($_POST['return']),
                'premium' => sanitize_text_field($_POST['premium']),
                'agent_id' => intval($_POST['agent_id']),
                'terms' => sanitize_textarea_field($_POST['terms']),
            ], ['id' => intval($_POST['sale_id'])]);
            echo '<div class="updated notice"><p>Sale updated.</p></div>';
        }

        // Pagination simple
        $per_page = 20;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $per_page;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $sales = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset));

        // Récupère policies et agents
        $policies = get_posts(['post_type' => 'policy', 'numberposts' => -1]);
        $agents = get_users(['role' => 'agent']);

    


echo '<table class="widefat striped maljani-sales-table">';
echo '<thead><tr>
    <th>ID</th>
    <th>Client</th>
    <th>Policy</th>
    <th>Policy Number</th> <!-- Ajouté -->
    <th>Dates</th>
    <th>Premium</th>
    <th>Agent</th>
    <th>Policy Status</th>
    <th>Payment Status</th>
    <th class="terms-cell">Terms</th>
    <th class="actions-cell">Actions</th>
</tr></thead><tbody>';

foreach ($sales as $sale) {
    $policy_title = '';
    foreach ($policies as $policy) {
        if ($policy->ID == $sale->policy_id) $policy_title = $policy->post_title;
    }
    $agent_name = '';
    foreach ($agents as $agent) {
        if ($agent->ID == $sale->agent_id) $agent_name = $agent->display_name;
    }
    $terms_short = wp_trim_words($sale->terms, 10, '...');
    $policy_status = $sale->policy_status ?: 'unconfirmed';
    $payment_status = $sale->payment_status ?: 'unconfirmed';

    // Ligne affichage (lecture seule)
    echo '<tr id="sale-row-' . esc_attr($sale->id) . '" class="sale-row-view">';
    echo '<td>' . esc_html($sale->id) . '</td>';
    echo '<td>' . esc_html($sale->insured_names) . '<br>' . esc_html($sale->insured_email) . '<br>' . esc_html($sale->insured_phone) . '</td>';
    echo '<td>' . esc_html($policy_title) . '</td>';
    echo '<td>' . esc_html($sale->policy_number) . '</td>'; // Affichage du numéro de police
    echo '<td>' . esc_html($sale->departure) . ' → ' . esc_html($sale->return) . '</td>';
    echo '<td>' . esc_html($sale->premium) . '</td>';
    echo '<td>' . esc_html($agent_name) . '</td>';
    echo '<td>' . esc_html(ucfirst($policy_status)) . '</td>';
    echo '<td>' . esc_html(ucfirst($payment_status)) . '</td>';
    echo '<td class="terms-cell"><span class="terms-short" style="cursor:pointer;color:#0073aa;" data-full="' . esc_attr($sale->terms) . '">' . esc_html($terms_short) . ' <span style="color:#888;">(show all)</span></span></td>';
    echo '<td class="actions-cell">
        <button type="button" class="button edit-sale-btn" data-sale="' . esc_attr($sale->id) . '">Edit</button>
        <a href="' . plugins_url('includes/generate-policy-pdf.php', dirname(__FILE__)) . '?sale_id=' . esc_attr($sale->id) . '" target="_blank">Generate PDF</a> | 
        <a href="' . esc_url(add_query_arg(['action' => 'archive_policy', 'sale_id' => $sale->id])) . '" onclick="return confirm(\'Archive this sale?\')">Archive</a>
    </td>';
    echo '</tr>';

    // Ligne édition (cachée par défaut)
    echo '<tr id="sale-edit-row-' . esc_attr($sale->id) . '" class="sale-row-edit" style="display:none;background:#f9f9f9;">';
    echo '<form method="post">';
    wp_nonce_field('update_policy_sale_' . $sale->id);
    echo '<td>' . esc_html($sale->id) . '<input type="hidden" name="sale_id" value="' . esc_attr($sale->id) . '"></td>';
    echo '<td>
        <input type="text" name="insured_names" value="' . esc_attr($sale->insured_names) . '" style="width:100px;" placeholder="Name"><br>
        <input type="email" name="insured_email" value="' . esc_attr($sale->insured_email) . '" style="width:120px;" placeholder="Email"><br>
        <input type="text" name="insured_phone" value="' . esc_attr($sale->insured_phone) . '" style="width:100px;" placeholder="Phone">
    </td>';
    echo '<td><select name="policy_id" class="policy-select" data-sale="' . esc_attr($sale->id) . '">';
    foreach ($policies as $policy) {
        echo '<option value="' . esc_attr($policy->ID) . '" ' . selected($sale->policy_id, $policy->ID, false) . '>' . esc_html($policy->post_title) . '</option>';
    }
    echo '</select></td>';
    // Champ Policy Number
    echo '<td><input type="text" name="policy_number" value="' . esc_attr($sale->policy_number) . '" style="width:120px;"></td>';
    echo '<td>
        <input type="date" name="departure" class="date-dep" data-sale="' . esc_attr($sale->id) . '" value="' . esc_attr($sale->departure) . '" style="width:110px;">
        →
        <input type="date" name="return" class="date-ret" data-sale="' . esc_attr($sale->id) . '" value="' . esc_attr($sale->return) . '" style="width:110px;">
    </td>';
    // Champ premium en lecture seule avec indicateur de recalcul automatique
    echo '<td><input type="text" name="premium" class="premium-field" data-sale="' . esc_attr($sale->id) . '" value="' . esc_attr($sale->premium) . '" style="width:70px;" readonly title="Premium will be auto-calculated when policy or dates change"><br><small style="color:#666;">Auto-calc</small></td>';
    echo '<td><select name="agent_id">';
    echo '<option value="">--</option>';
    foreach ($agents as $agent) {
        echo '<option value="' . esc_attr($agent->ID) . '" ' . selected($sale->agent_id, $agent->ID, false) . '>' . esc_html($agent->display_name) . '</option>';
    }
    echo '</select></td>';
    // Policy Status dropdown
    echo '<td>
        <select name="policy_status">
            <option value="unconfirmed" ' . selected($policy_status, 'unconfirmed', false) . '>Unconfirmed</option>
            <option value="active" ' . selected($policy_status, 'active', false) . '>Active</option>
            <option value="archived" ' . selected($policy_status, 'archived', false) . '>Archived</option>
            <option value="cancelled" ' . selected($policy_status, 'cancelled', false) . '>Cancelled</option>
        </select>
    </td>';
    // Payment Status dropdown
    echo '<td>
        <select name="payment_status">
            <option value="unconfirmed" ' . selected($payment_status, 'unconfirmed', false) . '>Unconfirmed</option>
            <option value="pending" ' . selected($payment_status, 'pending', false) . '>Pending</option>
            <option value="paid" ' . selected($payment_status, 'paid', false) . '>Paid</option>
            <option value="failed" ' . selected($payment_status, 'failed', false) . '>Failed</option>
        </select>
    </td>';
    echo '<td>
        <textarea name="terms" class="terms-field" style="width:150px;height:40px;">' . esc_textarea($sale->terms) . '</textarea>
    </td>';
    echo '<td class="actions-cell">
        <button type="submit" name="update_policy_sale" class="button button-primary">Update</button>
        <button type="button" class="button cancel-edit-sale-btn" data-sale="' . esc_attr($sale->id) . '">Cancel</button>
    </td>';
    echo '</form>';
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
        // Ajout du JS inline pour l'édition
        echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // Afficher la ligne d\'édition au clic sur Edit
    document.querySelectorAll(".edit-sale-btn").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var saleId = this.getAttribute("data-sale");
            document.querySelectorAll(".sale-row-view").forEach(function(row) {
                row.style.display = "";
            });
            document.querySelectorAll(".sale-row-edit").forEach(function(row) {
                row.style.display = "none";
            });
            document.getElementById("sale-row-" + saleId).style.display = "none";
            document.getElementById("sale-edit-row-" + saleId).style.display = "";
        });
    });
    
    // Annuler l\'édition
    document.querySelectorAll(".cancel-edit-sale-btn").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var saleId = this.getAttribute("data-sale");
            document.getElementById("sale-edit-row-" + saleId).style.display = "none";
            document.getElementById("sale-row-" + saleId).style.display = "";
        });
    });

    // Fonction pour calculer les jours entre deux dates
    function calculateDays(startDate, endDate) {
        if (!startDate || !endDate) return 0;
        var start = new Date(startDate);
        var end = new Date(endDate);
        var timeDiff = end.getTime() - start.getTime();
        var daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
        return daysDiff > 0 ? daysDiff : 0;
    }

    // Fonction pour recalculer la prime
    function recalculatePremium(saleId) {
        var policySelect = document.querySelector(`.policy-select[data-sale="${saleId}"]`);
        var dateDepInput = document.querySelector(`.date-dep[data-sale="${saleId}"]`);
        var dateRetInput = document.querySelector(`.date-ret[data-sale="${saleId}"]`);
        var premiumField = document.querySelector(`.premium-field[data-sale="${saleId}"]`);

        if (!policySelect || !dateDepInput || !dateRetInput || !premiumField) return;

        var policyId = policySelect.value;
        var startDate = dateDepInput.value;
        var endDate = dateRetInput.value;

        if (!policyId || !startDate || !endDate) {
            premiumField.value = "";
            return;
        }

        var days = calculateDays(startDate, endDate);
        if (days <= 0) {
            premiumField.value = "";
            return;
        }

        // Afficher un indicateur de chargement
        premiumField.value = "Calculating...";
        premiumField.style.backgroundColor = "#fff3cd";

        // Appel AJAX pour récupérer la prime
        var formData = new FormData();
        formData.append("action", "maljani_get_policy_premium");
        formData.append("policy_id", policyId);
        formData.append("days", days);

        fetch(ajaxurl, {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                premiumField.value = data.data;
                premiumField.style.backgroundColor = "#d1edff"; // Bleu clair pour indiquer une mise à jour
                setTimeout(() => {
                    premiumField.style.backgroundColor = "";
                }, 2000);
            } else {
                premiumField.value = "N/A";
                premiumField.style.backgroundColor = "#f8d7da"; // Rouge clair pour erreur
            }
        })
        .catch(error => {
            console.error("Error calculating premium:", error);
            premiumField.value = "Error";
            premiumField.style.backgroundColor = "#f8d7da";
        });
    }

    // Ajouter les événements pour recalcul automatique
    document.querySelectorAll(".policy-select, .date-dep, .date-ret").forEach(function(element) {
        element.addEventListener("change", function() {
            var saleId = this.getAttribute("data-sale");
            if (saleId) {
                // Petit délai pour s\'assurer que la valeur est bien mise à jour
                setTimeout(() => recalculatePremium(saleId), 100);
            }
        });
    });

    // Validation des dates (la date de retour doit être après la date de départ)
    document.querySelectorAll(".date-dep, .date-ret").forEach(function(element) {
        element.addEventListener("change", function() {
            var saleId = this.getAttribute("data-sale");
            var dateDepInput = document.querySelector(`.date-dep[data-sale="${saleId}"]`);
            var dateRetInput = document.querySelector(`.date-ret[data-sale="${saleId}"]`);
            
            if (dateDepInput && dateRetInput && dateDepInput.value && dateRetInput.value) {
                var startDate = new Date(dateDepInput.value);
                var endDate = new Date(dateRetInput.value);
                
                if (endDate <= startDate) {
                    alert("Return date must be after departure date!");
                    if (this.classList.contains("date-ret")) {
                        this.value = "";
                    }
                    return;
                }
            }
        });
    });
});
</script>';
    }
}
new Maljani_Policy_Sales_Admin();

