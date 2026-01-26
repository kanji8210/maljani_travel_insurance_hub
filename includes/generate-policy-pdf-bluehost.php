<?php
/**
 * Génération de PDF - Version Bluehost
 * Version corrigée pour résoudre les erreurs 500 sur Bluehost
 */

// Configuration d'erreur pour debug (à commenter en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Augmenter la limite de mémoire si possible
if (function_exists('ini_set')) {
    ini_set('memory_limit', '256M');
    ini_set('max_execution_time', 300);
}

try {
    // Méthode plus robuste pour charger WordPress
    if (!defined('ABSPATH')) {
        // Essayer plusieurs méthodes pour trouver wp-load.php
        $wp_load_paths = [
            __DIR__ . '/../../../../wp-load.php',
            $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
            dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php'
        ];
        
        $wp_loaded = false;
        foreach ($wp_load_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $wp_loaded = true;
                break;
            }
        }
        
        if (!$wp_loaded) {
            die('Error: WordPress core not found. Please check paths.');
        }
    }

    // Security: Verify user is logged in
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to access this document.');
    }

    // Validation des paramètres
    $sale_id = isset($_GET['sale_id']) && !empty($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;
    if (!$sale_id) {
        wp_die('Error: No sale ID provided.');
    }

    // Chargement TCPDF avec gestion d'erreur
    $tcpdf_path = dirname(__DIR__) . '/lib/TCPDF-main/tcpdf.php';
    $tcpdf_config = dirname(__DIR__) . '/lib/tcpdf-config-bluehost.php';
    
    if (!file_exists($tcpdf_path)) {
        wp_die('Error: TCPDF library not found at: ' . $tcpdf_path);
    }
    
    // Charger la configuration personnalisée pour Bluehost
    if (file_exists($tcpdf_config)) {
        require_once $tcpdf_config;
    }
    
    require_once $tcpdf_path;

    // Récupération des données de vente
    global $wpdb;
    $table = $wpdb->prefix . 'policy_sale';
    $sale = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $sale_id));
    
    if (!$sale) {
        wp_die('Error: Sale record not found for ID: ' . $sale_id);
    }
    
    // Security: Verify user has permission to view this sale
    $current_user_id = get_current_user_id();
    $user_email = wp_get_current_user()->user_email;
    
    // Allow: Admins, the agent who created it, or the insured person
    if (!current_user_can('manage_options') && 
        $sale->agent_id != $current_user_id && 
        $sale->insured_email != $user_email) {
        wp_die('You are not authorized to access this document.');
    }

    // Récupération des informations de la police
    $policy_id = $sale->policy_id;
    $policy_title = get_the_title($policy_id);
    
    if (!$policy_title) {
        wp_die('Error: Policy not found for ID: ' . $policy_id);
    }

    // Récupération des informations de l'assureur
    $insurer_id = get_post_meta($policy_id, '_policy_insurer', true);
    if (!$insurer_id) {
        wp_die('Error: No insurer assigned to policy ID: ' . $policy_id);
    }

    $insurer = get_post_meta($insurer_id, '_insurer_name', true);
    $insurer_logo = get_post_meta($insurer_id, '_insurer_logo', true);
    
    if (!$insurer) {
        wp_die('Error: Insurer profile incomplete for ID: ' . $insurer_id);
    }

    // Gestion du logo de l'assureur
    if ($insurer_logo) {
        if (is_numeric($insurer_logo)) {
            $insurer_logo = wp_get_attachment_url($insurer_logo);
        }
    }

    // Récupération de la région
    $region = '';
    $regions = get_the_terms($policy_id, 'policy_region');
    if ($regions && !is_wp_error($regions)) {
        $region = $regions[0]->name;
    }

    // Récupération de la couverture
    $coverage = get_post_meta($policy_id, '_policy_cover_details', true);

    // Informations de l'agent
    $agent = get_userdata($sale->agent_id);
    $agent_name = $agent ? $agent->display_name : 'N/A';

    // Préparation des données
    $policyholder_name = $sale->insured_names ?: 'N/A';
    $passport_number = $sale->passport_number ?? 'N/A';
    $address = $sale->insured_address ?? 'N/A';
    $telephone = $sale->insured_phone ?: 'N/A';
    $email = $sale->insured_email ?: 'N/A';
    $pin = $sale->national_id ?? 'N/A';
    $policy_number = $sale->policy_number ?? 'N/A';
    $start_date = $sale->departure ?: 'N/A';
    $end_date = $sale->return ?: 'N/A';
    
    // Calcul de la durée
    $duration_days = 'N/A';
    if ($start_date !== 'N/A' && $end_date !== 'N/A') {
        try {
            $d1 = new DateTime($start_date);
            $d2 = new DateTime($end_date);
            $duration_days = $d1 < $d2 ? $d1->diff($d2)->days : 'N/A';
        } catch (Exception $e) {
            $duration_days = 'N/A';
        }
    }

    $product_name = $policy_title ?: 'N/A';
    $destination_area = $region ?: 'N/A';
    $country_of_origin = $sale->country_of_origin ?? 'KENYA';

    // Configuration TCPDF pour Bluehost
    if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
        define('K_TCPDF_EXTERNAL_CONFIG', true);
    }

    // Création du PDF avec gestion d'erreur
    try {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Configuration de base
        $pdf->SetCreator('Maljani Insurance');
        $pdf->SetAuthor('Maljani Insurance');
        $pdf->SetTitle('Policy Document - ' . $policy_number);
        $pdf->SetSubject('Travel Insurance Policy');
        
        // Désactiver l'en-tête et le pied de page par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Configuration des marges
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Configuration de la police
        $pdf->SetFont('helvetica', '', 10);
        
        // Contenu HTML avec styles CSS
        $css_file_path = dirname(__FILE__) . '/css/pdf_generator.css';
        $css_content = '';
        if (file_exists($css_file_path)) {
            $css_content = file_get_contents($css_file_path);
        }
        
        $html = '<style>' . $css_content . '</style>';
        
        // En-tête du document avec logos
        $html .= build_pdf_header($policy_id);
        
        // Contenu principal de la police
        $html .= build_policy_content($sale, $policy_title, $insurer, $region, $premium, $days, $start_date, $end_date, $agent_name);
        
        // Liste des assurés
        $html .= build_insured_list($sale, $region, $start_date, $end_date);
        
        // Termes et conditions
        $html .= build_terms_and_conditions($sale, $policy_id);
        
        // Écrire le HTML principal
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Nouvelle page pour la lettre à l'ambassade
        $pdf->AddPage();
        $letter_html = '<style>' . $css_content . '</style>';
        $letter_html .= build_embassy_letter($sale, $policy_title, $insurer, $region, $start_date, $end_date, $policy_number);
        $pdf->writeHTML($letter_html, true, false, true, false, '');
        
        // Sortie du PDF
        $filename = 'Policy_' . $policy_number . '_' . date('Ymd') . '.pdf';
        $pdf->Output($filename, 'D');
        
    } catch (Exception $e) {
        wp_die('Error generating PDF: ' . $e->getMessage());
    }

} catch (Exception $e) {
    // Log l'erreur si possible
    if (function_exists('error_log')) {
        error_log('Maljani PDF Generation Error: ' . $e->getMessage());
    }
    
    wp_die('An error occurred while generating the PDF. Please contact support. Error: ' . $e->getMessage());
}

