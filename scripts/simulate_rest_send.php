<?php
// scripts/simulate_rest_send.php
// Simple CLI simulation of the REST handler in includes/class-maljani-support.php

if ($argc < 2) {
    echo "Usage:\n";
    echo "  php simulate_rest_send.php '{\"email\":\"a@b.com\",\"message\":\"hello\"}'\n";
    echo "  OR\n  php simulate_rest_send.php email@example.com " . '"Your message"' . "\n";
    exit(1);
}

$first = $argv[1];
// If first arg looks like JSON, decode it; otherwise use arg1= email and arg2=message
if (strpos(trim($first), '{') === 0) {
    $json = $first;
    $data = json_decode($json, true);
    if (! is_array($data)) {
        echo "Invalid JSON payload\n";
        exit(1);
    }
    $email = isset($data['email']) ? $data['email'] : '';
    $message = isset($data['message']) ? $data['message'] : '';
} else {
    $email = $first;
    $message = isset($argv[2]) ? $argv[2] : '';
}
if (trim($message) === '') {
    echo json_encode(['success' => false, 'message' => 'Message required']);
    exit(0);
}

$wp_config = __DIR__ . '/../../../../wp-config.php';
if (! file_exists($wp_config)) {
    echo json_encode(['success' => false, 'message' => 'wp-config.php not found']);
    exit(1);
}
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
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'DB connect error: ' . $mysqli->connect_error]);
    exit(1);
}

$table = $table_prefix . 'support_chat';
$stmt = $mysqli->prepare("INSERT INTO `{$table}` (user_id, email, message, status) VALUES (?, ?, ?, 'new')");
// user_id unknown in CLI simulation
$null = null;
$stmt->bind_param('iss', $null, $email, $message);
$ok = $stmt->execute();
if (! $ok) {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
    exit(1);
}
$id = $stmt->insert_id;
$stmt->close();
$mysqli->close();

// Optionally send admin email using mail() — keep simple
$admin_email = 'admin@localhost';
if (file_exists(__DIR__ . '/../../../../wp-options.php')) {
    // no-op
}
$subject = 'New support message';
$body = "A new support message has been submitted.\n\nID: {$id}\nEmail: {$email}\n\nMessage:\n{$message}\n";
@mail($admin_email, $subject, $body);

echo json_encode(['success' => true, 'id' => $id]);
