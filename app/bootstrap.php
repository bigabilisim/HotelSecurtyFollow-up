<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

load_env(BASE_PATH . '/.env');

$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

$sessionName = config('app.session_name', 'hotel_security_session');
$sessionLifetimeSeconds = max(86400, (int) config('app.session_lifetime_days', 365) * 86400);
$isSecureRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
date_default_timezone_set((string) config('app.timezone', 'Europe/Istanbul'));
session_name($sessionName);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.gc_maxlifetime', (string) $sessionLifetimeSeconds);
    ini_set('session.cookie_lifetime', (string) $sessionLifetimeSeconds);
    session_set_cookie_params([
        'lifetime' => $sessionLifetimeSeconds,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecureRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function config(string $key, mixed $default = null): mixed
{
    static $config = [];

    [$file, $item] = array_pad(explode('.', $key, 2), 2, null);

    if (!isset($config[$file])) {
        $path = BASE_PATH . '/config/' . $file . '.php';
        $config[$file] = is_file($path) ? require $path : [];
    }

    if ($item === null) {
        return $config[$file] ?? $default;
    }

    return $config[$file][$item] ?? $default;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function route(string $path, array $params = []): string
{
    $query = array_merge(['route' => $path], $params);
    return '/index.php?' . http_build_query($query);
}

function redirect(string $path, array $params = []): never
{
    header('Location: ' . route($path, $params));
    exit;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

function view(string $template, array $data = []): string
{
    extract($data, EXTR_SKIP);

    ob_start();
    require BASE_PATH . '/app/Views/' . $template . '.php';
    $content = ob_get_clean();

    ob_start();
    require BASE_PATH . '/app/Views/layout.php';
    return ob_get_clean();
}
