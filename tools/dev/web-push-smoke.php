<?php

declare(strict_types=1);

use App\Core\Database;

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8004', '/');
$cookieFile = sys_get_temp_dir() . '/otel-security-web-push-cookie.txt';
$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

if (is_file($cookieFile)) {
    unlink($cookieFile);
}

function webpush_http(string $method, string $url, ?array $fields, string $cookieFile): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 15,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields ?? []));
    }

    $response = curl_exec($ch);
    if ($response === false) {
        throw new RuntimeException(curl_error($ch));
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => substr($response, $headerSize),
    ];
}

function webpush_csrf(string $html): string
{
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $html, $match)) {
        throw new RuntimeException('CSRF token bulunamadı.');
    }

    return $match[1];
}

function webpush_b64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

$pdo = Database::connection();
$pushSubscriptions = new App\Models\PushSubscription();
$pushSubscriptions->ensureReady();
$pdo->exec("UPDATE users SET mobile_notification_enabled = 1 WHERE username = 'admin' AND deleted_at IS NULL");

$login = webpush_http('GET', $baseUrl . '/login', null, $cookieFile);
if ($login['status'] !== 200) {
    throw new RuntimeException('Login ekranı açılamadı.');
}

$auth = webpush_http('POST', $baseUrl . '/login', [
    '_csrf' => webpush_csrf($login['body']),
    'username' => 'admin',
    'password' => 'admin',
], $cookieFile);
if ($auth['status'] !== 302) {
    throw new RuntimeException('Login başarısız.');
}

$dashboard = webpush_http('GET', $baseUrl . '/dashboard', null, $cookieFile);
if ($dashboard['status'] !== 200) {
    throw new RuntimeException('Dashboard açılamadı.');
}

foreach (['data-web-push-config-url', 'data-web-push-subscribe-url', 'data-web-push-unsubscribe-url'] as $needle) {
    if (!str_contains($dashboard['body'], $needle)) {
        throw new RuntimeException('Dashboard Web Push veri alanı eksik: ' . $needle);
    }
}

$config = webpush_http('GET', $baseUrl . '/mobile-notifications/web-push-config', null, $cookieFile);
if ($config['status'] !== 200) {
    throw new RuntimeException('Web Push config endpoint başarısız.');
}

$payload = json_decode($config['body'], true);
if (!is_array($payload) || empty($payload['enabled']) || empty($payload['publicKey'])) {
    throw new RuntimeException('Web Push public key alınamadı: ' . $config['body']);
}

$endpoint = 'https://push.example.test/subscription/' . bin2hex(random_bytes(8));
$subscription = [
    'endpoint' => $endpoint,
    'contentEncoding' => 'aes128gcm',
    'keys' => [
        'p256dh' => webpush_b64url("\x04" . random_bytes(64)),
        'auth' => webpush_b64url(random_bytes(16)),
    ],
];

$subscribe = webpush_http('POST', $baseUrl . '/mobile-notifications/subscribe', [
    '_csrf' => webpush_csrf($dashboard['body']),
    'subscription' => json_encode($subscription, JSON_UNESCAPED_SLASHES),
], $cookieFile);
if ($subscribe['status'] !== 200) {
    throw new RuntimeException('Web Push subscribe başarısız: ' . $subscribe['body']);
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE endpoint_hash = :hash AND status = "active"');
$countStmt->execute(['hash' => hash('sha256', $endpoint)]);
if ((int) $countStmt->fetchColumn() !== 1) {
    throw new RuntimeException('Push aboneliği veritabanına kaydedilmedi.');
}

$unsubscribe = webpush_http('POST', $baseUrl . '/mobile-notifications/unsubscribe', [
    '_csrf' => webpush_csrf($dashboard['body']),
    'endpoint' => $endpoint,
], $cookieFile);
if ($unsubscribe['status'] !== 200) {
    throw new RuntimeException('Web Push unsubscribe başarısız: ' . $unsubscribe['body']);
}

$countStmt->execute(['hash' => hash('sha256', $endpoint)]);
if ((int) $countStmt->fetchColumn() !== 0) {
    throw new RuntimeException('Push aboneliği pasife alınmadı.');
}

echo "Web Push smoke OK\n";
