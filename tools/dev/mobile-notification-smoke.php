<?php

declare(strict_types=1);

use App\Core\Database;

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8004', '/');
$cookieFile = sys_get_temp_dir() . '/otel-security-mobile-notification-cookie.txt';
$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

if (is_file($cookieFile)) {
    unlink($cookieFile);
}

function mobile_http(string $method, string $url, ?array $fields, string $cookieFile): array
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
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
    ];
}

function mobile_csrf(string $html): string
{
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $html, $match)) {
        throw new RuntimeException('CSRF token bulunamadı.');
    }

    return $match[1];
}

$pdo = Database::connection();
(new App\Models\MobileNotification())->ensureReady();
$pdo->exec("UPDATE users SET mobile_notification_enabled = 1, mobile_notification_entry_enabled = 1, mobile_notification_exit_enabled = 1 WHERE username = 'admin' AND deleted_at IS NULL");
$adminUserId = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
if ($adminUserId > 0) {
    $pdo->prepare('UPDATE mobile_notification_logs SET status = "delivered", delivered_at = COALESCE(delivered_at, NOW()) WHERE user_id = :user_id AND status = "queued"')
        ->execute(['user_id' => $adminUserId]);
}
$categoryId = (int) $pdo->query("SELECT id FROM visitor_categories WHERE deleted_at IS NULL AND status = 'active' ORDER BY id LIMIT 1")->fetchColumn();
if ($categoryId <= 0) {
    throw new RuntimeException('Aktif kategori bulunamadı.');
}

$login = mobile_http('GET', $baseUrl . '/login', null, $cookieFile);
if ($login['status'] !== 200) {
    throw new RuntimeException('Login ekranı açılamadı.');
}

$auth = mobile_http('POST', $baseUrl . '/login', [
    '_csrf' => mobile_csrf($login['body']),
    'username' => 'admin',
    'password' => 'admin',
], $cookieFile);
if ($auth['status'] !== 302) {
    throw new RuntimeException('Login başarısız.');
}

$dashboard = mobile_http('GET', $baseUrl . '/dashboard', null, $cookieFile);
if ($dashboard['status'] !== 200 || !str_contains($dashboard['body'], 'data-mobile-notifications="1"')) {
    throw new RuntimeException('Mobil bildirim yetkisi dashboard HTML içinde görünmedi.');
}

$visitorName = 'Mobil Bildirim Test ' . date('YmdHis');
$entry = mobile_http('POST', $baseUrl . '/visits/entry', [
    '_csrf' => mobile_csrf($dashboard['body']),
    'category_id' => $categoryId,
    'full_name' => $visitorName,
    'phone' => '05550000000',
    'vehicle_plate' => '34 MBT 001',
    'company' => 'Test',
    'department_id' => '',
    'host_name' => '',
    'purpose' => 'Mobil bildirim testi',
    'note' => '',
], $cookieFile);
if ($entry['status'] !== 302) {
    throw new RuntimeException('Giriş kaydı POST beklenen redirect dönmedi.');
}

$poll = mobile_http('POST', $baseUrl . '/mobile-notifications/poll', [
    '_csrf' => mobile_csrf($dashboard['body']),
], $cookieFile);
if ($poll['status'] !== 200) {
    throw new RuntimeException('Mobil bildirim poll başarısız.');
}

$payload = json_decode($poll['body'], true);
if (!is_array($payload) || empty($payload['notifications'])) {
    throw new RuntimeException('Mobil bildirim kuyruğu boş döndü.');
}

$matched = false;
foreach ($payload['notifications'] as $notification) {
    if (str_contains((string) ($notification['message'] ?? ''), $visitorName)) {
        $matched = true;
        break;
    }
}

if (!$matched) {
    throw new RuntimeException('Yeni giriş kaydı mobil bildirim cevabında bulunamadı.');
}

$visitStmt = $pdo->prepare(
    'SELECT v.id
     FROM visits v
     INNER JOIN visitors vi ON vi.id = v.visitor_id
     WHERE vi.full_name = :full_name
     ORDER BY v.id DESC
     LIMIT 1'
);
$visitStmt->execute(['full_name' => $visitorName]);
$visitId = (int) $visitStmt->fetchColumn();
if ($visitId <= 0) {
    throw new RuntimeException('Mobil bildirim test giriş kaydı bulunamadı.');
}

$exit = mobile_http('POST', $baseUrl . '/visits/exit', [
    '_csrf' => mobile_csrf($dashboard['body']),
    'visit_id' => $visitId,
    'exit_note' => 'Mobil bildirim çıkış testi',
], $cookieFile);
if ($exit['status'] !== 302) {
    throw new RuntimeException('Çıkış kaydı POST beklenen redirect dönmedi.');
}

$exitPoll = mobile_http('POST', $baseUrl . '/mobile-notifications/poll', [
    '_csrf' => mobile_csrf($dashboard['body']),
], $cookieFile);
if ($exitPoll['status'] !== 200) {
    throw new RuntimeException('Çıkış mobil bildirim poll başarısız.');
}

$exitPayload = json_decode($exitPoll['body'], true);
if (!is_array($exitPayload) || empty($exitPayload['notifications'])) {
    throw new RuntimeException('Çıkış mobil bildirim kuyruğu boş döndü.');
}

$exitMatched = false;
foreach ($exitPayload['notifications'] as $notification) {
    if (($notification['title'] ?? '') === 'Yeni çıkış kaydı' && str_contains((string) ($notification['message'] ?? ''), $visitorName)) {
        $exitMatched = true;
        break;
    }
}

if (!$exitMatched) {
    throw new RuntimeException('Yeni çıkış kaydı mobil bildirim cevabında bulunamadı.');
}

echo "Mobile notification smoke OK\n";
