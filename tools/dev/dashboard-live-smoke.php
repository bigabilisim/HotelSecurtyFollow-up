<?php

declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8004', '/');
$root = dirname(__DIR__, 2);
$cookieFile = sys_get_temp_dir() . '/otel-security-dashboard-live-cookie.txt';

require $root . '/app/bootstrap.php';

if (is_file($cookieFile)) {
    unlink($cookieFile);
}

function live_request(string $method, string $url, ?array $fields, string $cookieFile): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 10,
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

function live_assert_status(array $response, int $expected, string $label): void
{
    if ($response['status'] !== $expected) {
        file_put_contents(sys_get_temp_dir() . '/otel-security-dashboard-live-failure.html', $response['body']);
        throw new RuntimeException($label . ' beklenen HTTP ' . $expected . ', gelen ' . $response['status']);
    }

    echo $label . ': HTTP ' . $response['status'] . "\n";
}

function live_extract(string $pattern, string $body, string $label): string
{
    if (!preg_match($pattern, $body, $match)) {
        throw new RuntimeException($label . ' bulunamadı.');
    }

    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$login = live_request('GET', $baseUrl . '/login', null, $cookieFile);
live_assert_status($login, 200, 'Login ekranı');

$loginCsrf = live_extract('/name="_csrf" value="([^"]+)"/', $login['body'], 'Login CSRF');
$auth = live_request('POST', $baseUrl . '/login', [
    '_csrf' => $loginCsrf,
    'username' => 'admin',
    'password' => 'admin',
], $cookieFile);
live_assert_status($auth, 302, 'Login POST');

$dashboard = live_request('GET', $baseUrl . '/dashboard', null, $cookieFile);
live_assert_status($dashboard, 200, 'Dashboard');

foreach (['data-live-stats', 'data-live-verifications', 'data-live-inside', 'data-live-activity'] as $marker) {
    if (!str_contains($dashboard['body'], $marker)) {
        throw new RuntimeException('Canlı bölüm işaretçisi eksik: ' . $marker);
    }
}

$csrf = live_extract('/data-csrf="([^"]+)"/', $dashboard['body'], 'Dashboard CSRF');
$signature = live_extract('/data-dashboard-signature="([^"]+)"/', $dashboard['body'], 'Canlı imza');

$pdo = App\Core\Database::connection();
$adminId = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
$categoryId = (int) $pdo->query("SELECT id FROM visitor_categories WHERE status = 'active' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
$departmentId = (int) $pdo->query("SELECT id FROM departments WHERE status = 'active' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();

if ($adminId <= 0 || $categoryId <= 0) {
    throw new RuntimeException('Test için admin kullanıcı veya aktif kategori bulunamadı.');
}

$visitId = 0;
$visitorName = 'Canlı Panel Smoke ' . date('His');

try {
    $visitId = (new App\Models\Visit())->createEntry([
        'full_name' => $visitorName,
        'category_id' => $categoryId,
        'department_id' => $departmentId ?: null,
        'phone' => '05550009988',
        'vehicle_plate' => '34 LIVE 16',
        'company' => 'Canlı Panel Test',
        'host_name' => 'Dashboard',
        'purpose' => 'Smoke',
        'appointment_status' => 'walk_in',
        'note' => 'Sayfa yenilemeden canlı düşme testi',
    ], $adminId);

    $heartbeat = live_request('POST', $baseUrl . '/dashboard/heartbeat', [
        '_csrf' => $csrf,
        'signature' => $signature,
    ], $cookieFile);
    live_assert_status($heartbeat, 200, 'Heartbeat');

    $payload = json_decode($heartbeat['body'], true);
    if (!is_array($payload) || empty($payload['ok'])) {
        throw new RuntimeException('Heartbeat JSON payload okunamadı: ' . $heartbeat['body']);
    }

    if (empty($payload['changed']) || empty($payload['signature']) || $payload['signature'] === $signature) {
        throw new RuntimeException('Heartbeat canlı imzası yeni kayıt sonrası değişmedi.');
    }

    $html = is_array($payload['html'] ?? null) ? $payload['html'] : [];
    foreach (['stats', 'verifications', 'inside', 'activity'] as $key) {
        if (!array_key_exists($key, $html) || !is_string($html[$key])) {
            throw new RuntimeException('Heartbeat HTML parçası eksik: ' . $key);
        }
    }

    if (!str_contains($html['inside'], $visitorName) || !str_contains($html['activity'], $visitorName)) {
        throw new RuntimeException('Yeni kayıt canlı içeride/akış parçalarına düşmedi.');
    }

    echo "Canlı heartbeat HTML güncellemesi: OK\n";
} finally {
    if ($visitId > 0) {
        (new App\Models\Visit())->createExit($visitId, $adminId, 'Canlı panel smoke test çıkışı');
    }
}

echo "Dashboard live smoke test tamamlandı.\n";
