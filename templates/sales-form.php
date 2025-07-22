<?php
// templates\sales-form.php

// Traitement du formulaire
if (isset($_POST['maljani_submit_sales'])) {
    // Inclure le fichier qui contient la classe Maljani_Sales_Page
    if (file_exists(plugin_dir_path(__FILE__) . '../includes/class-maljani-sales-page.php')) {
        include_once plugin_dir_path(__FILE__) . '../includes/class-maljani-sales-page.php';
        
        // Si la classe existe, créer une instance et appeler handle_form_submission
        if (class_exists('Maljani_Sales_Page')) {
            $sales_page = new Maljani_Sales_Page();
            $sales_page->handle_form_submission();
        }
    }
}

get_header();

$current_user = wp_get_current_user();
$user_role = ( $current_user->exists() ) ? $current_user->roles[0] : '';
$is_client = ($user_role === 'insured');

// Récupère les infos passées en GET
$policy_id = isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0;
$policy = $policy_id ? get_post($policy_id) : null;
$policy_title = $policy ? get_the_title($policy_id) : '';
$departure = isset($_GET['departure']) ? sanitize_text_field($_GET['departure']) : '';
$return = isset($_GET['return']) ? sanitize_text_field($_GET['return']) : '';

// Calcul du nombre de jours
$days = 0;
if ($departure && $return) {
    $d1 = new DateTime($departure);
    $d2 = new DateTime($return);
    $days = $d1 < $d2 ? $d1->diff($d2)->days : 0;
}

// Récupération de la région
$region = '';
if ($policy_id) {
    $regions = get_the_terms($policy_id, 'policy_region');
    if ($regions && !is_wp_error($regions)) {
        $region = $regions[0]->name;
    } else {
        // Si pas de taxonomie, essayer avec le champ meta (pour compatibilité)
        $region = get_post_meta($policy_id, '_policy_region', true);
    }
}

// Calcul du premium
$premium = '';
if ($policy_id && $days > 0) {
    $premiums = get_post_meta($policy_id, '_policy_day_premiums', true);
    if (is_array($premiums)) {
        foreach ($premiums as $row) {
            if ($days >= intval($row['from']) && $days <= intval($row['to'])) {
                $premium = $row['premium'];
                break;
            }
        }
    }
}

// Récupération des détails de paiement de la police
$payment_details = '';
if ($policy_id) {
    $payment_details = get_post_meta($policy_id, '_policy_payment_details', true);
}

// Préremplissage pour client connecté
$client_data = [
    'full_name' => $is_client ? $current_user->display_name : '',
    'dob' => $is_client ? get_user_meta($current_user->ID, 'insured_dob', true) : '',
    'passport' => $is_client ? get_user_meta($current_user->ID, 'passport_number', true) : '',
    'national_id' => $is_client ? get_user_meta($current_user->ID, 'national_id', true) : '',
    'phone' => $is_client ? get_user_meta($current_user->ID, 'phone', true) : '',
    'email' => $is_client ? $current_user->user_email : '',
    'address' => $is_client ? get_user_meta($current_user->ID, 'address', true) : '',
    'country' => $is_client ? get_user_meta($current_user->ID, 'country', true) : '',
];
?>

