<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Settings;
use App\Models\User;
use PDO;

final class SetupController
{
    public function index(): string
    {
        [$hasUsers, $databaseError] = $this->installState();

        if ($hasUsers) {
            Auth::requirePermission('settings.manage');
        }

        return view('setup/index', [
            'title' => 'Kurulum',
            'hasUsers' => $hasUsers,
            'databaseError' => $databaseError,
            'settings' => $this->settings(),
            'dbDefaults' => $this->dbDefaults(),
        ]);
    }

    public function store(): never
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/setup');
        }

        [$hasUsers] = $this->installState();
        if ($hasUsers) {
            Auth::requirePermission('settings.manage');
        }

        $database = [
            'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
            'port' => trim((string) ($_POST['db_port'] ?? '3306')),
            'database' => trim((string) ($_POST['db_database'] ?? '')),
            'username' => trim((string) ($_POST['db_username'] ?? '')),
            'password' => (string) ($_POST['db_password'] ?? ''),
        ];
        $admin = [
            'full_name' => trim((string) ($_POST['admin_name'] ?? 'Sistem Yöneticisi')),
            'username' => trim((string) ($_POST['admin_username'] ?? 'admin')),
            'email' => trim((string) ($_POST['admin_email'] ?? '')),
            'password' => (string) ($_POST['admin_password'] ?? ''),
            'password_repeat' => (string) ($_POST['admin_password_repeat'] ?? ''),
        ];

        if ($database['host'] === '' || $database['port'] === '' || $database['database'] === '' || $database['username'] === '') {
            flash('error', 'SQL host, port, veritabanı adı ve kullanıcı adı zorunludur.');
            redirect('/setup');
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $database['database'])) {
            flash('error', 'Veritabanı adı sadece harf, rakam ve alt çizgi içermelidir.');
            redirect('/setup');
        }

        if ($admin['full_name'] === '' || $admin['username'] === '' || strlen($admin['password']) < 4 || $admin['password'] !== $admin['password_repeat']) {
            flash('error', 'Admin ad soyad, kullanıcı adı ve eşleşen en az 4 karakter şifre zorunludur.');
            redirect('/setup');
        }

        try {
            $this->ensureEnvWritable();
            $server = $this->serverConnection($database);
            $this->createDatabase($server, $database['database']);
            $pdo = $this->databaseConnection($database);
            $this->runSqlFile($pdo, BASE_PATH . '/database/schema.sql');
            $this->repairExistingSchema($pdo);
            $this->runSqlFile($pdo, BASE_PATH . '/database/seed.sql');

            $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn();
            if ($userCount > 0 && !Auth::check()) {
                $this->writeEnv($database);
                $this->markSetupCompleted($pdo);
                flash('success', 'Veritabanı bağlantısı kaydedildi. Bu veritabanında kullanıcı bulundu; giriş yapabilirsiniz.');
                redirect('/login');
            }

            $userId = $userCount > 0 ? (int) Auth::id() : $this->createAdmin($pdo, $admin);
            $this->markSetupCompleted($pdo);
            $this->writeEnv($database);
            $_SESSION['user_id'] = $userId;
            unset($_SESSION['user']);
        } catch (\Throwable $error) {
            flash('error', 'Kurulum tamamlanamadı: ' . $error->getMessage());
            redirect('/setup');
        }

        flash('success', 'Kurulum tamamlandı. Sistem kullanıma hazır.');
        redirect('/dashboard');
    }

    private function installState(): array
    {
        try {
            return [(new User())->hasAnyUsers(), null];
        } catch (\Throwable $error) {
            return [false, $error->getMessage()];
        }
    }

    private function settings(): array
    {
        try {
            return (new Settings())->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function dbDefaults(): array
    {
        return [
            'host' => (string) env('DB_HOST', '127.0.0.1'),
            'port' => (string) env('DB_PORT', '3306'),
            'database' => (string) env('DB_DATABASE', 'hotel_security'),
            'username' => (string) env('DB_USERNAME', 'root'),
        ];
    }

    private function serverConnection(array $database): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $database['host'], $database['port']);

        return new PDO($dsn, $database['username'], $database['password'], $this->pdoOptions());
    }

    private function databaseConnection(array $database): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $database['host'],
            $database['port'],
            $database['database']
        );

        return new PDO($dsn, $database['username'], $database['password'], $this->pdoOptions());
    }

    private function createDatabase(PDO $pdo, string $databaseName): void
    {
        $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $this->quoteIdentifier($databaseName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function runSqlFile(PDO $pdo, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException('SQL dosyası okunamadı: ' . basename($path));
        }

        if (basename($path) === 'schema.sql') {
            $sql = preg_replace('/^\s*CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`?hotel_security`?.*?;\s*/is', '', $sql) ?? $sql;
            $sql = preg_replace('/^\s*USE\s+`?hotel_security`?\s*;\s*/is', '', $sql) ?? $sql;
        }

        $pdo->exec($sql);
    }

    private function repairExistingSchema(PDO $pdo): void
    {
        $this->ensureUserPermissionOverrideTable($pdo);
        $this->ensurePasswordResetTokenTable($pdo);
        $this->ensureLoginIpBlockTable($pdo);
        $this->ensureMobileNotificationTable($pdo);
        $this->ensurePushSubscriptionTable($pdo);
        $this->ensureReservationlessReviewTable($pdo);

        $this->ensureTableColumns($pdo, 'users', [
            'report_daily_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'report_weekly_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'report_monthly_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'mobile_notification_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'mobile_notification_entry_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'mobile_notification_exit_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ]);

        $this->ensureTableColumns($pdo, 'visitor_categories', [
            'color' => "VARCHAR(20) NOT NULL DEFAULT '#0f766e'",
            'max_duration_minutes' => 'INT UNSIGNED NULL',
            'warning_before_minutes' => 'INT UNSIGNED NOT NULL DEFAULT 10',
            'requires_department_approval' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'escalation_after_minutes' => 'INT UNSIGNED NOT NULL DEFAULT 5',
            'is_notification_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'is_quick_access' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'quick_access_order' => 'TINYINT UNSIGNED NULL',
            'escalation_level_1_user_id' => 'BIGINT UNSIGNED NULL',
            'escalation_level_1_after_minutes' => 'INT UNSIGNED NULL',
            'escalation_level_2_user_id' => 'BIGINT UNSIGNED NULL',
            'escalation_level_2_after_minutes' => 'INT UNSIGNED NULL',
            'escalation_level_3_user_id' => 'BIGINT UNSIGNED NULL',
            'escalation_level_3_after_minutes' => 'INT UNSIGNED NULL',
            'status' => "ENUM('active', 'passive') NOT NULL DEFAULT 'active'",
            'created_by' => 'BIGINT UNSIGNED NULL',
            'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'deleted_at' => 'TIMESTAMP NULL',
        ]);

        $this->ensureTableColumns($pdo, 'visits', [
            'appointment_status' => "ENUM('walk_in', 'appointment') NOT NULL DEFAULT 'walk_in'",
            'current_escalation_level' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        ]);

        $this->ensureTableColumns($pdo, 'department_verifications', [
            'response_token' => 'VARCHAR(64) NULL',
            'escalation_level' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        ]);

        $this->ensureTableColumns($pdo, 'notification_logs', [
            'action_yes_url' => 'VARCHAR(500) NULL',
            'action_no_url' => 'VARCHAR(500) NULL',
            'attachment_path' => 'VARCHAR(500) NULL',
            'attachment_name' => 'VARCHAR(180) NULL',
        ]);

        $this->ensureTableColumns($pdo, 'backup_jobs', [
            'mail_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'mail_recipient_name' => 'VARCHAR(160) NULL',
            'mail_recipient_email' => 'VARCHAR(190) NULL',
        ]);
    }

    private function ensureUserPermissionOverrideTable(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'users')) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_permission_overrides (
                user_id BIGINT UNSIGNED NOT NULL,
                permission_code VARCHAR(80) NOT NULL,
                is_allowed TINYINT(1) NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, permission_code),
                KEY idx_user_permission_overrides_code (permission_code),
                CONSTRAINT fk_user_permission_overrides_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensurePasswordResetTokenTable(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'users')) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                email VARCHAR(180) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                used_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_password_reset_tokens_hash (token_hash),
                KEY idx_password_reset_tokens_email (email),
                KEY idx_password_reset_tokens_user_status (user_id, used_at, expires_at),
                CONSTRAINT fk_password_reset_tokens_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureLoginIpBlockTable(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'users')) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS login_ip_blocks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                failed_attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                total_failed_attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                ban_count INT UNSIGNED NOT NULL DEFAULT 0,
                status ENUM("watching", "temporary", "permanent") NOT NULL DEFAULT "watching",
                blocked_until DATETIME NULL,
                first_failed_at DATETIME NULL,
                last_failed_at DATETIME NULL,
                last_username VARCHAR(180) NULL,
                last_user_agent VARCHAR(255) NULL,
                last_block_reason VARCHAR(255) NULL,
                released_at DATETIME NULL,
                released_by_user_id BIGINT UNSIGNED NULL,
                release_note VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_login_ip_blocks_ip (ip_address),
                KEY idx_login_ip_blocks_status (status, blocked_until),
                KEY idx_login_ip_blocks_last_failed (last_failed_at),
                KEY idx_login_ip_blocks_released_by (released_by_user_id),
                CONSTRAINT fk_login_ip_blocks_released_by
                    FOREIGN KEY (released_by_user_id) REFERENCES users(id)
                    ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureMobileNotificationTable(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'users') || !$this->tableExists($pdo, 'visits')) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS mobile_notification_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                visit_id BIGINT UNSIGNED NULL,
                title VARCHAR(160) NOT NULL,
                message TEXT NOT NULL,
                target_url VARCHAR(255) NULL,
                event_type ENUM("entry", "exit") NOT NULL DEFAULT "entry",
                status ENUM("queued", "delivered", "read", "skipped") NOT NULL DEFAULT "queued",
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                delivered_at TIMESTAMP NULL,
                read_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                KEY idx_mobile_notifications_user_status (user_id, status, created_at),
                KEY idx_mobile_notifications_visit (visit_id),
                KEY idx_mobile_notifications_deleted (deleted_at),
                CONSTRAINT fk_mobile_notifications_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_mobile_notifications_visit
                    FOREIGN KEY (visit_id) REFERENCES visits(id)
                    ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensurePushSubscriptionTable(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'users')) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS push_subscriptions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                endpoint_hash CHAR(64) NOT NULL,
                endpoint TEXT NOT NULL,
                public_key VARCHAR(255) NOT NULL,
                auth_token VARCHAR(255) NOT NULL,
                content_encoding VARCHAR(20) NOT NULL DEFAULT "aes128gcm",
                user_agent VARCHAR(255) NULL,
                status ENUM("active", "expired") NOT NULL DEFAULT "active",
                last_seen_at TIMESTAMP NULL,
                last_success_at TIMESTAMP NULL,
                last_error_at TIMESTAMP NULL,
                error_message VARCHAR(500) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_push_subscriptions_endpoint_hash (endpoint_hash),
                KEY idx_push_subscriptions_user_status (user_id, status, deleted_at),
                CONSTRAINT fk_push_subscriptions_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureReservationlessReviewTable(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'users') || !$this->tableExists($pdo, 'visits') || !$this->tableExists($pdo, 'departments')) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS reservationless_reviews (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                visit_id BIGINT UNSIGNED NOT NULL,
                department_id BIGINT UNSIGNED NULL,
                requested_user_id BIGINT UNSIGNED NULL,
                response_token VARCHAR(64) NOT NULL,
                room_number VARCHAR(80) NULL,
                manager_note TEXT NULL,
                status ENUM("pending", "submitted", "cancelled") NOT NULL DEFAULT "pending",
                requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                submitted_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_reservationless_reviews_visit (visit_id),
                UNIQUE KEY uk_reservationless_reviews_token (response_token),
                KEY idx_reservationless_reviews_status (status, requested_at),
                KEY idx_reservationless_reviews_department (department_id),
                CONSTRAINT fk_reservationless_reviews_visit
                    FOREIGN KEY (visit_id) REFERENCES visits(id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_reservationless_reviews_department
                    FOREIGN KEY (department_id) REFERENCES departments(id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_reservationless_reviews_requested_user
                    FOREIGN KEY (requested_user_id) REFERENCES users(id)
                    ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureTableColumns(PDO $pdo, string $table, array $definitions): void
    {
        if (!$this->tableExists($pdo, $table)) {
            return;
        }

        $columns = $this->tableColumns($pdo, $table);
        foreach ($definitions as $column => $definition) {
            if (in_array($column, $columns, true)) {
                continue;
            }

            try {
                $pdo->exec(
                    'ALTER TABLE ' . $this->quoteIdentifier($table)
                    . ' ADD COLUMN ' . $this->quoteIdentifier((string) $column) . ' ' . $definition
                );
            } catch (\PDOException $error) {
                if ((string) $error->getCode() !== '42S21' && (int) ($error->errorInfo[1] ?? 0) !== 1060) {
                    throw $error;
                }
            }
            $columns[] = (string) $column;
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableColumns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => $table]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function createAdmin(PDO $pdo, array $admin): int
    {
        $existing = $pdo->prepare('SELECT id FROM users WHERE username = :username AND deleted_at IS NULL LIMIT 1');
        $existing->execute(['username' => $admin['username']]);
        if ($existing->fetchColumn()) {
            throw new \RuntimeException('Bu admin kullanıcı adı zaten kullanılıyor.');
        }

        $departmentId = $pdo->query("SELECT id FROM departments WHERE code = 'GUVENLIK' LIMIT 1")->fetchColumn() ?: null;
        $stmt = $pdo->prepare(
            "INSERT INTO users (
                department_id,
                full_name,
                username,
                email,
                password_hash,
                status,
                report_daily_enabled,
                report_weekly_enabled,
                report_monthly_enabled
             ) VALUES (
                :department_id,
                :full_name,
                :username,
                :email,
                :password_hash,
                'active',
                0,
                0,
                0
             )"
        );
        $stmt->execute([
            'department_id' => $departmentId ? (int) $departmentId : null,
            'full_name' => $admin['full_name'],
            'username' => $admin['username'],
            'email' => $admin['email'] ?: null,
            'password_hash' => password_hash($admin['password'], PASSWORD_DEFAULT),
        ]);

        $userId = (int) $pdo->lastInsertId();
        $roleId = $pdo->query("SELECT id FROM roles WHERE code = 'admin' LIMIT 1")->fetchColumn();
        if ($roleId) {
            $role = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
            $role->execute(['user_id' => $userId, 'role_id' => (int) $roleId]);
        }

        return $userId;
    }

    private function markSetupCompleted(PDO $pdo): void
    {
        $settings = [
            'app.name' => 'Otel Güvenlik Sistemi',
            'app.domain' => $this->currentDomain(),
            'app.logo_text' => 'OG',
            'setup.completed' => '1',
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value, is_encrypted)
             VALUES (:setting_key, :setting_value, 0)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = VALUES(is_encrypted)'
        );

        foreach ($settings as $key => $value) {
            $stmt->execute(['setting_key' => $key, 'setting_value' => $value]);
        }
    }

    private function writeEnv(array $database): void
    {
        $values = [
            'APP_NAME' => (string) env('APP_NAME', 'Otel Güvenlik Sistemi'),
            'APP_URL' => $this->currentUrl(),
            'APP_DEBUG' => (string) env('APP_DEBUG', 'false'),
            'APP_TIMEZONE' => (string) env('APP_TIMEZONE', 'Europe/Istanbul'),
            'DB_HOST' => $database['host'],
            'DB_PORT' => $database['port'],
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => $database['password'],
            'SESSION_NAME' => (string) env('SESSION_NAME', 'hotel_security_session'),
        ];
        $lines = [];
        foreach ($values as $key => $value) {
            $lines[] = $key . '=' . $this->envValue((string) $value);
        }

        if (file_put_contents(BASE_PATH . '/.env', implode("\n", $lines) . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('.env dosyası yazılamadı.');
        }
    }

    private function ensureEnvWritable(): void
    {
        $path = BASE_PATH . '/.env';
        if (is_file($path) && !is_writable($path)) {
            throw new \RuntimeException('.env dosyası yazılabilir değil.');
        }

        if (!is_file($path) && !is_writable(BASE_PATH)) {
            throw new \RuntimeException('Proje klasörü .env oluşturmak için yazılabilir değil.');
        }
    }

    private function pdoOptions(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
        }

        return $options;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function envValue(string $value): string
    {
        if ($value !== '' && preg_match('/^[A-Za-z0-9_.,:\/@-]+$/', $value)) {
            return $value;
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function currentUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host;
    }

    private function currentDomain(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'security.hotel.local');

        return preg_replace('/:\d+$/', '', $host) ?: 'security.hotel.local';
    }
}
