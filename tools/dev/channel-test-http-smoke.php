<?php

declare(strict_types=1);

$baseUrl = $argv[1] ?? 'http://127.0.0.1:8002';
$cookieFile = sys_get_temp_dir() . '/otel-security-channel-test-cookie.txt';
$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

if (is_file($cookieFile)) {
    unlink($cookieFile);
}

function http_request(string $method, string $url, ?array $fields, string $cookieFile): array
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
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
    ];
}

function csrf_from(string $html): string
{
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $html, $match)) {
        throw new RuntimeException('CSRF token bulunamadı.');
    }

    return $match[1];
}

$login = http_request('GET', $baseUrl . '/login', null, $cookieFile);
if ($login['status'] !== 200) {
    throw new RuntimeException('Login ekranı açılamadı.');
}

$auth = http_request('POST', $baseUrl . '/login', [
    '_csrf' => csrf_from($login['body']),
    'username' => 'admin',
    'password' => 'admin',
], $cookieFile);
if ($auth['status'] !== 302) {
    throw new RuntimeException('Login başarısız.');
}

$page = http_request('GET', $baseUrl . '/admin/notification-channels', null, $cookieFile);
if ($page['status'] !== 200 || !str_contains($page['body'], 'Kanal testi')) {
    throw new RuntimeException('Kanal testi alanı render edilmedi.');
}

$post = http_request('POST', $baseUrl . '/admin/notification-channels', [
    '_csrf' => csrf_from($page['body']),
    'action' => 'test',
    'code' => 'telegram',
    'name' => 'Telegram',
    'is_enabled' => 'on',
    'configured' => 'on',
    'bot_token' => '',
    'parse_mode' => '',
    'test_recipient' => '',
    'test_message' => 'Test',
], $cookieFile);
if ($post['status'] !== 302) {
    throw new RuntimeException('Test POST beklenen redirect dönmedi.');
}

$after = http_request('GET', $baseUrl . '/admin/notification-channels', null, $cookieFile);
if ($after['status'] !== 200 || !str_contains($after['body'], 'testi için test alıcısı zorunludur')) {
    throw new RuntimeException('Boş alıcı doğrulama mesajı görünmedi.');
}

echo "Kanal test HTTP smoke OK\n";
