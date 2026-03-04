<?php
// Create support_chat table using DB credentials from wp-config.php (CLI-friendly)
$wp_config = __DIR__ . '/../../../../wp-config.php';
if (! file_exists($wp_config)) {
    echo "wp-config.php not found at: $wp_config\n";
    exit(1);
}

$content = file_get_contents($wp_config);
if (! preg_match("/define\(\s*'DB_NAME'\s*,\s*'([^']+)'\s*\)/", $content, $m)) {
    echo "Could not find DB_NAME in wp-config.php\n";
    exit(1);
}
$db_name = $m[1];
preg_match("/define\(\s*'DB_USER'\s*,\s*'([^']+)'\s*\)/", $content, $m2);
$db_user = isset($m2[1]) ? $m2[1] : '';
preg_match("/define\(\s*'DB_PASSWORD'\s*,\s*'([^']*)'\s*\)/", $content, $m3);
$db_pass = isset($m3[1]) ? $m3[1] : '';
preg_match("/define\(\s*'DB_HOST'\s*,\s*'([^']+)'\s*\)/", $content, $m4);
$db_host = isset($m4[1]) ? $m4[1] : 'localhost';

preg_match('/\$table_prefix\s*=\s*\'([^\']+)\'\s*;/', $content, $m5);
$table_prefix = isset($m5[1]) ? $m5[1] : 'wp_';

echo "Connecting to MySQL host {$db_host}, db {$db_name} as {$db_user}\n";
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    echo "MySQL connection failed: " . $mysqli->connect_error . "\n";
    exit(1);
}

$table = $table_prefix . 'support_chat';
$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    email VARCHAR(191) NOT NULL,
    message TEXT NOT NULL,
    response TEXT NULL,
    status ENUM('new','answered','closed') DEFAULT 'new',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY email (email),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

echo "Running CREATE TABLE for $table...\n";
if ($mysqli->query($sql)) {
    echo "Table {$table} created or already exists.\n";
} else {
    echo "Error creating table: " . $mysqli->error . "\n";
    exit(1);
}

$mysqli->close();
echo "Done.\n";
