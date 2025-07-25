<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wordpress/wp-load.php');

$tcpdf_path = dirname(__DIR__) . '/lib/TCPDF-main/tcpdf.php';
if (!file_exists($tcpdf_path)) die('TCPDF not found at: ' . $tcpdf_path);
require_once $tcpdf_path;

if (!current_user_can('manage_options')) wp_die('Not allowed');

$sale_id = isset($_GET['sale_id'])// Écriture du HTML principal
$pdf->writeHTML($html, true, false, true, false, '');

// Si tu as une liste d'assurés, boucle dessus. Sinon, affichfunction build_embassy_letter($policy_number, $start_date, $end_date, $product_name, $destination_area, 
                             $policyholder_name, $passport_number, $sale, $insurer) {
    $letter_html = '
<div class="letter">';.'sale_id']) : 0;
if (!$sale_id) wp_die('No sale ID');

global $wpdb;
$table = $wpdb->prefix . 'policy_sale';
$sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $sale_id));
if (!$sale) wp_die('Sale not found');

// Policy info
$policy_id = $sale->policy_id;
$policy_title = get_the_title($policy_id);
$insurer_id = get_post_meta($policy_id, '_policy_insurer', true);

// Test to ensure insurer ID is found
if (!$insurer_id) {
    wp_die('Error: No insurer assigned to this policy. Please assign an insurer to policy ID: ' . $policy_id);
}

$insurer = '';
$insurer_logo = '';
if ($insurer_id) {
    $insurer = get_post_meta($insurer_id, '_insurer_name', true);
    $insurer_logo = get_post_meta($insurer_id, '_insurer_logo', true);
    
    // Handle both ID and URL formats for logo
    if ($insurer_logo) {
        if (is_numeric($insurer_logo)) {
            $insurer_logo = wp_get_attachment_url($insurer_logo);
        }
        // If it's already a URL, keep it as is
    }
    
    // Additional test to ensure insurer data exists
    if (!$insurer) {
        wp_die('Error: Insurer profile not found or incomplete. Insurer ID: ' . $insurer_id . ' does not have a valid name.');
    }
}
$region_id = get_post_meta($policy_id, '_policy_region', true);
$region = '';
if ($region_id) {
    // Si c'est un ID de terme (taxonomy), récupère le nom du terme
    $term = get_term($region_id);
    if ($term && !is_wp_error($term)) {
        $region = $term->name;
    } else {
        // Sinon, affiche la valeur brute (au cas où c'est déjà un nom)
        $region = $region_id;
    }
}
$coverage = get_post_meta($policy_id, '_policy_cover_details', true);

// Agent
$agent = get_userdata($sale->agent_id);

// Dates et autres champs
$policyholder_name = $sale->insured_names ?: '';
$passport_number   = $sale->passport_number ?? '';
$address          = $sale->insured_address ?? '';
$telephone        = $sale->insured_phone ?: '';
$email            = $sale->insured_email ?: '';
$pin              = $sale->kra_pin ?? '';
$policy_number    = $sale->policy_number ?? '';
$num_passengers   = $sale->num_passengers ?? '';
$start_date       = $sale->departure ?: '';
$end_date         = $sale->return ?: '';
$duration_days    = '';
if ($start_date && $end_date) {
    $d1 = new DateTime($start_date);
    $d2 = new DateTime($end_date);
    $duration_days = $d1 < $d2 ? $d1->diff($d2)->days : '';
}
$product_name     = $policy_title ?: '';
$destination_area = $region ?: '';
$country_of_origin = 'KENYA'; // ou autre logique

// Affichage "vide" si champ manquant
$passport_number = $passport_number ?: '';
$address        = $address ?: '';
$pin            = $pin ?: '';
$policy_number  = $policy_number ?: '';
$num_passengers = $num_passengers ?: '';
$duration_days  = $duration_days ?: '';
$product_name   = $product_name ?: '';
$destination_area = $destination_area ?: '';

// Création du PDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Maljani Insurance');
$pdf->SetTitle('Policy Document');
$pdf->AddPage();

// Initialisation du HTML
$html = '';

// Inclusion des styles depuis le fichier CSS externe
$css_file_path = dirname(__FILE__) . '/css/pdf_generator.css';
$css_content = '';
if (file_exists($css_file_path)) {
    $css_content = file_get_contents($css_file_path);
}

$html .= '<style>' . $css_content . '</style>';

