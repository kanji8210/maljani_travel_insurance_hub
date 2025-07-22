<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wordpress/wp-load.php');

$tcpdf_path = dirname(__DIR__) . '/lib/TCPDF-main/tcpdf.php';
if (!file_exists($tcpdf_path)) die('TCPDF not found at: ' . $tcpdf_path);
require_once $tcpdf_path;

if (!current_user_can('manage_options')) wp_die('Not allowed');

$sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;
if (!$sale_id) wp_die('No sale ID');

global $wpdb;
$table = $wpdb->prefix . 'policy_sale';
$sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $sale_id));
if (!$sale) wp_die('Sale not found');

// Policy info
$policy_id = $sale->policy_id;
$policy_title = get_the_title($policy_id);
$insurer_logo = get_post_meta($policy_id, '_insurer_logo', true); // URL ou ID image
if (is_numeric($insurer_logo)) $insurer_logo = wp_get_attachment_url($insurer_logo);
$insurer = get_post_meta($policy_id, '_insurer', true);
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

// Styles globaux
$html = '
<style>
    p { font-size:10px !important; }
</style>
';

if ($insurer_logo) {
    $html .= '<img src="' . esc_url($insurer_logo) . '" height="50"><br>';
}

$html .= '
<h1 style="text-align:center;">TRAVEL INSURANCE POLICY</h1>
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
<tr>
    <!-- Première box (65%) -->
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
    <!-- Deuxième box (35%) -->
    <td style="vertical-align:top;width:35%;border:1px solid #000;padding:0;">
        <table cellpadding="0" cellspacing="0" style="width:100%;">
            <tr><td style="padding:3px;"><strong>Policy Number:</strong> ' . $policy_number . '</td></tr>
            <tr><td style="padding:3px;"><strong>N. Passengers:</strong> ' . ($num_passengers ? $num_passengers : '1') . '</td></tr>
        </table>
    </td>
</tr>
</table>
<br><br>
';

$html .= '
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
<tr>
    <!-- Box 1 -->
    <td style="vertical-align:top;width:32.5%;border:1px solid #000;padding:6px;">
        <strong>Coverage Period</strong><br>
        Effective from: ' . ($start_date ?: '___') . '<br>
        Expiry: ' . ($end_date ?: '___') . '
    </td>
    <td style="width:3px;"></td>
    <!-- Box 2 -->
    <td style="vertical-align:top;width:32.5%;border:1px solid #000;padding:6px;">
        <strong>Policy period:</strong> ' . ($duration_days ?: '___') . ' Day(s)<br>
        <strong>Product:</strong> ' . ($product_name ?: '___') . '<br>
        INDIVIDUAL<br>
        Country of Origin: ' . ($country_of_origin ?: 'KENYA') . '
    </td>
    <td style="width:3px;"></td>
    <!-- Box 3 -->
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
';

$html .= '
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
<tr>
    <td style="border:1px solid #000;padding:6px;width:100%;">
        <strong>Destination Area:</strong> ' . ($destination_area ?: '___') . '
    </td>
</tr>
</table>
<br><br>
';

//cover details
$html .= '
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
';

//signature areas
$html .= '
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
    <tr>
        <td colspan="3" style="padding:6px 30px 16px 30px;">
            <p style="text-align:center;margin:0; font-size: 10px;">
                By signing this document, the Policyholder expressly accepts the clauses limiting the rights of the Insured included in the attached General Conditions of the Policy. This travel policy can only be changed or cancelled before the start date of the policy period.
            </p>
        </td>
    </tr>
    <tr>
        <!-- Policyholder -->
        <td style="border:0.5px solid #000;padding:20px 30px 16px 30px;width:48%;text-align:center;">
            <strong>Policyholder Signature</strong><br>
            <span style="display:inline-block;border-bottom:1px dotted #000;width:200px;height:32px;margin:16px 0;"></span><br>
            <span style="display:inline-block;width:200px;text-align:left;">Date: ' . date('Y-m-d') . '</span>
        </td>
        <td style="width:6%"></td>
        <!-- Insurer representative -->
        <td style="border:0.5px solid #000;padding:20px 30px 16px 30px;width:48%;text-align:center;">
            <strong>Insurer Representative</strong><br>
            <span style="display:inline-block;border-bottom:1px dotted #000;width:200px;height:32px;margin:16px 0;"></span><br>
            <span style="display:inline-block;width:200px;text-align:left;">Date: ' . date('Y-m-d') . '</span>
        </td>
    </tr>
</table>
<br><br>
';

// --- TERMS AND CONDITIONS EN 2 COLONNES ---
$terms_content = !empty($sale->terms) ? wpautop($sale->terms) : 'No terms and conditions available.';

// On découpe le texte en deux parties à peu près égales pour chaque colonne
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

$html .= '
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

// === LIST OF INSURED (à placer avant Terms and Conditions) ===
$html .= '
<style>
    .insured-table {
        border: 1px solid #000;
        border-collapse: collapse;
        width: 100%;
        font-size: 11pt;
    }
    .insured-table th, .insured-table td {
        border: 1px solid #000;
        padding: 6px;
        text-align: left;
    }
    .insured-title {
        font-size: 13pt;
        font-weight: bold;
        margin-bottom: 10px;
    }
</style>

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

$pdf->writeHTML($html, true, false, true, false, '');

// Nouvelle page pour la lettre
$pdf->AddPage();

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
    AIG KENYA INSURANCE COMPANY LIMITED<br>
    TRAVEL PROTECT</p>
</div>
';

$pdf->writeHTML($letter_html, true, false, true, false, '');

$pdf->Output('policy_' . $sale_id . '.pdf', 'I');
exit;