<?php
// scripts/run_support_test.php
// Minimal test runner that stubs $wpdb and checks Maljani_Database_Tools::get_table_schemas()

// Minimal $wpdb stub
class WPDB_Stub {
    public $prefix = 'wp_';
    public function get_charset_collate() { return ''; }
    public function get_var($q = null) { return null; }
}

global $wpdb;
$wpdb = new WPDB_Stub();

$file = __DIR__ . '/../admin/class-maljani-database-tools.php';
if (! file_exists($file)) {
    echo "Missing file: $file\n";
    exit(1);
}
require_once $file;

if (! class_exists('Maljani_Database_Tools')) {
    echo "Maljani_Database_Tools not found\n";
    exit(1);
}

$schemas = Maljani_Database_Tools::get_table_schemas();
if (isset($schemas['support_chat'])) {
    echo "TEST PASS: support_chat schema present\n";
    exit(0);
} else {
    echo "TEST FAIL: support_chat schema missing\n";
    exit(2);
}
