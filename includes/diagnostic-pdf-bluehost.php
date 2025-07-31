<?php
/**
 * Script de diagnostic pour PDF - Bluehost
 * Utiliser ce script pour identifier les problèmes sur Bluehost
 */

// Activer l'affichage des erreurs pour diagnostic
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnostic Maljani PDF - Bluehost</h2>";

// 1. Test de chargement WordPress
echo "<h3>1. Test de chargement WordPress</h3>";
try {
    $wp_load_paths = [
        __DIR__ . '/../../../../wp-load.php',
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
        dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        echo "Tentative: $path - ";
        if (file_exists($path)) {
            echo "✅ Existe<br>";
            if (!$wp_loaded) {
                require_once $path;
                $wp_loaded = true;
                echo "✅ WordPress chargé avec succès<br>";
            }
        } else {
            echo "❌ N'existe pas<br>";
        }
    }
    
    if (!$wp_loaded) {
        echo "❌ Échec du chargement de WordPress<br>";
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
}

// 2. Test de TCPDF
echo "<h3>2. Test de TCPDF</h3>";
$tcpdf_path = dirname(__DIR__) . '/lib/TCPDF-main/tcpdf.php';
echo "Chemin TCPDF: $tcpdf_path<br>";
if (file_exists($tcpdf_path)) {
    echo "✅ TCPDF trouvé<br>";
    try {
        require_once $tcpdf_path;
        echo "✅ TCPDF chargé avec succès<br>";
        
        // Test de création d'instance
        $test_pdf = new TCPDF();
        echo "✅ Instance TCPDF créée<br>";
    } catch (Exception $e) {
        echo "❌ Erreur TCPDF: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ TCPDF non trouvé<br>";
}

// 3. Test des permissions et chemins
echo "<h3>3. Informations système</h3>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current Dir: " . __DIR__ . "<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";

// 4. Test de base de données
echo "<h3>4. Test de base de données</h3>";
if ($wp_loaded && function_exists('get_option')) {
    global $wpdb;
    echo "✅ Base de données accessible<br>";
    
    $table = $wpdb->prefix . 'policy_sale';
    $result = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($result === $table) {
        echo "✅ Table policy_sale existe<br>";
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo "Nombre d'enregistrements: $count<br>";
    } else {
        echo "❌ Table policy_sale n'existe pas<br>";
    }
} else {
    echo "❌ WordPress non chargé - impossible de tester la base<br>";
}

// 5. Test des fonctions WordPress
echo "<h3>5. Test des fonctions WordPress</h3>";
if (function_exists('get_option')) {
    echo "✅ get_option disponible<br>";
}
if (function_exists('get_post_meta')) {
    echo "✅ get_post_meta disponible<br>";
}
if (function_exists('wp_die')) {
    echo "✅ wp_die disponible<br>";
}

// 6. Test des extensions PHP
echo "<h3>6. Extensions PHP</h3>";
$required_extensions = ['gd', 'mbstring', 'zlib', 'xml'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext chargé<br>";
    } else {
        echo "❌ $ext manquant<br>";
    }
}

// 7. Test de création de fichier temporaire
echo "<h3>7. Test d'écriture de fichier</h3>";
$temp_file = sys_get_temp_dir() . '/test_maljani.txt';
if (file_put_contents($temp_file, 'test')) {
    echo "✅ Écriture de fichier possible<br>";
    unlink($temp_file);
} else {
    echo "❌ Impossible d'écrire des fichiers<br>";
}

echo "<h3>Diagnostic terminé</h3>";
echo "<p>Si vous voyez des ❌, ce sont les problèmes à résoudre pour que la génération PDF fonctionne.</p>";
?>
