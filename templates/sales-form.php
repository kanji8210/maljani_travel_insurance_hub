<?php
// filepath: c:\xampp\htdocs\wordpress\wp-content\plugins\maljani_travel_insurance_hub\templates\sales-form.php

get_header();

$current_user = wp_get_current_user();
$user_role = ( $current_user->exists() ) ? $current_user->roles[0] : '';
$is_agent = ($user_role === 'insurer');
$is_client = ($user_role === 'insured');

// Récupère les infos passées en GET
$policy_id = isset($_GET['policy_id']) ? intval($_GET['policy_id']) : 0;
$policy = $policy_id ? get_post($policy_id) : null;
$region = $policy ? get_post_meta($policy_id, '_policy_region', true) : '';
$policy_title = $policy ? get_the_title($policy_id) : '';
$premium = isset($_GET['premium']) ? sanitize_text_field($_GET['premium']) : '';
$days = isset($_GET['days']) ? intval($_GET['days']) : '';
$departure = isset($_GET['departure']) ? sanitize_text_field($_GET['departure']) : '';
$return = isset($_GET['return']) ? sanitize_text_field($_GET['return']) : '';

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
    <form method="post" class="maljani-sales-form" autocomplete="off">
        <input type="hidden" name="policy_id" value="<?php echo esc_attr($policy_id); ?>">
        <input type="hidden" name="region" value="<?php echo esc_attr($region); ?>">
        <input type="hidden" name="premium" value="<?php echo esc_attr($premium); ?>">
        <input type="hidden" name="days" value="<?php echo esc_attr($days); ?>">

        <div class="maljani-sales-summary">
            <p><strong>Policy:</strong> <?php echo esc_html($policy_title); ?></p>
            <p><strong>Region:</strong> <?php echo esc_html($region); ?></p>
            <p><strong>Premium:</strong> <?php echo esc_html($premium); ?></p>
        </div>

        <?php if ($is_agent || $is_client): ?>
            <div class="maljani-form-group">
                <label>
                    <input type="checkbox" name="buying_for_someone_else" id="buying_for_someone_else" value="1">
                    I am buying for someone else
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
            <input type="date" name="insured_dob" placeholder="Date of birth" value="<?php echo esc_attr($client_data['dob']); ?>" required>
            <input type="text" name="passport_number" placeholder="Passport number" value="<?php echo esc_attr($client_data['passport']); ?>" required>
            <input type="text" name="national_id" placeholder="National ID or PIN number" value="<?php echo esc_attr($client_data['national_id']); ?>" required>
            <input type="text" name="insured_phone" placeholder="Phone number" value="<?php echo esc_attr($client_data['phone']); ?>" required>
            <input type="email" name="insured_email" placeholder="Email address" value="<?php echo esc_attr($client_data['email']); ?>" required>
            <input type="text" name="insured_address" placeholder="Residential address" value="<?php echo esc_attr($client_data['address']); ?>" required>
            <input type="text" name="country_of_origin" placeholder="Country of origin" value="<?php echo esc_attr($client_data['country']); ?>" required>
        </div>

        <button type="submit" class="maljani-sales-btn">
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

    // Affichage des champs selon "buying for someone else"
    const checkbox = document.getElementById('buying_for_someone_else');
    if(checkbox) {
        checkbox.addEventListener('change', function() {
            const insuredFields = document.getElementById('insured-fields');
            if(this.checked) {
                // Vide les champs si achat pour autrui
                insuredFields.querySelectorAll('input').forEach(input => input.value = '');
            } else {
                // Préremplit si client connecté
                <?php if ($is_client): ?>
                insuredFields.querySelector('input[name="insured_names"]').value = "<?php echo esc_js($client_data['full_name']); ?>";
                insuredFields.querySelector('input[name="insured_dob"]').value = "<?php echo esc_js($client_data['dob']); ?>";
                insuredFields.querySelector('input[name="passport_number"]').value = "<?php echo esc_js($client_data['passport']); ?>";
                insuredFields.querySelector('input[name="national_id"]').value = "<?php echo esc_js($client_data['national_id']); ?>";
                insuredFields.querySelector('input[name="insured_phone"]').value = "<?php echo esc_js($client_data['phone']); ?>";
                insuredFields.querySelector('input[name="insured_email"]').value = "<?php echo esc_js($client_data['email']); ?>";
                insuredFields.querySelector('input[name="insured_address"]').value = "<?php echo esc_js($client_data['address']); ?>";
                insuredFields.querySelector('input[name="country_of_origin"]').value = "<?php echo esc_js($client_data['country']); ?>";
                <?php endif; ?>
            }
        });
    }
});
</script>

<?php get_footer(); ?>