<?php
/**
 * Diagnostic simple pour PDF - À utiliser sur Bluehost
 * Accédez à ce fichier via votre navigateur pour voir les problèmes
 */

echo "<h2>Diagnostic Maljani PDF Generator</h2>";
echo "<p>Date/Heure: " . date('Y-m-d H:i:s') . "</p>";

// 1. Test des chemins WordPress
echo "<h3>1. Test de chargement WordPress</h3>";
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wordpress/wp-load.php',
    dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    echo "Tentative: " . htmlspecialchars($path) . " - ";
    if (file_exists($path)) {
        echo "✅ Existe<br>";
        if (!$wp_loaded) {
            try {
                require_once $path;
                $wp_loaded = true;
                echo "✅ WordPress chargé avec succès<br>";
            } catch (Exception $e) {
                echo "❌ Erreur de chargement: " . $e->getMessage() . "<br>";
            }
        }
    } else {
        echo "❌ N'existe pas<br>";
    }
}

// 2. Test de TCPDF
echo "<h3>2. Test de TCPDF</h3>";
$tcpdf_path = dirname(__DIR__) . '/lib/TCPDF-main/tcpdf.php';
echo "Chemin TCPDF: " . htmlspecialchars($tcpdf_path) . "<br>";
if (file_exists($tcpdf_path)) {
    echo "✅ TCPDF trouvé<br>";
    try {
        require_once $tcpdf_path;
        echo "✅ TCPDF chargé<br>";
    } catch (Exception $e) {
        echo "❌ Erreur TCPDF: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ TCPDF non trouvé<br>";
}

// 3. Informations système
echo "<h3>3. Informations système</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
echo "Document Root: " . htmlspecialchars($_SERVER['DOCUMENT_ROOT']) . "<br>";
echo "Current Directory: " . htmlspecialchars(__DIR__) . "<br>";

// 4. Test des extensions PHP requises
echo "<h3>4. Extensions PHP</h3>";
$required_extensions = ['gd', 'mbstring', 'zlib', 'xml'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext<br>";
    } else {
        echo "❌ $ext manquant<br>";
    }
}

// 5. Test de base de données si WordPress chargé
if ($wp_loaded && function_exists('get_option')) {
    echo "<h3>5. Test de base de données</h3>";
    global $wpdb;
    $table = $wpdb->prefix . 'policy_sale';
    $result = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($result === $table) {
        echo "✅ Table policy_sale existe<br>";
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo "Nombre de ventes: $count<br>";
    } else {
        echo "❌ Table policy_sale n'existe pas<br>";
    }
} else {
    echo "<h3>5. Base de données</h3>";
    echo "❌ WordPress non chargé - impossible de tester la base<br>";
}

echo "<hr>";
echo "<p><strong>Instructions:</strong></p>";
echo "<ul>";
echo "<li>Si vous voyez des ❌, ce sont les problèmes à résoudre</li>";
echo "<li>Contactez le support Bluehost si des extensions PHP manquent</li>";
echo "<li>Vérifiez que WordPress est bien installé si le chargement échoue</li>";
echo "</ul>";
?>