// Header with site logo and insurer logo
$site_logo_url = '';
$site_logo_debug = [];

// Try multiple methods to get site logo
// Method 1: Custom Logo from theme (using wp_get_attachment_image_src)
$custom_logo_id = get_theme_mod('custom_logo');
if ($custom_logo_id) {
    $logo_data = wp_get_attachment_image_src($custom_logo_id, 'full');
    if ($logo_data && isset($logo_data[0])) {
        $site_logo_url = $logo_data[0];
        $site_logo_debug[] = "Custom Logo ID: $custom_logo_id, URL: $site_logo_url (via wp_get_attachment_image_src)";
    }
}

// Method 2: Site Icon (fallback)
if (!$site_logo_url) {
    $site_icon_id = get_option('site_icon');
    if ($site_icon_id) {
        $icon_data = wp_get_attachment_image_src($site_icon_id, 'full');
        if ($icon_data && isset($icon_data[0])) {
            $site_logo_url = $icon_data[0];
            $site_logo_debug[] = "Site Icon ID: $site_icon_id, URL: $site_logo_url (via wp_get_attachment_image_src)";
        }
    }
}

// Method 3: Site icon URL function
if (!$site_logo_url) {
    $site_logo_url = get_site_icon_url(200);
    $site_logo_debug[] = "Site Icon URL function: $site_logo_url";
}

// Method 4: WordPress admin logo (fallback)
if (!$site_logo_url) {
    $site_logo_url = admin_url('images/wordpress-logo.svg');
    $site_logo_debug[] = "WordPress admin logo fallback: $site_logo_url";
}

// Improved insurer logo recovery
$insurer_logo_final = '';
if ($insurer_id) {
    $logo_url = get_post_meta($insurer_id, '_insurer_logo', true);
    if ($logo_url && !empty($logo_url)) {
        $insurer_logo_final = $logo_url;
    }
}

// For debugging purposes (comment out in production)
// error_log("Site Logo Debug: " . implode(" | ", $site_logo_debug));
// error_log("Insurer Logo Debug: " . implode(" | ", $insurer_logo_debug));

// Construction du HTML - Section Header
$html .= build_pdf_header($site_logo_url, $insurer_logo, $insurer);

// Construction du HTML - Contenu principal
$html .= build_policy_content(
    $policyholder_name, $passport_number, $address, $telephone, $email, $pin,
    $policy_number, $num_passengers, $insurer, $start_date, $end_date,
    $duration_days, $product_name, $country_of_origin, $destination_area,
    $region, $sale, $coverage
);

// Construction du HTML - Liste des assurés
$html .= build_insured_list($policyholder_name, $passport_number, $sale, $region, $start_date, $end_date);

// Construction du HTML - Termes et conditions
$html .= build_terms_and_conditions($sale);

// Fonctions pour construire les sections HTML
function build_pdf_header($site_logo_url, $insurer_logo, $insurer) {
    $header_html = '
<table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
        <td class="header-left">';

    if ($site_logo_url && $site_logo_url !== '') {
        $header_html .= '<img src="' . esc_url($site_logo_url) . '" class="site-logo" alt="Site Logo" style="max-height: 60px; max-width: 200px;"><br>';
    } else {
        // Fallback: show site name instead of logo
        $header_html .= '<div style="font-size: 18px; font-weight: bold; color: #333; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">' . get_bloginfo('name') . '</div>';
    }

    $header_html .= '<div class="tagline">Insurance Aggregator</div>
        </td>
        <td class="header-right">';

    if ($insurer_logo && $insurer_logo !== '') {
        $header_html .= '<img src="' . esc_url($insurer_logo) . '" class="insurer-logo" alt="Insurer Logo" style="max-height: 60px; max-width: 200px;"><br>';
    } else {
        // Fallback: show insurer name in styled box if no logo
        $header_html .= '<div style="border: 2px solid #333; padding: 15px; text-align: center; font-weight: bold; background: #f0f0f0;">' . esc_html($insurer ?: 'Insurance Company') . '</div><br>';
    }

    $header_html .= '<div class="insurer-name">' . esc_html($insurer ?: 'Insurance Company') . '</div>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="text-align: center; font-size: 12px; color: #666; padding-top: 5px;">
            ' . get_bloginfo('description') . '
        </td>
    </tr>
</table>
<hr style="border: 1px solid #ddd; margin: 20px 0;">
';
    
    return $header_html;
}

