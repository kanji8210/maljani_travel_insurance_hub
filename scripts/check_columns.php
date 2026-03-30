<?php
require_once('../../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'policy_sale';
$columns = $wpdb->get_results("DESCRIBE $table");
header('Content-Type: application/json');
echo json_encode($columns, JSON_PRETTY_PRINT);
