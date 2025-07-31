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

    // Vérification de sécurité basique
    if (!current_user_can('read')) {
        wp_die('Access denied: You do not have permission to access this resource.');
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
        $pdf->SetFont('helvetica', '', 12);
        
        // En-tête du document
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'TRAVEL INSURANCE POLICY', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Informations de l'assureur
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, $insurer, 0, 1, 'C');
        $pdf->Ln(10);
        
        // Détails de la police
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'POLICY DETAILS', 0, 1, 'L');
        $pdf->Ln(3);
        
        $pdf->SetFont('helvetica', '', 10);
        
        // Tableau des informations
        $policy_data = [
            ['Policy Number:', $policy_number],
            ['Policyholder Name:', $policyholder_name],
            ['Passport Number:', $passport_number],
            ['National ID/PIN:', $pin],
            ['Address:', $address],
            ['Telephone:', $telephone],
            ['Email:', $email],
            ['Product Name:', $product_name],
            ['Destination Area:', $destination_area],
            ['Country of Origin:', $country_of_origin],
            ['Start Date:', $start_date],
            ['End Date:', $end_date],
            ['Duration (Days):', $duration_days],
            ['Agent:', $agent_name]
        ];
        
        foreach ($policy_data as $row) {
            $pdf->Cell(60, 6, $row[0], 0, 0, 'L');
            $pdf->Cell(0, 6, $row[1], 0, 1, 'L');
        }
        
        $pdf->Ln(10);
        
        // Détails de couverture
        if ($coverage) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'COVERAGE DETAILS', 0, 1, 'L');
            $pdf->Ln(3);
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->MultiCell(0, 5, $coverage, 0, 'L');
            $pdf->Ln(5);
        }
        
        // Pied de page
        $pdf->SetY(-30);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 5, 'This policy is issued by ' . $insurer, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        
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
?>
