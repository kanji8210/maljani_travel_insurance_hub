<?php
// scripts/simulate_agent_reply.php
// Usage: php simulate_agent_reply.php session_id "Reply text"

if ($argc < 3) {
    echo "Usage: php simulate_agent_reply.php SESSION_ID \"Your reply\"\n";
    exit(1);
}

$session_id = intval($argv[1]);
$message = $argv[2];

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

$messages_table = $table_prefix . 'support_messages';
$sessions_table = $table_prefix . 'support_sessions';

$stmt = $mysqli->prepare("INSERT INTO {$messages_table} (session_id, sender, user_id, email, message, status, created_at) VALUES (?, 'agent', NULL, NULL, ?, 'new', NOW())");
$stmt->bind_param('is', $session_id, $message);
$ok = $stmt->execute();
if (! $ok) { echo "Insert failed: " . $stmt->error . "\n"; exit(1); }
$msg_id = $stmt->insert_id; $stmt->close();

$mysqli->query("UPDATE {$sessions_table} SET last_message_at = NOW() WHERE id = " . intval($session_id));

echo json_encode(['success'=>true,'session_id'=>$session_id,'message_id'=>$msg_id]);
$mysqli->close();
