<?php

declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8004', '/');
$cookieFile = sys_get_temp_dir() . '/otel-security-performance-cookie.txt';

if (is_file($cookieFile)) {
    unlink($cookieFile);
}

function perf_request(string $method, string $url, ?array $fields, string $cookieFile): array
{
    $start = microtime(true);
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
        'seconds' => microtime(true) - $start,
        'body' => substr($response, $headerSize),
    ];
}

function perf_csrf(string $body): string
{
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $body, $match)) {
        throw new RuntimeException('CSRF token bulunamadı.');
    }

    return $match[1];
}

function perf_line(string $label, array $response): void
{
    echo $label . ': HTTP ' . $response['status'] . ' | ' . number_format($response['seconds'], 3) . " sn\n";
}

$login = perf_request('GET', $baseUrl . '/login', null, $cookieFile);
perf_line('Login ekranı', $login);
$auth = perf_request('POST', $baseUrl . '/login', [
    '_csrf' => perf_csrf($login['body']),
    'username' => 'admin',
    'password' => 'admin',
], $cookieFile);
perf_line('Login POST', $auth);

$dashboard = perf_request('GET', $baseUrl . '/dashboard', null, $cookieFile);
perf_line('Dashboard', $dashboard);
$csrf = perf_csrf($dashboard['body']);

$heartbeat1 = perf_request('POST', $baseUrl . '/dashboard/heartbeat', ['_csrf' => $csrf], $cookieFile);
perf_line('Heartbeat 1', $heartbeat1);
echo 'Heartbeat 1 payload: ' . trim($heartbeat1['body']) . "\n";

$heartbeat2 = perf_request('POST', $baseUrl . '/dashboard/heartbeat', ['_csrf' => $csrf], $cookieFile);
perf_line('Heartbeat 2', $heartbeat2);
echo 'Heartbeat 2 payload: ' . trim($heartbeat2['body']) . "\n";

$records = perf_request('GET', $baseUrl . '/admin/records', null, $cookieFile);
perf_line('Kayıt listesi', $records);
