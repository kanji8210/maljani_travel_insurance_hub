<?php
// Boot WordPress and run DB creation for support_chat table
chdir(__DIR__ . '/..');
// path from scripts/ to WP load
// Ensure minimal server variables for CLI bootstrap
if (empty($_SERVER['HTTP_HOST'])) $_SERVER['HTTP_HOST'] = 'localhost';
if (empty($_SERVER['SERVER_NAME'])) $_SERVER['SERVER_NAME'] = 'localhost';
if (empty($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/';
if (empty($_SERVER['REQUEST_METHOD'])) $_SERVER['REQUEST_METHOD'] = 'GET';
if (empty($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$wp = __DIR__ . '/../../../../wp-load.php';
if (! file_exists($wp)) {
    echo "wp-load.php not found at: $wp\n";
    exit(1);
}
require_once $wp;

// Ensure the class is available
require_once __DIR__ . '/../admin/class-maljani-database-tools.php';

if (! class_exists('Maljani_Database_Tools')) {
    echo "Maljani_Database_Tools not available\n";
    exit(1);
}

echo "Creating support_chat table via Maljani_Database_Tools::create_missing_tables('support_chat')...\n";
$res = Maljani_Database_Tools::create_missing_tables('support_chat');
echo "Result:\n";
var_export($res);
echo "\nDone.\n";