<div class="maljani-sales-form-container">
    <h2>Get Covered: <?php echo esc_html($policy_title); ?></h2>
    
    <?php if (isset($_GET['sale_success'])): ?>
        <div class="notice notice-success" style="background:#d4edda;color:#155724;padding:12px;border-radius:6px;margin-bottom:16px;">Vente enregistrée avec succès !</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['sale_error'])): ?>
        <div class="notice notice-error" style="background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;margin-bottom:16px;">Une erreur est survenue. Veuillez réessayer.</div>
    <?php endif; ?>
    
    <!-- Étape 1 : Si les dates ou le policy ID manquent -->
    <?php if (!$policy_id || !$departure || !$return || $days <= 0): ?>
        <form method="get" class="maljani-sales-form" autocomplete="off">
            <?php if ($policy_id): ?>
                <input type="hidden" name="policy_id" value="<?php echo esc_attr($policy_id); ?>">
            <?php endif; ?>
            <div class="maljani-form-group">
                <label>Departure date</label>
                <input type="date" name="departure" value="<?php echo esc_attr($departure); ?>" required>
            </div>
            <div class="maljani-form-group">
                <label>Return date</label>
                <input type="date" name="return" value="<?php echo esc_attr($return); ?>" required>
            </div>
            <button type="submit" class="maljani-sales-btn">Continue</button>
        </form>
    <?php else: ?>
    <!-- Étape 2 : Formulaire complet -->
    <form method="post" class="maljani-sales-form" autocomplete="off">
        <input type="hidden" name="policy_id" value="<?php echo esc_attr($policy_id); ?>">
        <input type="hidden" name="region" value="<?php echo esc_attr($region); ?>">
        <input type="hidden" name="premium" value="<?php echo esc_attr($premium); ?>">
        <input type="hidden" name="days" value="<?php echo esc_attr($days); ?>">
        <input type="hidden" name="departure" value="<?php echo esc_attr($departure); ?>">
        <input type="hidden" name="return" value="<?php echo esc_attr($return); ?>">

        <div class="maljani-sales-summary">
            <p><strong>Policy:</strong> <?php echo esc_html($policy_title); ?></p>
            <p><strong>Region:</strong> <?php echo esc_html($region); ?></p>
            <p><strong>Premium:</strong> <?php echo esc_html($premium); ?></p>
        </div>

        <?php if ($is_client): ?>
            <div class="maljani-form-group">
                <label>Who are you buying for?</label><br>
                <label>
                    <input type="radio" name="buying_for" value="self" checked>
                    Myself
                </label>
                <label>
                    <input type="radio" name="buying_for" value="other">
                    Someone else
                </label>
            </div>
        <?php endif; ?>

        <!-- Dates de voyage -->
        <div class="maljani-form-group">
            <label>Departure date</label>
            <input type="date" name="departure" id="departure" value="<?php echo esc_attr($departure); ?>" required>
        </div>
        <div class="maljani-form-group">
            <label>Return date</label>
            <input type="date" name="return" id="return" value="<?php echo esc_attr($return); ?>" required>
        </div>
        <div class="maljani-form-group">
            <label>Days covered</label>
            <input type="text" name="days_covered" id="days_covered" value="<?php echo esc_attr($days); ?>" readonly>
        </div>

        <!-- Infos assurés -->
        <div id="insured-fields">
            <input type="text" name="insured_names" placeholder="Full name (as it appears on passport)" value="<?php echo esc_attr($client_data['full_name']); ?>" required>
            <label for="insured_dob">Date of birth</label>
            <input type="date" name="insured_dob" placeholder="Date of birth" value="<?php echo esc_attr($client_data['dob']); ?>" required>
            <input type="text" name="passport_number" placeholder="Passport number" value="<?php echo esc_attr($client_data['passport']); ?>" required>
            <input type="text" name="national_id" placeholder="National ID or PIN number" value="<?php echo esc_attr($client_data['national_id']); ?>" required>
            <input type="text" name="insured_phone" placeholder="Phone number" value="<?php echo esc_attr($client_data['phone']); ?>" required>
            <input type="email" name="insured_email" placeholder="Email address" value="<?php echo esc_attr($client_data['email']); ?>" required>
            <input type="text" name="insured_address" placeholder="Residential address" value="<?php echo esc_attr($client_data['address']); ?>" required>
            <input type="text" name="country_of_origin" placeholder="Country of origin" value="<?php echo esc_attr($client_data['country']); ?>" required>
        </div>

        <!-- Détails de paiement de la police (en lecture seule) -->
        <?php if ($payment_details): ?>
        <div class="maljani-form-group maljani-payment-details">
            <label><strong>💳 Payment Instructions</strong></label>
            <div class="payment-details-content">
                <?php echo esc_html($payment_details); ?>
            </div>
            <p class="payment-instructions-note">
                <em>ℹ️ Please follow the payment instructions above and enter the reference number below.</em>
            </p>
        </div>
        <?php endif; ?>

        <!-- Référence de paiement -->
        <div class="maljani-form-group">
            <label>Payment Reference <span style="color: red;">*</span></label>
            <input type="text" name="payment_reference" placeholder="Enter your payment reference/transaction ID" required>
            <p style="font-size: 12px; color: #6c757d; margin-top: 5px;">
                Enter the transaction reference from your payment as proof of payment.
            </p>
        </div>

        <button type="submit" name="maljani_submit_sales" class="maljani-sales-btn">
            <span class="dashicons dashicons-yes"></span>
            Confirm & Get Covered
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calcul automatique du nombre de jours
    function updateDays() {
        const dep = document.getElementById('departure').value;
        const ret = document.getElementById('return').value;
        const daysField = document.getElementById('days_covered');
        if(dep && ret) {
            const d1 = new Date(dep);
            const d2 = new Date(ret);
            const diff = Math.ceil((d2 - d1) / (1000*60*60*24));
            daysField.value = diff > 0 ? diff : '';
        }
    }
    document.getElementById('departure').addEventListener('change', updateDays);
    document.getElementById('return').addEventListener('change', updateDays);

    // Gestion du choix "pour moi" ou "pour quelqu'un d'autre"
    <?php if ($is_client): ?>
    const insuredFields = document.getElementById('insured-fields');
    const inputs = insuredFields.querySelectorAll('input');
    const clientData = {
        'insured_names': "<?php echo esc_js($client_data['full_name']); ?>",
        'insured_dob': "<?php echo esc_js($client_data['dob']); ?>",
        'passport_number': "<?php echo esc_js($client_data['passport']); ?>",
        'national_id': "<?php echo esc_js($client_data['national_id']); ?>",
        'insured_phone': "<?php echo esc_js($client_data['phone']); ?>",
        'insured_email': "<?php echo esc_js($client_data['email']); ?>",
        'insured_address': "<?php echo esc_js($client_data['address']); ?>",
        'country_of_origin': "<?php echo esc_js($client_data['country']); ?>"
    };

    function setFieldsForSelf() {
        inputs.forEach(input => {
            if (clientData[input.name] !== undefined) {
                input.value = clientData[input.name];
                input.readOnly = true;
            }
        });
    }
    function setFieldsForOther() {
        inputs.forEach(input => {
            input.value = '';
            input.readOnly = false;
        });
    }

    document.querySelectorAll('input[name="buying_for"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'self') {
                setFieldsForSelf();
            } else {
                setFieldsForOther();
            }
        });
    });

    // Initialisation
    setFieldsForSelf();
    <?php endif; ?>
});
</script>

<?php endif; // Fermeture du else (condition complète du formulaire) ?>

<?php get_footer(); ?>