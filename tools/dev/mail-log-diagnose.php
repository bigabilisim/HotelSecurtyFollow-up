<?php

declare(strict_types=1);

use App\Core\Database;

$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

$pdo = Database::connection();

$stmt = $pdo->query(
    "SELECT code, name, is_enabled, config_json
     FROM notification_channels
     WHERE code = 'mail'
     LIMIT 1"
);
$channel = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$config = json_decode((string) ($channel['config_json'] ?? '{}'), true);
$config = is_array($config) ? $config : [];

echo "MAIL CHANNEL\n";
echo 'enabled: ' . ((int) ($channel['is_enabled'] ?? 0) === 1 ? 'yes' : 'no') . PHP_EOL;
echo 'configured: ' . (!empty($config['configured']) ? 'yes' : 'no') . PHP_EOL;
echo 'driver: ' . (string) ($config['driver'] ?? '-') . PHP_EOL;
echo 'smtp_host: ' . (string) ($config['smtp_host'] ?? '-') . PHP_EOL;
echo 'from_email: ' . (string) ($config['from_email'] ?? '-') . PHP_EOL;
echo PHP_EOL;

$logs = $pdo->query(
    "SELECT
        nl.id,
        nc.code AS channel,
        nl.recipient_name,
        nl.recipient_address,
        nl.subject,
        nl.status,
        nl.error_message,
        nl.attempt_count,
        nl.attachment_name,
        nl.queued_at,
        nl.sent_at
     FROM notification_logs nl
     INNER JOIN notification_channels nc ON nc.id = nl.channel_id
     WHERE nc.code = 'mail'
     ORDER BY nl.id DESC
     LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);

echo "RECENT MAIL LOGS\n";
foreach ($logs as $log) {
    echo '#' . $log['id']
        . ' | ' . $log['status']
        . ' | attempts=' . $log['attempt_count']
        . ' | to=' . $log['recipient_address']
        . ' | subject=' . $log['subject']
        . ' | attachment=' . ($log['attachment_name'] ?: '-')
        . ' | queued=' . $log['queued_at']
        . ' | sent=' . ($log['sent_at'] ?: '-')
        . PHP_EOL;

    if (!empty($log['error_message'])) {
        echo '  error: ' . $log['error_message'] . PHP_EOL;
    }
}