function build_policy_content($policyholder_name, $passport_number, $address, $telephone, $email, $pin,
                               $policy_number, $num_passengers, $insurer, $start_date, $end_date,
                               $duration_days, $product_name, $country_of_origin, $destination_area,
                               $region, $sale, $coverage) {
    $content_html = '
<h1 style="text-align:center;">TRAVEL INSURANCE POLICY</h1>

<!-- Section informations du souscripteur et de la police -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
<tr>
    <!-- Informations personnelles (65%) -->
    <td style="vertical-align:top;width:65%;border:1px solid #000;padding:0;">
        <table cellpadding="0" cellspacing="0" style="width:100%;">
            <tr><td style="padding:3px;"><strong>Policyholder:</strong> ' . $policyholder_name . '</td></tr>
            <tr><td style="padding:3px;"><strong>Passport:</strong> ' . $passport_number . '</td></tr>
            <tr><td style="padding:3px;"><strong>Address:</strong> ' . $address . '</td></tr>
            <tr><td style="padding:3px;"><strong>Telephone:</strong> ' . $telephone . '</td></tr>
            <tr><td style="padding:3px;"><strong>Email:</strong> ' . $email . '</td></tr>
            <tr><td style="padding:3px;"><strong>PIN Number:</strong> ' . $pin . '</td></tr>
        </table>
    </td>
    <td style="width:5px;"></td>
    <!-- Informations de la police (35%) -->
    <td style="vertical-align:top;width:35%;border:1px solid #000;padding:0;">
        <table cellpadding="0" cellspacing="0" style="width:100%;">
            <tr><td style="padding:3px;"><strong>Policy Number:</strong> ' . $policy_number . '</td></tr>
            <tr><td style="padding:3px;"><strong>N. Passengers:</strong> ' . ($num_passengers ? $num_passengers : '1') . '</td></tr>
            <tr><td style="padding:3px;"><strong>Insurer:</strong> ' . ($insurer ?: '___') . '</td></tr>
        </table>
    </td>
</tr>
</table>
<br><br>

<!-- Section détails de la couverture -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
<tr>
    <!-- Période de couverture -->
    <td style="vertical-align:top;width:32.5%;border:1px solid #000;padding:6px;">
        <strong>Coverage Period</strong><br>
        Effective from: ' . ($start_date ?: '___') . '<br>
        Expiry: ' . ($end_date ?: '___') . '
    </td>
    <td style="width:3px;"></td>
    <!-- Détails de la police -->
    <td style="vertical-align:top;width:32.5%;border:1px solid #000;padding:6px;">
        <strong>Policy period:</strong> ' . ($duration_days ?: '___') . ' Day(s)<br>
        <strong>Product:</strong> ' . ($product_name ?: '___') . '<br>
        INDIVIDUAL<br>
        Country of Origin: ' . ($country_of_origin ?: 'KENYA') . '
    </td>
    <td style="width:3px;"></td>
    <!-- Destination et montant -->
    <td style="vertical-align:top;width:32.5%;border:1px solid #000;padding:6px;">
        <strong>Destination:</strong> ' . ($destination_area ?: '___') . '<br>
        <strong>Insurer:</strong> ' . ($insurer ?: '___') . '<br>
        <strong>Region:</strong> ' . ($region ?: '___') . '<br>
        <strong>Policy Amount</strong><br>
        GROSS PREMIUM ' . (empty($sale->premium) ? '___' : esc_html($sale->premium) . ' USD') . '
    </td>
</tr>
</table>
<br><br>

<!-- Zone de destination -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
<tr>
    <td style="border:1px solid #000;padding:6px;width:100%;">
        <strong>Destination Area:</strong> ' . ($destination_area ?: '___') . '
    </td>
</tr>
</table>
<br><br>

<!-- Détails de la couverture -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
    <tr>
        <td style="border:1px solid #000;padding:6px;width:100%;">
            <h2 style="text-align:center;margin:0;">Schedule of Covers / Coverage details</h2>
            <div style="font-size:12px;">
                ' . (!empty($coverage) ? wpautop($coverage) : 'No coverage details available.') . '
            </div>
        </td>
    </tr>
</table>
<br><br>

<!-- Zone de signatures -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
    <tr>
        <td colspan="3" style="padding:6px 30px 16px 30px;">
            <p style="text-align:center;margin:0; font-size: 10px;">
                By signing this document, the Policyholder expressly accepts the clauses limiting the rights of the Insured included in the attached General Conditions of the Policy. This travel policy can only be changed or cancelled before the start date of the policy period.
            </p>
        </td>
    </tr>
    <tr>
        <!-- Signature du souscripteur -->
        <td style="border:0.5px solid #000;padding:20px 30px 16px 30px;width:48%;text-align:center;">
            <strong>Policyholder Signature</strong><br>
            <span style="display:inline-block;border-bottom:1px dotted #000;width:200px;height:32px;margin:16px 0;"></span><br>
            <span style="display:inline-block;width:200px;text-align:left;">Date: ' . date('Y-m-d') . '</span>
        </td>
        <td style="width:6%"></td>
        <!-- Signature du représentant assureur -->
        <td style="border:0.5px solid #000;padding:20px 30px 16px 30px;width:48%;text-align:center;">
            <strong>Insurer Representative</strong><br>
            <span style="display:inline-block;border-bottom:1px dotted #000;width:200px;height:32px;margin:16px 0;"></span><br>
            <span style="display:inline-block;width:200px;text-align:left;">Date: ' . date('Y-m-d') . '</span>
        </td>
    </tr>
</table>
<br><br>
';
    
    return $content_html;
}

