<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\LoginIpBlock;
use App\Models\Settings;

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8004', '/');
$adminCookie = sys_get_temp_dir() . '/otel-security-ip-block-admin-cookie.txt';
$guestCookie = sys_get_temp_dir() . '/otel-security-ip-block-guest-cookie.txt';
$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

foreach ([$adminCookie, $guestCookie] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

function ip_block_request(string $method, string $url, ?array $fields, string $cookieFile): array
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

function ip_block_csrf(string $html): string
{
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $html, $match)) {
        throw new RuntimeException('CSRF token bulunamadı.');
    }

    return $match[1];
}

function ip_block_assert_status(array $response, int $expected, string $label): void
{
    if ($response['status'] !== $expected) {
        throw new RuntimeException($label . ' beklenen HTTP ' . $expected . ', gelen ' . $response['status']);
    }
}

$pdo = Database::connection();
$settingsModel = new Settings();
$settingsBefore = $settingsModel->all();
$ipModel = new LoginIpBlock();
$ipModel->ensureTable();
$ip = '127.0.0.1';
$previousStmt = $pdo->prepare('SELECT * FROM login_ip_blocks WHERE ip_address = :ip LIMIT 1');
$previousStmt->execute(['ip' => $ip]);
$previousRow = $previousStmt->fetch(PDO::FETCH_ASSOC) ?: null;

try {
    $settingsModel->setMany([
        'security.failed_login_alert_enabled' => '0',
        'security.failed_login_alert_email' => '',
    ]);

    $pdo->prepare('DELETE FROM login_ip_blocks WHERE ip_address = :ip')->execute(['ip' => $ip]);

    $adminLogin = ip_block_request('GET', $baseUrl . '/login', null, $adminCookie);
    ip_block_assert_status($adminLogin, 200, 'Admin login ekranı');
    $adminAuth = ip_block_request('POST', $baseUrl . '/login', [
        '_csrf' => ip_block_csrf($adminLogin['body']),
        'username' => 'admin',
        'password' => 'admin',
    ], $adminCookie);
    ip_block_assert_status($adminAuth, 302, 'Admin login POST');

    $guestLogin = ip_block_request('GET', $baseUrl . '/login', null, $guestCookie);
    ip_block_assert_status($guestLogin, 200, 'Misafir login ekranı');
    $guestCsrf = ip_block_csrf($guestLogin['body']);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $failed = ip_block_request('POST', $baseUrl . '/login', [
            '_csrf' => $guestCsrf,
            'username' => 'admin',
            'password' => 'yanlis-gecici-' . $attempt,
        ], $guestCookie);
        ip_block_assert_status($failed, 302, 'Geçici ban hatalı giriş #' . $attempt);
    }

    $stmt = $pdo->prepare('SELECT * FROM login_ip_blocks WHERE ip_address = :ip LIMIT 1');
    $stmt->execute(['ip' => $ip]);
    $temporary = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$temporary || $temporary['status'] !== 'temporary' || (int) $temporary['ban_count'] !== 1 || (int) $temporary['failed_attempt_count'] !== 5) {
        throw new RuntimeException('5 hatalı deneme sonrası geçici IP banı oluşmadı.');
    }

    $blockedLogin = ip_block_request('POST', $baseUrl . '/login', [
        '_csrf' => $guestCsrf,
        'username' => 'admin',
        'password' => 'admin',
    ], $guestCookie);
    ip_block_assert_status($blockedLogin, 302, 'Geçici ban doğru şifre engeli');

    $pdo->prepare('UPDATE login_ip_blocks SET blocked_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE ip_address = :ip')->execute(['ip' => $ip]);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $failed = ip_block_request('POST', $baseUrl . '/login', [
            '_csrf' => $guestCsrf,
            'username' => 'admin',
            'password' => 'yanlis-kalici-' . $attempt,
        ], $guestCookie);
        ip_block_assert_status($failed, 302, 'Kalıcı ban hatalı giriş #' . $attempt);
    }

    $stmt->execute(['ip' => $ip]);
    $permanent = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$permanent || $permanent['status'] !== 'permanent' || (int) $permanent['ban_count'] < 2 || (int) $permanent['failed_attempt_count'] !== 3) {
        throw new RuntimeException('İkinci seride 3 hatalı deneme sonrası kalıcı IP blok oluşmadı.');
    }

    $adminPage = ip_block_request('GET', $baseUrl . '/admin/ip-blocks', null, $adminCookie);
    ip_block_assert_status($adminPage, 200, 'IP blokları admin ekranı');
    foreach ([$ip, 'Kalıcı blok', 'Banı Kaldır'] as $needle) {
        if (!str_contains($adminPage['body'], $needle)) {
            throw new RuntimeException('IP blokları ekranında beklenen içerik yok: ' . $needle);
        }
    }

    $release = ip_block_request('POST', $baseUrl . '/admin/ip-blocks', [
        '_csrf' => ip_block_csrf($adminPage['body']),
        'id' => (int) $permanent['id'],
        'release_note' => 'Smoke test manuel açma.',
    ], $adminCookie);
    ip_block_assert_status($release, 302, 'IP blok kaldır POST');

    $stmt->execute(['ip' => $ip]);
    $released = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$released || $released['status'] !== 'watching' || (int) $released['failed_attempt_count'] !== 0 || $released['blocked_until'] !== null) {
        throw new RuntimeException('Admin manuel IP blok kaldırma beklenen sonucu vermedi.');
    }

    echo "Login IP block smoke OK\n";
} finally {
    $settingsModel->setMany([
        'security.failed_login_alert_enabled' => (string) ($settingsBefore['security.failed_login_alert_enabled'] ?? '0'),
        'security.failed_login_alert_email' => (string) ($settingsBefore['security.failed_login_alert_email'] ?? ''),
    ]);

    $pdo->prepare('DELETE FROM login_ip_blocks WHERE ip_address = :ip')->execute(['ip' => $ip]);
    if ($previousRow) {
        $columns = array_keys($previousRow);
        $columnSql = implode(', ', array_map(fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns));
        $placeholderSql = implode(', ', array_map(fn (string $column): string => ':' . $column, $columns));
        $restore = $pdo->prepare('INSERT INTO login_ip_blocks (' . $columnSql . ') VALUES (' . $placeholderSql . ')');
        $restore->execute($previousRow);
    }
}
