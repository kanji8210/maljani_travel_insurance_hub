<?php
// scripts/create_support_conversation_tables.php
// Create support_sessions and support_messages tables using wp-config DB credentials

$wp_config = __DIR__ . '/../../../../wp-config.php';
if (! file_exists($wp_config)) { echo "wp-config.php not found\n"; exit(1); }
$content = file_get_contents($wp_config);
preg_match("/define\(\s*'DB_NAME'\s*,\s*'([^']+)'\s*\)/", $content, $m);
$db_name = $m[1];
preg_match("/define\(\s*'DB_USER'\s*,\s*'([^']+)'\s*\)/", $content, $m2);
$db_user = isset($m2[1]) ? $m2[1] : '';
preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*'([^']*)'\s*\)/", $content, $m3);
$db_pass = isset($m3[1]) ? $m3[1] : '';
preg_match("/define\(\s*'DB_HOST'\s*,\s*'([^']+)'\s*\)/", $content, $m4);
$db_host = isset($m4[1]) ? $m4[1] : 'localhost';
preg_match('/\$table_prefix\s*=\s*\'([^\']+)\'\s*;/', $content, $m5);
$table_prefix = isset($m5[1]) ? $m5[1] : 'wp_';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) { echo "DB connect error: " . $mysqli->connect_error . "\n"; exit(1); }

$charset_collate = 'DEFAULT CHARSET=utf8mb4';

$sessions_sql = "CREATE TABLE IF NOT EXISTS `{$table_prefix}support_sessions` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    email VARCHAR(191) NULL,
    subject VARCHAR(191) NULL,
    status ENUM('open','closed') DEFAULT 'open',
    last_message_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY email (email),
    KEY status (status)
) $charset_collate;";

$messages_sql = "CREATE TABLE IF NOT EXISTS `{$table_prefix}support_messages` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    sender ENUM('user','agent') DEFAULT 'user',
    user_id BIGINT UNSIGNED NULL,
    email VARCHAR(191) NULL,
    message TEXT NOT NULL,
    status ENUM('new','read') DEFAULT 'new',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY session_id (session_id),
    KEY sender (sender),
    KEY status (status)
) $charset_collate;";

if ($mysqli->query($sessions_sql) === TRUE) {
    echo "Table {$table_prefix}support_sessions created or exists.\n";
} else { echo "Error creating sessions table: " . $mysqli->error . "\n"; }

if ($mysqli->query($messages_sql) === TRUE) {
    echo "Table {$table_prefix}support_messages created or exists.\n";
} else { echo "Error creating messages table: " . $mysqli->error . "\n"; }

$mysqli->close();
