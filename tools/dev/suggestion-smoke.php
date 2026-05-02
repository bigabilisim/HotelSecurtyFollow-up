<?php

declare(strict_types=1);

use App\Core\Database;

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8004', '/');
$cookieFile = sys_get_temp_dir() . '/otel-security-suggestion-cookie.txt';
$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

if (is_file($cookieFile)) {
    unlink($cookieFile);
}

function suggestion_http(string $method, string $url, ?array $fields, string $cookieFile): array
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

function suggestion_csrf(string $html): string
{
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $html, $match)) {
        throw new RuntimeException('CSRF token bulunamadı.');
    }

    return $match[1];
}

$login = suggestion_http('GET', $baseUrl . '/login', null, $cookieFile);
if ($login['status'] !== 200) {
    throw new RuntimeException('Login ekranı açılamadı.');
}

$auth = suggestion_http('POST', $baseUrl . '/login', [
    '_csrf' => suggestion_csrf($login['body']),
    'username' => 'admin',
    'password' => 'admin',
], $cookieFile);
if ($auth['status'] !== 302) {
    throw new RuntimeException('Login başarısız.');
}

$dashboard = suggestion_http('GET', $baseUrl . '/dashboard', null, $cookieFile);
if ($dashboard['status'] !== 200 || !str_contains($dashboard['body'], 'data-suggestion-open')) {
    throw new RuntimeException('Öneri butonu canlı panelde görünmedi.');
}

$title = 'Smoke önerisi ' . date('YmdHis');
$post = suggestion_http('POST', $baseUrl . '/suggestions', [
    '_csrf' => suggestion_csrf($dashboard['body']),
    'return_route' => '/dashboard',
    'page_route' => '/dashboard',
    'suggestion_type' => 'improvement',
    'priority' => 'normal',
    'title' => $title,
    'message' => 'Otomatik test önerisi.',
], $cookieFile);
if ($post['status'] !== 302) {
    throw new RuntimeException('Öneri POST beklenen redirect dönmedi.');
}

$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT id, status FROM user_suggestions WHERE title = :title AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['title' => $title]);
$suggestion = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$suggestion) {
    throw new RuntimeException('Öneri veritabanına kaydedilmedi.');
}

$admin = suggestion_http('GET', $baseUrl . '/admin/suggestions', null, $cookieFile);
if ($admin['status'] !== 200 || !str_contains($admin['body'], $title)) {
    throw new RuntimeException('Öneri yönetim ekranında görünmedi.');
}

$update = suggestion_http('POST', $baseUrl . '/admin/suggestions', [
    '_csrf' => suggestion_csrf($admin['body']),
    'action' => 'status',
    'id' => (int) $suggestion['id'],
    'status' => 'reviewing',
], $cookieFile);
if ($update['status'] !== 302) {
    throw new RuntimeException('Öneri durum güncellemesi redirect dönmedi.');
}

$deletePage = suggestion_http('GET', $baseUrl . '/admin/suggestions', null, $cookieFile);
$delete = suggestion_http('POST', $baseUrl . '/admin/suggestions', [
    '_csrf' => suggestion_csrf($deletePage['body']),
    'action' => 'delete',
    'id' => (int) $suggestion['id'],
], $cookieFile);
if ($delete['status'] !== 302) {
    throw new RuntimeException('Öneri silme redirect dönmedi.');
}

echo "Suggestion smoke OK\n";
