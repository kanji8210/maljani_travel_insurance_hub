<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wordpress/wp-load.php');

$tcpdf_path = dirname(__DIR__) . '/lib/TCPDF-main/tcpdf.php';
if (!file_exists($tcpdf_path)) die('TCPDF not found at: ' . $tcpdf_path);
require_once $tcpdf_path;

// if (!current_user_can('manage_options')) wp_die('Not allowed');

$sale_id = isset($_GET['sale_id']) && !empty($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;
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

// --- Logo Handling ---
// Get server path for images, which is more reliable for TCPDF

// Site Logo
$site_logo_path = '';
$custom_logo_id = get_theme_mod('custom_logo');
if ($custom_logo_id) {
    $site_logo_path = get_attached_file($custom_logo_id); 
}
if (!$site_logo_path) {
    $site_icon_id = get_option('site_icon');
    if ($site_icon_id) {
        $site_logo_path = get_attached_file($site_icon_id);
    }
}

// Insurer Logo
$insurer_logo_path = '';
if ($insurer_id) {
    $logo_id_or_url = get_post_meta($insurer_id, '_insurer_logo', true);
    if (is_numeric($logo_id_or_url)) {
        // It's an ID, get the path.
        $insurer_logo_path = get_attached_file($logo_id_or_url);
    } elseif (filter_var($logo_id_or_url, FILTER_VALIDATE_URL)) {
        // It's a URL, try to convert it to a path.
        $logo_post_id = attachment_url_to_postid($logo_id_or_url);
        if ($logo_post_id) {
            $insurer_logo_path = get_attached_file($logo_post_id);
        } else {
            // As a fallback, keep the URL, but it might not render in TCPDF.
            $insurer_logo_path = $logo_id_or_url;
        }
    }
}

// Construction du HTML - Section Header
$html .= build_pdf_header($site_logo_path, $insurer_logo_path, $insurer);

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
function build_pdf_header($site_logo_path, $insurer_logo_path, $insurer) {
    $header_html = '
<table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
        <td class="header-left">';

    if ($site_logo_path && file_exists($site_logo_path)) {
        $header_html .= '<img src="' . $site_logo_path . '" class="site-logo" alt="Site Logo" style="max-height: 60px; max-width: 200px;">';
    } else {
        // Fallback: show site name instead of logo
        $header_html .= '<div style="font-size: 18px; font-weight: bold; color: #333; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">' . get_bloginfo('name') . '</div>';
    }

    $header_html .= '<h5>Insurance Aggregator</h5>
        </td>
        <td class="header-right">';

    if ($insurer_logo_path && file_exists($insurer_logo_path)) {
        $header_html .= '<img src="' . $insurer_logo_path . '" class="insurer-logo" alt="Insurer Logo" style="max-height: 60px; max-width: 200px;">';
    } else {
        // Fallback: show insurer name in styled box if no logo
        $header_html .= '<div style="border: 2px solid #333; padding: 15px; text-align: center; font-weight: bold; background: #f0f0f0;">' . esc_html($insurer ?: 'Insurance Company') . '</div>';
    }

    $header_html .= '<h5 class="insurer-name: "> Insurer : ' . esc_html($insurer ?: 'Insurance Company') . '</h5>
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

// Nouvelle page pour la lettre à l'ambassade
$pdf->AddPage();
$letter_html = '<style>' . $css_content . '</style>';
$letter_html .= build_embassy_letter($policy_number, $start_date, $end_date, $product_name, $destination_area, 
                                   $policyholder_name, $passport_number, $sale, $insurer, $sale_id);
$pdf->writeHTML($letter_html, true, false, true, false, '');

$pdf->Output('policy_' . $sale_id . '.pdf', 'I');

// ==========================================
// FONCTIONS POUR ORGANISER LE HTML DU PDF
// ==========================================

// Fonction pour générer un hash sécurisé pour la vérification
function generate_verification_hash($sale_id, $policy_number, $passport_number) {
    $secret_key = 'maljani_secure_key_2025'; // À changer en production
    $data = $sale_id . '|' . $policy_number . '|' . $passport_number;
    return hash('sha256', $data . $secret_key);
}

// Fonction pour générer l'URL de vérification avec QR code
function generate_verification_url($sale_id, $policy_number, $passport_number) {
    $hash = generate_verification_hash($sale_id, $policy_number, $passport_number);
    $site_url = home_url(); // Utilise l'URL dynamique du site WordPress
    $verify_url = $site_url . '/verify-policy?sale_id=' . $sale_id . '&token=' . $hash;
    return $verify_url;
}

// Fonction pour générer l'URL du QR code via API externe
function generate_qr_code_url($verification_url) {
    $qr_api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($verification_url);
    return $qr_api_url;
}

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
                             $policyholder_name, $passport_number, $sale, $insurer, $sale_id) {
    
    // Générer l'URL de vérification et le QR code
    $verification_url = generate_verification_url($sale_id, $policy_number, $passport_number);
    $qr_code_url = generate_qr_code_url($verification_url);
    
    $letter_html = '


<div class="letter">
    <div class="letter-header">TO: THE EMBASSY / CONSULATE</div>
    <div class="policy-ref">RE: OVERSEAS TRAVEL INSURANCE (Policy Nº. ' . $policy_number . ')</div>
    
    <div class="intro-text">
        <strong>TO WHOM IT MAY CONCERN,</strong><br>
        This letter confirms that the traveller(s) below is/are covered under our overseas travel insurance policy during the specified period:
    </div>

    <!-- Section détails côte à côte -->
    <div class="details-container">
        <div class="details-left">
            <div class="details-title">POLICY DETAILS</div>
            <div class="detail-row"><span class="detail-label">Policy Number:</span> ' . $policy_number . '</div>
            <div class="detail-row"><span class="detail-label">Product:</span> ' . $product_name . '</div>
            <div class="detail-row"><span class="detail-label">Start Date:</span> ' . $start_date . '</div>
            <div class="detail-row"><span class="detail-label">End Date:</span> ' . $end_date . '</div>
            <div class="detail-row"><span class="detail-label">Territory:</span> ' . $destination_area . '</div>
            <div class="detail-row"><span class="detail-label">Insurer:</span> ' . strtoupper($insurer ?: 'INSURANCE COMPANY') . '</div>
        </div>
        
        <div class="details-right">
            <div class="details-title">INSURED PERSON(S)</div>
            <div class="detail-row"><span class="detail-label">Name:</span> ' . $policyholder_name . '</div>
            <div class="detail-row"><span class="detail-label">Passport:</span> ' . $passport_number . '</div>
            <div class="detail-row"><span class="detail-label">Date of Birth:</span> ' . (!empty($sale->insured_dob) ? esc_html($sale->insured_dob) : '___') . '</div>
            <div class="detail-row"><span class="detail-label">Country of Origin:</span> KENYA</div>
            <div class="detail-row"><span class="detail-label">Coverage Type:</span> INDIVIDUAL</div>
        </div>
    </div>

    <!-- Section couverture médicale -->
    <div class="coverage-section">
        <div class="coverage-title">MEDICAL COVERAGE LIMITS</div>
        <div class="coverage-list">
            • <strong>Medical Transportation/Repatriation:</strong> EUR 36,000<br>
            • <strong>Medical Expenses Abroad:</strong> EUR 36,000<br>
            • <strong>Emergency Medical Assistance:</strong> 24/7 Available
        </div>
    </div>
    <br>
    <div>
    <hr></div>
    <br>
    <div class="signature-section">
        <p><strong>Yours faithfully,</strong></p>
        <p><strong>Authorized Representative</strong><br>
        ' . strtoupper($insurer ?: 'INSURANCE COMPANY') . '<br>
        <em>' . strtoupper($product_name ?: 'TRAVEL PROTECT') . '</em></p>
    </div>

    <!-- Section de vérification avec QR Code -->
    <div class="verification-section">
        <div class="verification-title">🔒 DOCUMENT VERIFICATION</div>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 65%; vertical-align: top; padding-right: 10px;">
                    <div class="security-note">
                        <strong>Security Instructions:</strong><br>
                        • Scan QR Code to verify authenticity<br>
                        • Verify URL starts with: maljaniinsurancecenter.com<br>
                        • Check dates and names match this document<br>
                        • For assistance: +254 XXX XXX XXX
                    </div>
                </td>
                <td style="width: 35%; text-align: center; vertical-align: middle;">
                    <div class="qr-container">
                        <img src="' . $qr_code_url . '" alt="Verification QR Code" style="width: 90px; height: 90px;">
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
';
    
    return $letter_html;
}

exit;