<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Controllers\SetupController;

$envPath = BASE_PATH . '/.env';
$originalEnv = is_file($envPath) ? file_get_contents($envPath) : null;
$suffix = date('YmdHis') . '_' . bin2hex(random_bytes(3));
$databaseName = 'setup_wizard_smoke_' . $suffix;
$database = [
    'host' => (string) env('DB_HOST', '127.0.0.1'),
    'port' => (string) env('DB_PORT', '3306'),
    'database' => $databaseName,
    'username' => (string) env('DB_USERNAME', 'root'),
    'password' => (string) env('DB_PASSWORD', ''),
];
$admin = [
    'full_name' => 'Setup Smoke Admin',
    'username' => 'setup.smoke.' . $suffix,
    'email' => 'setup.smoke.' . $suffix . '@example.test',
    'password' => 'admin1234',
    'password_repeat' => 'admin1234',
];

$controller = new SetupController();
$reflection = new ReflectionClass($controller);
$call = static function (string $method, mixed ...$arguments) use ($controller, $reflection): mixed {
    $refMethod = $reflection->getMethod($method);
    $refMethod->setAccessible(true);

    return $refMethod->invoke($controller, ...$arguments);
};
$dropColumnIfExists = static function (PDO $pdo, string $table, string $column): void {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column'
    );
    $stmt->execute(['table' => $table, 'column' => $column]);

    if ((int) $stmt->fetchColumn() > 0) {
        $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` DROP COLUMN `' . str_replace('`', '``', $column) . '`');
    }
};

$server = null;

try {
    $call('ensureEnvWritable');
    $server = $call('serverConnection', $database);
    $call('createDatabase', $server, $database['database']);
    $pdo = $call('databaseConnection', $database);
    $call('runSqlFile', $pdo, BASE_PATH . '/database/schema.sql');
    foreach ([
        ['visitor_categories', 'is_quick_access'],
        ['visitor_categories', 'quick_access_order'],
        ['visitor_categories', 'escalation_level_1_user_id'],
        ['visitor_categories', 'escalation_level_1_after_minutes'],
        ['backup_jobs', 'mail_enabled'],
        ['users', 'report_daily_enabled'],
    ] as [$table, $column]) {
        $dropColumnIfExists($pdo, $table, $column);
    }
    $call('repairExistingSchema', $pdo);
    $call('runSqlFile', $pdo, BASE_PATH . '/database/seed.sql');
    $userId = (int) $call('createAdmin', $pdo, $admin);
    $call('markSetupCompleted', $pdo);
    $call('writeEnv', $database);

    $userStmt = $pdo->prepare(
        'SELECT u.id, u.username, GROUP_CONCAT(r.code ORDER BY r.code) AS role_codes
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         WHERE u.id = :id
         GROUP BY u.id, u.username'
    );
    $userStmt->execute(['id' => $userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['username'] !== $admin['username'] || !str_contains((string) $user['role_codes'], 'admin')) {
        throw new RuntimeException('Kurulum admin kullanıcısı beklenen admin rolüyle oluşmadı.');
    }

    $setupCompleted = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'setup.completed' LIMIT 1")->fetchColumn();
    if ((string) $setupCompleted !== '1') {
        throw new RuntimeException('Kurulum tamamlandı ayarı yazılmadı.');
    }

    $envContent = (string) file_get_contents($envPath);
    if (!str_contains($envContent, 'DB_DATABASE=' . $databaseName)) {
        throw new RuntimeException('.env dosyasına test veritabanı yazılmadı.');
    }

    echo "Setup SQL kurulumu: OK\n";
    echo "Setup admin kullanıcısı: OK\n";
    echo "Setup .env yazımı: OK\n";
} finally {
    if ($server instanceof PDO) {
        $server->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '``', $databaseName) . '`');
    }

    if ($originalEnv !== null) {
        file_put_contents($envPath, $originalEnv, LOCK_EX);
    } elseif (is_file($envPath)) {
        unlink($envPath);
    }
}

echo "Setup wizard smoke test tamamlandı.\n";
