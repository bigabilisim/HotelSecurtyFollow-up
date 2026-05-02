<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Settings;

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8004', '/');
$cookieFile = sys_get_temp_dir() . '/otel-security-failed-login-admin-cookie.txt';
$failedCookieFile = sys_get_temp_dir() . '/otel-security-failed-login-guest-cookie.txt';
$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

foreach ([$cookieFile, $failedCookieFile] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

function failed_login_request(string $method, string $url, ?array $fields, string $cookieFile): array
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

function failed_login_csrf(string $html): string
{
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $html, $match)) {
        throw new RuntimeException('CSRF token bulunamadı.');
    }

    return $match[1];
}

$pdo = Database::connection();
$settingsModel = new Settings();
$settingsBefore = $settingsModel->all();
$mailEnabledBefore = $pdo->query("SELECT is_enabled FROM notification_channels WHERE code = 'mail' LIMIT 1")->fetchColumn();
$alertEmail = 'failed-login-smoke@example.test';

try {
    $pdo->prepare('DELETE FROM notification_logs WHERE recipient_address = :email')->execute(['email' => $alertEmail]);
    $pdo->exec("UPDATE notification_channels SET is_enabled = 0 WHERE code = 'mail'");

    $login = failed_login_request('GET', $baseUrl . '/login', null, $cookieFile);
    if ($login['status'] !== 200) {
        throw new RuntimeException('Admin login ekranı açılamadı.');
    }

    $auth = failed_login_request('POST', $baseUrl . '/login', [
        '_csrf' => failed_login_csrf($login['body']),
        'username' => 'admin',
        'password' => 'admin',
    ], $cookieFile);
    if ($auth['status'] !== 302) {
        throw new RuntimeException('Admin login başarısız.');
    }

    $admin = failed_login_request('GET', $baseUrl . '/admin', null, $cookieFile);
    if ($admin['status'] !== 200 || !str_contains($admin['body'], 'Hatalı Şifre Denemesi Maili')) {
        throw new RuntimeException('Hatalı şifre uyarısı yönetim ekranında görünmedi.');
    }

    $save = failed_login_request('POST', $baseUrl . '/admin/security-settings', [
        '_csrf' => failed_login_csrf($admin['body']),
        'failed_login_alert_enabled' => 'on',
        'failed_login_alert_email' => $alertEmail,
    ], $cookieFile);
    if ($save['status'] !== 302) {
        throw new RuntimeException('Hatalı şifre uyarı ayarı kaydedilemedi.');
    }

    $guestLogin = failed_login_request('GET', $baseUrl . '/login', null, $failedCookieFile);
    if ($guestLogin['status'] !== 200) {
        throw new RuntimeException('Misafir login ekranı açılamadı.');
    }

    $failed = failed_login_request('POST', $baseUrl . '/login', [
        '_csrf' => failed_login_csrf($guestLogin['body']),
        'username' => 'admin',
        'password' => 'yanlis-sifre-smoke',
    ], $failedCookieFile);
    if ($failed['status'] !== 302) {
        throw new RuntimeException('Hatalı login POST beklenen redirect dönmedi.');
    }

    $stmt = $pdo->prepare(
        'SELECT subject, message, status, error_message
         FROM notification_logs
         WHERE recipient_address = :email
           AND subject LIKE :subject
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'email' => $alertEmail,
        'subject' => '%Hatalı giriş denemesi%',
    ]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        throw new RuntimeException('Hatalı şifre denemesi için mail logu oluşmadı.');
    }

    foreach (['admin', 'IP adresi', 'Cihaz/Tarayıcı'] as $needle) {
        if (!str_contains((string) $log['message'], $needle)) {
            throw new RuntimeException('Hatalı şifre mail içeriğinde beklenen bilgi yok: ' . $needle);
        }
    }

    if ((string) $log['status'] !== 'skipped') {
        throw new RuntimeException('Mail kanalı kapalıyken test logu skipped olmalıydı, gelen: ' . $log['status']);
    }

    echo "Failed login alert smoke OK\n";
} finally {
    $settingsModel->setMany([
        'security.failed_login_alert_enabled' => (string) ($settingsBefore['security.failed_login_alert_enabled'] ?? '0'),
        'security.failed_login_alert_email' => (string) ($settingsBefore['security.failed_login_alert_email'] ?? ''),
    ]);

    if ($mailEnabledBefore !== false) {
        $stmt = $pdo->prepare("UPDATE notification_channels SET is_enabled = :is_enabled WHERE code = 'mail'");
        $stmt->execute(['is_enabled' => (int) $mailEnabledBefore]);
    }
}
