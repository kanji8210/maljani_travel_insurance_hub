<?php
/**
 * Test simple de TCPDF pour Bluehost
 * Utiliser ce fichier pour tester rapidement si TCPDF fonctionne
 */

// Configuration d'erreur pour debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test TCPDF Simple - Bluehost</h2>";

try {
    // Augmenter la limite de mémoire
    ini_set('memory_limit', '256M');
    ini_set('max_execution_time', 300);
    
    // Charger la configuration
    $tcpdf_config = dirname(__DIR__) . '/lib/tcpdf-config-bluehost.php';
    if (file_exists($tcpdf_config)) {
        require_once $tcpdf_config;
        echo "✅ Configuration Bluehost chargée<br>";
    }
    
    // Charger TCPDF
    $tcpdf_path = dirname(__DIR__) . '/lib/TCPDF-main/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        die('❌ TCPDF non trouvé : ' . $tcpdf_path);
    }
    
    require_once $tcpdf_path;
    echo "✅ TCPDF chargé avec succès<br>";
    
    // Créer un PDF simple
    $pdf = new TCPDF();
    echo "✅ Instance TCPDF créée<br>";
    
    $pdf->SetCreator('Test Maljani');
    $pdf->SetAuthor('Test');
    $pdf->SetTitle('Test PDF');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Test PDF - TCPDF fonctionne sur Bluehost!', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Date: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    
    echo "✅ PDF créé avec succès<br>";
    
    // Générer le PDF
    $filename = 'test_tcpdf_bluehost.pdf';
    $pdf->Output($filename, 'D');
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "<br>";
    echo "❌ Stack trace : " . $e->getTraceAsString() . "<br>";
}
?>