// --- TERMS AND CONDITIONS EN 2 COLONNES ---
// Cette section a été déplacée dans la fonction build_terms_and_conditions()

// === LIST OF INSURED ===
// Cette section a été déplacée dans la fonction build_insured_list()

// Écriture du HTML principal
$pdf->writeHTML($html, true, false, true, false, '');
<table class="insured-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Passport Number</th>
            <th>Date of Birth</th>
            <th>Destination</th>
            <th>Cover Period</th>
        </tr>
    </thead>
    <tbody>
';

// Si tu as une liste d’assurés, boucle dessus. Sinon, affiche l’assuré principal.
if (!empty($insured_list) && is_array($insured_list)) {
    foreach ($insured_list as $insured) {
        $html .= '
        <tr>
            <td>' . esc_html($insured['name']) . '</td>
            <td>' . esc_html($insured['passport']) . '</td>
            <td>' . esc_html($insured['dob']) . '</td>
            <td>' . esc_html($region) . '</td>
            <td>' . esc_html($start_date) . ' - ' . esc_html($end_date) . '</td>
        </tr>';
    }
} else {
    $html .= '
        <tr>
            <td>' . esc_html($policyholder_name) . '</td>
            <td>' . esc_html($passport_number) . '</td>
            <td>' . (!empty($sale->insured_dob) ? esc_html($sale->insured_dob) : '___') . '</td>
            <td>' . esc_html($region) . '</td>
            <td>' . esc_html($start_date) . ' - ' . esc_html($end_date) . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>
<br><br>
';

// Écriture du HTML principal
$pdf->writeHTML($html, true, false, true, false, '');

// Nouvelle page pour la lettre à l'ambassade
$pdf->AddPage();
$letter_html = build_embassy_letter($policy_number, $start_date, $end_date, $product_name, $destination_area, 
                                   $policyholder_name, $passport_number, $sale, $insurer);
$pdf->writeHTML($letter_html, true, false, true, false, '');

$pdf->Output('policy_' . $sale_id . '.pdf', 'I');

// ==========================================
// FONCTIONS POUR ORGANISER LE HTML DU PDF
// ==========================================

function build_insured_list($policyholder_name, $passport_number, $sale, $region, $start_date, $end_date) {
    global $insured_list;
    
    $insured_html = '
<div class="insured-title">List of Insured</div>
<table class="insured-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Passport Number</th>
            <th>Date of Birth</th>
            <th>Destination</th>
            <th>Cover Period</th>
        </tr>
    </thead>
    <tbody>';

    // Si une liste d'assurés existe, l'utiliser. Sinon, afficher l'assuré principal.
    if (!empty($insured_list) && is_array($insured_list)) {
        foreach ($insured_list as $insured) {
            $insured_html .= '
        <tr>
            <td>' . esc_html($insured['name']) . '</td>
            <td>' . esc_html($insured['passport']) . '</td>
            <td>' . esc_html($insured['dob']) . '</td>
            <td>' . esc_html($region) . '</td>
            <td>' . esc_html($start_date) . ' - ' . esc_html($end_date) . '</td>
        </tr>';
        }
    } else {
        $insured_html .= '
        <tr>
            <td>' . esc_html($policyholder_name) . '</td>
            <td>' . esc_html($passport_number) . '</td>
            <td>' . (!empty($sale->insured_dob) ? esc_html($sale->insured_dob) : '___') . '</td>
            <td>' . esc_html($region) . '</td>
            <td>' . esc_html($start_date) . ' - ' . esc_html($end_date) . '</td>
        </tr>';
    }

    $insured_html .= '
    </tbody>
</table>
<br><br>
';
    
    return $insured_html;
}

function build_terms_and_conditions($sale) {
    // Préparation du contenu des termes et conditions
    $terms_content = !empty($sale->terms) ? wpautop($sale->terms) : 'No terms and conditions available.';

    // Division du texte en deux colonnes
    $terms_parts = ['', ''];
    if (!empty($sale->terms)) {
        $terms_plain = strip_tags($sale->terms);
        $words = preg_split('/\s+/', $terms_plain);
        $half = ceil(count($words) / 2);
        $col1 = implode(' ', array_slice($words, 0, $half));
        $col2 = implode(' ', array_slice($words, $half));
        $terms_parts[0] = wpautop($col1);
        $terms_parts[1] = wpautop($col2);
    } else {
        $terms_parts[0] = 'No terms and conditions available.';
        $terms_parts[1] = '';
    }

    $terms_html = '
<h2 style="text-align:center;margin:0;">Terms and Conditions</h2>
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
    <tr>
        <td style="vertical-align:top;width:48%;padding:6px 12px 6px 0;">
            ' . $terms_parts[0] . '
        </td>
        <td style="width:4%;"></td>
        <td style="vertical-align:top;width:48%;padding:6px 0 6px 12px;">
            ' . $terms_parts[1] . '
        </td>
    </tr>
</table>
<br><br>
';
    
    return $terms_html;
}

function build_embassy_letter($policy_number, $start_date, $end_date, $product_name, $destination_area, 
                             $policyholder_name, $passport_number, $sale, $insurer) {
    $letter_html = '
<style>
    .letter {
        font-size: 11pt;
        line-height: 1.6;
        text-align: justify;
    }
    .letter-header {
        font-size: 12pt;
        font-weight: bold;
        margin-bottom: 10px;
    }
    .letter-table td {
        padding: 4px;
    }
</style>

<div class="letter">
    <div class="letter-header">TO: THE EMBASSY / CONSULATE</div>
    <p><strong>RE: OVERSEAS TRAVEL INSURANCE (Policy Nº. ' . $policy_number . ')</strong></p>
    <p>TO WHOM IT MAY CONCERN,</p>
    <p>This letter serves to confirm that the traveller(s) named below is/are covered under our overseas travel insurance policy while traveling during the period(s) detailed below:</p>

    <table class="letter-table">
        <tr><td><strong>Policy Number:</strong></td><td>' . $policy_number . '</td></tr>
        <tr><td><strong>Start Date:</strong></td><td>' . $start_date . '</td></tr>
        <tr><td><strong>End Date:</strong></td><td>' . $end_date . '</td></tr>
        <tr><td><strong>Product:</strong></td><td>' . $product_name . '</td></tr>
        <tr><td><strong>Territory Covered:</strong></td><td>' . $destination_area . '</td></tr>
    </table>

    <br>
    <p><strong>Insured Person(s):</strong></p>
    <table class="letter-table">
        <tr><td><strong>Name:</strong></td><td>' . $policyholder_name . '</td></tr>
        <tr><td><strong>Passport:</strong></td><td>' . $passport_number . '</td></tr>
        <tr><td><strong>Date of Birth:</strong></td><td>' . (!empty($sale->insured_dob) ? esc_html($sale->insured_dob) : '___') . '</td></tr>
    </table>

    <br>
    <p>The insured persons qualify for the Medical and Emergency Related Expenses listed in the Schedule of Covers of the Policy Certificate Nº ' . $policy_number . ' up to the following limits:</p>
    <ul>
        <li>Medical Transportation or Repatriation: EUR 36,000</li>
        <li>Medical Expenses Abroad: EUR 36,000</li>
    </ul>

    <br>
    <p>Yours faithfully,</p>
    <p><strong>Authorized Representative</strong><br>
    ' . strtoupper($insurer ?: 'INSURANCE COMPANY') . '<br>
    ' . strtoupper($product_name ?: 'TRAVEL PROTECT') . '</p>
</div>
';
    
    return $letter_html;
}

exit;