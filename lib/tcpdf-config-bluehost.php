<?php
/**
 * Configuration TCPDF pour Bluehost
 * Ce fichier aide à résoudre les problèmes de chemins et de configuration sur Bluehost
 */

// Configuration des chemins pour Bluehost
if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
}

// Chemins relatifs au plugin
$plugin_tcpdf_dir = dirname(__DIR__) . '/lib/TCPDF-main/';

// Configuration des constantes TCPDF
if (!defined('K_PATH_MAIN')) {
    define('K_PATH_MAIN', $plugin_tcpdf_dir);
}

if (!defined('K_PATH_URL')) {
    define('K_PATH_URL', plugin_dir_url(__FILE__) . '../lib/TCPDF-main/');
}

if (!defined('K_PATH_FONTS')) {
    define('K_PATH_FONTS', $plugin_tcpdf_dir . 'fonts/');
}

if (!defined('K_PATH_CACHE')) {
    // Utiliser le répertoire temp système ou un répertoire accessible en écriture
    $cache_dir = sys_get_temp_dir() . '/tcpdf_cache/';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    define('K_PATH_CACHE', $cache_dir);
}

if (!defined('K_PATH_URL_CACHE')) {
    define('K_PATH_URL_CACHE', K_PATH_URL . 'cache/');
}

if (!defined('K_PATH_IMAGES')) {
    define('K_PATH_IMAGES', $plugin_tcpdf_dir . 'examples/images/');
}

if (!defined('K_BLANK_IMAGE')) {
    define('K_BLANK_IMAGE', K_PATH_IMAGES . '_blank.png');
}

// Configuration générale
if (!defined('PDF_PAGE_FORMAT')) {
    define('PDF_PAGE_FORMAT', 'A4');
}

if (!defined('PDF_PAGE_ORIENTATION')) {
    define('PDF_PAGE_ORIENTATION', 'P');
}

if (!defined('PDF_CREATOR')) {
    define('PDF_CREATOR', 'Maljani Insurance Hub');
}

if (!defined('PDF_AUTHOR')) {
    define('PDF_AUTHOR', 'Maljani Insurance');
}

if (!defined('PDF_HEADER_TITLE')) {
    define('PDF_HEADER_TITLE', 'Maljani Insurance');
}

if (!defined('PDF_HEADER_STRING')) {
    define('PDF_HEADER_STRING', 'Travel Insurance Policy');
}

if (!defined('PDF_UNIT')) {
    define('PDF_UNIT', 'mm');
}

if (!defined('PDF_MARGIN_HEADER')) {
    define('PDF_MARGIN_HEADER', 5);
}

if (!defined('PDF_MARGIN_FOOTER')) {
    define('PDF_MARGIN_FOOTER', 10);
}

if (!defined('PDF_MARGIN_TOP')) {
    define('PDF_MARGIN_TOP', 27);
}

if (!defined('PDF_MARGIN_BOTTOM')) {
    define('PDF_MARGIN_BOTTOM', 25);
}

if (!defined('PDF_MARGIN_LEFT')) {
    define('PDF_MARGIN_LEFT', 15);
}

if (!defined('PDF_MARGIN_RIGHT')) {
    define('PDF_MARGIN_RIGHT', 15);
}

if (!defined('PDF_FONT_NAME_MAIN')) {
    define('PDF_FONT_NAME_MAIN', 'helvetica');
}

if (!defined('PDF_FONT_SIZE_MAIN')) {
    define('PDF_FONT_SIZE_MAIN', 10);
}

if (!defined('PDF_FONT_NAME_DATA')) {
    define('PDF_FONT_NAME_DATA', 'helvetica');
}

if (!defined('PDF_FONT_SIZE_DATA')) {
    define('PDF_FONT_SIZE_DATA', 8);
}

if (!defined('PDF_FONT_MONOSPACED')) {
    define('PDF_FONT_MONOSPACED', 'courier');
}

if (!defined('PDF_IMAGE_SCALE_RATIO')) {
    define('PDF_IMAGE_SCALE_RATIO', 1.25);
}

// Configuration spécifique pour éviter les erreurs sur Bluehost
if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR')) {
    define('K_TCPDF_THROW_EXCEPTION_ERROR', false);
}

// Configuration de sécurité
if (!defined('K_TCPDF_CALLS_IN_HTML')) {
    define('K_TCPDF_CALLS_IN_HTML', false);
}
?>
