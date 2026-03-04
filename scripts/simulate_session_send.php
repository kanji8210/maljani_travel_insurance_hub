<?php
// scripts/simulate_session_send.php
// Usage: php simulate_session_send.php email@example.com "Hello there"

if ($argc < 3) {
    echo "Usage: php simulate_session_send.php email@example.com \"Your message\"\n";
    exit(1);
}

$email = $argv[1];
$message = $argv[2];

// find wp-config.php
$wp_config = __DIR__ . '/../../../../wp-config.php';
if (! file_exists($wp_config)) {
    echo "wp-config.php not found\n";
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
    echo "DB connect error: " . $mysqli->connect_error . "\n";
    exit(1);
}

$sessions_table = $table_prefix . 'support_sessions';
$messages_table = $table_prefix . 'support_messages';

// find open session by email
$session_id = null;
$stmt = $mysqli->prepare("SELECT id FROM {$sessions_table} WHERE email = ? AND status = 'open' ORDER BY last_message_at DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($sid);
    if ($stmt->fetch()) {
        $session_id = $sid;
    }
    $stmt->close();
}

if (! $session_id) {
    $stmt = $mysqli->prepare("INSERT INTO {$sessions_table} (user_id, email, subject, status, last_message_at) VALUES (NULL, ?, NULL, 'open', NOW())");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $session_id = $stmt->insert_id;
    $stmt->close();
}

// insert message
$stmt = $mysqli->prepare("INSERT INTO {$messages_table} (session_id, sender, user_id, email, message, status, created_at) VALUES (?, 'user', NULL, ?, ?, 'new', NOW())");
$stmt->bind_param('iss', $session_id, $email, $message);
$ok = $stmt->execute();
if (! $ok) {
    echo "Insert failed: " . $stmt->error . "\n";
    exit(1);
}
$msg_id = $stmt->insert_id;
$stmt->close();

// update session last_message_at
$mysqli->query("UPDATE {$sessions_table} SET last_message_at = NOW() WHERE id = " . intval($session_id));

echo json_encode(['success'=>true,'session_id'=>$session_id,'message_id'=>$msg_id]);
$mysqli->close();