// ==========================================
// FONCTIONS POUR CONSTRUIRE LE HTML DU PDF
// ==========================================

function build_pdf_header($policy_id) {
    // Récupération des logos
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

    // Logo de l'assureur
    $insurer_logo_path = '';
    $insurer_id = get_post_meta($policy_id, '_policy_insurer', true);
    if ($insurer_id) {
        $logo_id_or_url = get_post_meta($insurer_id, '_insurer_logo', true);
        if (is_numeric($logo_id_or_url)) {
            $insurer_logo_path = get_attached_file($logo_id_or_url);
        } elseif (filter_var($logo_id_or_url, FILTER_VALIDATE_URL)) {
            $logo_post_id = attachment_url_to_postid($logo_id_or_url);
            if ($logo_post_id) {
                $insurer_logo_path = get_attached_file($logo_post_id);
            }
        }
    }

    $insurer = get_post_meta($insurer_id, '_insurer_name', true);

    $header_html = '
<table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
        <td class="header-left">';

    if ($site_logo_path && file_exists($site_logo_path)) {
        $header_html .= '<img src="' . $site_logo_path . '" class="site-logo" alt="Site Logo" style="max-height: 60px; max-width: 200px;">';
    } else {
        $header_html .= '<div style="font-size: 18px; font-weight: bold; color: #333; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">' . get_bloginfo('name') . '</div>';
    }

    $header_html .= '<h5>Insurance Aggregator</h5>
        </td>
        <td class="header-right">';

    if ($insurer_logo_path && file_exists($insurer_logo_path)) {
        $header_html .= '<img src="' . $insurer_logo_path . '" class="insurer-logo" alt="Insurer Logo" style="max-height: 60px; max-width: 200px;">';
    } else {
        $header_html .= '<div style="border: 2px solid #333; padding: 15px; text-align: center; font-weight: bold; background: #f0f0f0;">' . esc_html($insurer ?: 'Insurance Company') . '</div>';
    }

    $header_html .= '<h5 class="insurer-name"> Insurer : ' . esc_html($insurer ?: 'Insurance Company') . '</h5>
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

function build_policy_content($sale, $policy_title, $insurer, $region, $premium, $days, $start_date, $end_date, $agent_name) {
    $coverage = get_post_meta($sale->policy_id, '_policy_cover_details', true);
    
    $content_html = '
<h1 style="text-align:center;">TRAVEL INSURANCE POLICY</h1>

<!-- Section informations du souscripteur et de la police -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
<tr>
    <!-- Informations personnelles (65%) -->
    <td style="vertical-align:top;width:65%;border:1px solid #000;padding:0;">
        <table cellpadding="0" cellspacing="0" style="width:100%;">
            <tr><td style="padding:3px;"><strong>Policyholder:</strong> ' . esc_html($sale->insured_names) . '</td></tr>
            <tr><td style="padding:3px;"><strong>Passport:</strong> ' . esc_html($sale->passport_number) . '</td></tr>
            <tr><td style="padding:3px;"><strong>Address:</strong> ' . esc_html($sale->insured_address) . '</td></tr>
            <tr><td style="padding:3px;"><strong>Telephone:</strong> ' . esc_html($sale->insured_phone) . '</td></tr>
            <tr><td style="padding:3px;"><strong>Email:</strong> ' . esc_html($sale->insured_email) . '</td></tr>
            <tr><td style="padding:3px;"><strong>PIN Number:</strong> ' . esc_html($sale->national_id) . '</td></tr>
        </table>
    </td>
    <td style="width:5px;"></td>
    <!-- Informations de la police (35%) -->
    <td style="vertical-align:top;width:35%;border:1px solid #000;padding:0;">
        <table cellpadding="0" cellspacing="0" style="width:100%;">
            <tr><td style="padding:3px;"><strong>Policy Number:</strong> ' . esc_html($sale->policy_number) . '</td></tr>
            <tr><td style="padding:3px;"><strong>N. Passengers:</strong> 1</td></tr>
            <tr><td style="padding:3px;"><strong>Insurer:</strong> ' . esc_html($insurer) . '</td></tr>
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
        Effective from: ' . esc_html($start_date) . '<br>
        Expiry: ' . esc_html($end_date) . '
    </td>
    <td style="width:3px;"></td>
    <!-- Détails de la police -->
    <td style="vertical-align:top;width:32.5%;border:1px solid #000;padding:6px;">
        <strong>Policy period:</strong> ' . esc_html($days) . ' Day(s)<br>
        <strong>Product:</strong> ' . esc_html($policy_title) . '<br>
        INDIVIDUAL<br>
        Country of Origin: ' . esc_html($sale->country_of_origin ?: 'KENYA') . '
    </td>
    <td style="width:3px;"></td>
    <!-- Destination et montant -->
    <td style="vertical-align:top;width:32.5%;border:1px solid #000;padding:6px;">
        <strong>Destination:</strong> ' . esc_html($region) . '<br>
        <strong>Insurer:</strong> ' . esc_html($insurer) . '<br>
        <strong>Region:</strong> ' . esc_html($region) . '<br>
        <strong>Policy Amount</strong><br>
        GROSS PREMIUM ' . esc_html($premium) . ' USD
    </td>
</tr>
</table>
<br><br>

<!-- Zone de destination -->
<table cellpadding="0" cellspacing="0" style="width:100%;margin:auto;">
<tr>
    <td style="border:1px solid #000;padding:6px;width:100%;">
        <strong>Destination Area:</strong> ' . esc_html($region) . '
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

function build_insured_list($sale, $region, $start_date, $end_date) {
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
    <tbody>
        <tr>
            <td>' . esc_html($sale->insured_names) . '</td>
            <td>' . esc_html($sale->passport_number) . '</td>
            <td>' . (!empty($sale->insured_dob) ? esc_html($sale->insured_dob) : '___') . '</td>
            <td>' . esc_html($region) . '</td>
            <td>' . esc_html($start_date) . ' - ' . esc_html($end_date) . '</td>
        </tr>
    </tbody>
</table>
<br><br>
';
    
    return $insured_html;
}

function build_terms_and_conditions($sale, $policy_id) {
    $terms = get_post_meta($policy_id, '_policy_terms', true);
    
    // Division du texte en deux colonnes
    $terms_parts = ['', ''];
    if (!empty($terms)) {
        $terms_plain = strip_tags($terms);
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

function build_embassy_letter($sale, $policy_title, $insurer, $region, $start_date, $end_date, $policy_number) {
    // Générer l'URL de vérification et le QR code
    $verification_url = generate_verification_url($sale->id, $policy_number, $sale->passport_number);
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
            <div class="detail-row"><span class="detail-label">Product:</span> ' . $policy_title . '</div>
            <div class="detail-row"><span class="detail-label">Start Date:</span> ' . $start_date . '</div>
            <div class="detail-row"><span class="detail-label">End Date:</span> ' . $end_date . '</div>
            <div class="detail-row"><span class="detail-label">Territory:</span> ' . $region . '</div>
            <div class="detail-row"><span class="detail-label">Insurer:</span> ' . strtoupper($insurer ?: 'INSURANCE COMPANY') . '</div>
        </div>
        
        <div class="details-right">
            <div class="details-title">INSURED PERSON(S)</div>
            <div class="detail-row"><span class="detail-label">Name:</span> ' . $sale->insured_names . '</div>
            <div class="detail-row"><span class="detail-label">Passport:</span> ' . $sale->passport_number . '</div>
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
        <em>' . strtoupper($policy_title ?: 'TRAVEL PROTECT') . '</em></p>
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
                        • Verify URL starts with: ' . parse_url(home_url(), PHP_URL_HOST) . '<br>
                        • Check dates and names match this document<br>
                        • For assistance: Contact support
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

// Fonctions utilitaires pour la vérification
function generate_verification_hash($sale_id, $policy_number, $passport_number) {
    $secret_key = 'maljani_secure_key_2025';
    $data = $sale_id . '|' . $policy_number . '|' . $passport_number;
    return hash('sha256', $data . $secret_key);
}

function generate_verification_url($sale_id, $policy_number, $passport_number) {
    $hash = generate_verification_hash($sale_id, $policy_number, $passport_number);
    $site_url = home_url();
    $verify_url = $site_url . '/verify-policy?sale_id=' . $sale_id . '&token=' . $hash;
    return $verify_url;
}

function generate_qr_code_url($verification_url) {
    $qr_api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($verification_url);
    return $qr_api_url;
}
?>
