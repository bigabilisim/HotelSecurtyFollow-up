<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\PermissionCatalog;
use PDO;

final class User
{
    private static bool $reportColumnsEnsured = false;
    private static bool $panelPermissionTableEnsured = false;

    public function all(): array
    {
        $this->ensureReportColumns();

        $stmt = Database::connection()->query(
            'SELECT
                u.id,
                u.department_id,
                u.full_name,
                u.username,
                u.email,
                u.phone,
                u.report_daily_enabled,
                u.report_weekly_enabled,
                u.report_monthly_enabled,
                u.mobile_notification_enabled,
                u.mobile_notification_entry_enabled,
                u.mobile_notification_exit_enabled,
                u.mobile_notification_department_enabled,
                u.mobile_notification_external_movement_enabled,
                u.status,
                d.name AS department_name,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ", ") AS role_names
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.deleted_at IS NULL
             GROUP BY
                u.id,
                u.department_id,
                u.full_name,
                u.username,
                u.email,
                u.phone,
                u.report_daily_enabled,
                u.report_weekly_enabled,
                u.report_monthly_enabled,
                u.mobile_notification_enabled,
                u.mobile_notification_entry_enabled,
                u.mobile_notification_exit_enabled,
                u.mobile_notification_department_enabled,
                u.mobile_notification_external_movement_enabled,
                u.status,
                d.name
             ORDER BY u.status, u.full_name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasAnyUsers(): bool
    {
        $this->ensureReportColumns();

        $stmt = Database::connection()->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
        return (int) $stmt->fetchColumn() > 0;
    }

    public function find(int $id): ?array
    {
        $this->ensureReportColumns();

        $stmt = Database::connection()->prepare(
            'SELECT u.*, d.name AS department_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = :id AND u.deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $user['roles'] = $this->roles((int) $user['id']);
        return $user;
    }

    public function findForEdit(int $id): ?array
    {
        $user = $this->find($id);

        if (!$user) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT role_id
             FROM user_roles
             WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $id]);

        $user['role_ids'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $user['panel_permissions'] = $this->effectivePanelPermissions($id);
        return $user;
    }

    public function findByUsername(string $username): ?array
    {
        $this->ensureReportColumns();

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM users
             WHERE username = :username AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function findActiveByEmail(string $email): ?array
    {
        $this->ensureReportColumns();

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM users
             WHERE email = :email
               AND status = "active"
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['email' => trim($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function createAdmin(array $data): int
    {
        $this->ensureReportColumns();

        $pdo = Database::connection();
        $departmentId = $this->departmentIdByCode('GUVENLIK');

        $stmt = $pdo->prepare(
            "INSERT INTO users (
                department_id,
                full_name,
                username,
                email,
                phone,
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
                :phone,
                :password_hash,
                'active',
                0,
                0,
                0
             )"
        );
        $stmt->execute([
            'department_id' => $departmentId,
            'full_name' => $data['full_name'],
            'username' => $data['username'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);

        $userId = (int) $pdo->lastInsertId();
        $roleId = $this->roleIdByCode('admin');

        if ($roleId) {
            $roleStmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
            $roleStmt->execute(['user_id' => $userId, 'role_id' => $roleId]);
        }

        return $userId;
    }

    public function create(array $data): int
    {
        return $this->save($data);
    }

    public function save(array $data): int
    {
        $this->ensureReportColumns();

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $userId = (int) ($data['id'] ?? 0);

            if ($userId > 0) {
                $passwordSql = !empty($data['password']) ? ', password_hash = :password_hash' : '';
                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET department_id = :department_id,
                         full_name = :full_name,
                         username = :username,
                         email = :email,
                         phone = :phone,
                         report_daily_enabled = :report_daily_enabled,
                         report_weekly_enabled = :report_weekly_enabled,
                         report_monthly_enabled = :report_monthly_enabled,
                         mobile_notification_enabled = :mobile_notification_enabled,
                         mobile_notification_entry_enabled = :mobile_notification_entry_enabled,
                         mobile_notification_exit_enabled = :mobile_notification_exit_enabled,
                         mobile_notification_department_enabled = :mobile_notification_department_enabled,
                         mobile_notification_external_movement_enabled = :mobile_notification_external_movement_enabled,
                         status = :status' . $passwordSql . '
                     WHERE id = :id AND deleted_at IS NULL'
                );

                $payload = [
                    'id' => $userId,
                    'department_id' => $data['department_id'] ?: null,
                    'full_name' => $data['full_name'],
                    'username' => $data['username'],
                    'email' => $data['email'] ?: null,
                    'phone' => $data['phone'] ?: null,
                    'report_daily_enabled' => !empty($data['report_daily_enabled']) ? 1 : 0,
                    'report_weekly_enabled' => !empty($data['report_weekly_enabled']) ? 1 : 0,
                    'report_monthly_enabled' => !empty($data['report_monthly_enabled']) ? 1 : 0,
                    'mobile_notification_enabled' => !empty($data['mobile_notification_enabled']) ? 1 : 0,
                    'mobile_notification_entry_enabled' => !empty($data['mobile_notification_entry_enabled']) ? 1 : 0,
                    'mobile_notification_exit_enabled' => !empty($data['mobile_notification_exit_enabled']) ? 1 : 0,
                    'mobile_notification_department_enabled' => !empty($data['mobile_notification_department_enabled']) ? 1 : 0,
                    'mobile_notification_external_movement_enabled' => !empty($data['mobile_notification_external_movement_enabled']) ? 1 : 0,
                    'status' => $data['status'] ?: 'active',
                ];

                if (!empty($data['password'])) {
                    $payload['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                }

                $stmt->execute($payload);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (
                        department_id,
                        full_name,
                        username,
                        email,
                        phone,
                        password_hash,
                        status,
                        report_daily_enabled,
                        report_weekly_enabled,
                        report_monthly_enabled,
                        mobile_notification_enabled,
                        mobile_notification_entry_enabled,
                        mobile_notification_exit_enabled,
                        mobile_notification_department_enabled,
                        mobile_notification_external_movement_enabled
                    )
                 VALUES (
                    :department_id,
                    :full_name,
                    :username,
                    :email,
                    :phone,
                    :password_hash,
                    :status,
                    :report_daily_enabled,
                    :report_weekly_enabled,
                    :report_monthly_enabled,
                    :mobile_notification_enabled,
                    :mobile_notification_entry_enabled,
                    :mobile_notification_exit_enabled,
                    :mobile_notification_department_enabled,
                    :mobile_notification_external_movement_enabled
                 )
                 ON DUPLICATE KEY UPDATE
                    department_id = VALUES(department_id),
                    full_name = VALUES(full_name),
                    username = VALUES(username),
                    email = VALUES(email),
                    phone = VALUES(phone),
                    report_daily_enabled = VALUES(report_daily_enabled),
                    report_weekly_enabled = VALUES(report_weekly_enabled),
                    report_monthly_enabled = VALUES(report_monthly_enabled),
                    mobile_notification_enabled = VALUES(mobile_notification_enabled),
                    mobile_notification_entry_enabled = VALUES(mobile_notification_entry_enabled),
                    mobile_notification_exit_enabled = VALUES(mobile_notification_exit_enabled),
                    mobile_notification_department_enabled = VALUES(mobile_notification_department_enabled),
                    mobile_notification_external_movement_enabled = VALUES(mobile_notification_external_movement_enabled),
                    status = VALUES(status),
                    password_hash = VALUES(password_hash),
                    deleted_at = NULL'
                );
                $stmt->execute([
                    'department_id' => $data['department_id'] ?: null,
                    'full_name' => $data['full_name'],
                    'username' => $data['username'],
                    'email' => $data['email'] ?: null,
                    'phone' => $data['phone'] ?: null,
                    'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'status' => $data['status'] ?: 'active',
                    'report_daily_enabled' => !empty($data['report_daily_enabled']) ? 1 : 0,
                    'report_weekly_enabled' => !empty($data['report_weekly_enabled']) ? 1 : 0,
                    'report_monthly_enabled' => !empty($data['report_monthly_enabled']) ? 1 : 0,
                    'mobile_notification_enabled' => !empty($data['mobile_notification_enabled']) ? 1 : 0,
                    'mobile_notification_entry_enabled' => !empty($data['mobile_notification_entry_enabled']) ? 1 : 0,
                    'mobile_notification_exit_enabled' => !empty($data['mobile_notification_exit_enabled']) ? 1 : 0,
                    'mobile_notification_department_enabled' => !empty($data['mobile_notification_department_enabled']) ? 1 : 0,
                    'mobile_notification_external_movement_enabled' => !empty($data['mobile_notification_external_movement_enabled']) ? 1 : 0,
                ]);

                $userId = (int) $pdo->lastInsertId();

                if ($userId === 0) {
                    $existing = $this->findByUsername($data['username']);
                    $userId = (int) ($existing['id'] ?? 0);

                    if ($userId === 0 && !empty($data['email'])) {
                        $emailStmt = $pdo->prepare(
                            'SELECT id
                             FROM users
                             WHERE email = :email AND deleted_at IS NULL
                             LIMIT 1'
                        );
                        $emailStmt->execute(['email' => $data['email']]);
                        $userId = (int) $emailStmt->fetchColumn();
                    }
                }

                if ($userId === 0) {
                    throw new \RuntimeException('Kullanıcı kaydı bulunamadı.');
                }
            }

            $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute(['user_id' => $userId]);

            $roleStmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
            foreach (($data['role_ids'] ?? []) as $roleId) {
                $roleStmt->execute([
                    'user_id' => $userId,
                    'role_id' => (int) $roleId,
                ]);
            }

            if (array_key_exists('panel_permissions', $data)) {
                $this->savePanelPermissionOverrides($userId, (array) $data['panel_permissions']);
            }

            $pdo->commit();
            return $userId;
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public function delete(int $id, int $currentUserId): bool
    {
        $this->ensureReportColumns();

        if ($id === $currentUserId) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            "UPDATE users
             SET deleted_at = NOW(), status = 'passive'
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function touchLastLogin(int $id): void
    {
        $this->ensureReportColumns();

        $stmt = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function updatePassword(int $id, string $password): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET password_hash = :password_hash
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public function reportSubscribers(string $type): array
    {
        $this->ensureReportColumns();

        $column = match ($type) {
            'weekly' => 'report_weekly_enabled',
            'monthly' => 'report_monthly_enabled',
            default => 'report_daily_enabled',
        };

        $stmt = Database::connection()->query(
            'SELECT id, full_name, email, phone
             FROM users
             WHERE deleted_at IS NULL
               AND status = "active"
               AND email IS NOT NULL
               AND email <> ""
               AND ' . $column . ' = 1
             ORDER BY full_name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function panelPermissionOptions(): array
    {
        return PermissionCatalog::userPermissionOptions();
    }

    public function ensureReportColumns(): void
    {
        if (self::$reportColumnsEnsured) {
            $this->ensurePanelPermissionTable();
            return;
        }

        $pdo = Database::connection();
        $columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
        $definitions = [
            'report_daily_enabled' => 'ALTER TABLE users ADD COLUMN report_daily_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
            'report_weekly_enabled' => 'ALTER TABLE users ADD COLUMN report_weekly_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER report_daily_enabled',
            'report_monthly_enabled' => 'ALTER TABLE users ADD COLUMN report_monthly_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER report_weekly_enabled',
            'mobile_notification_enabled' => 'ALTER TABLE users ADD COLUMN mobile_notification_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER report_monthly_enabled',
            'mobile_notification_entry_enabled' => 'ALTER TABLE users ADD COLUMN mobile_notification_entry_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER mobile_notification_enabled',
            'mobile_notification_exit_enabled' => 'ALTER TABLE users ADD COLUMN mobile_notification_exit_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER mobile_notification_entry_enabled',
            'mobile_notification_department_enabled' => 'ALTER TABLE users ADD COLUMN mobile_notification_department_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER mobile_notification_exit_enabled',
            'mobile_notification_external_movement_enabled' => 'ALTER TABLE users ADD COLUMN mobile_notification_external_movement_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER mobile_notification_department_enabled',
        ];

        foreach ($definitions as $column => $sql) {
            if (!in_array($column, $columns, true)) {
                $pdo->exec($sql);
                $columns[] = $column;
            }
        }

        $this->ensurePanelPermissionTable();

        self::$reportColumnsEnsured = true;
    }

    private function effectivePanelPermissions(int $userId): array
    {
        $this->ensurePanelPermissionTable();

        $options = $this->panelPermissionOptions();
        $codes = array_column($options, 'code');
        $rolePermissions = $this->rolePermissionCodes($userId);
        $isAdmin = in_array('admin', $this->roleCodes($userId), true);
        $overrides = $this->panelPermissionOverrides($userId);
        $permissions = [];

        foreach ($codes as $code) {
            if (array_key_exists($code, $overrides)) {
                $permissions[$code] = (bool) $overrides[$code];
                continue;
            }

            $legacyCode = $this->legacyPanelPermission($code);
            $permissions[$code] = $isAdmin
                || in_array($code, $rolePermissions, true)
                || ($legacyCode !== null && in_array($legacyCode, $rolePermissions, true));
        }

        return $permissions;
    }

    private function savePanelPermissionOverrides(int $userId, array $allowedCodes): void
    {
        $allowedCodes = array_values(array_unique(array_map('strval', $allowedCodes)));
        $validCodes = array_column($this->panelPermissionOptions(), 'code');
        $stmt = Database::connection()->prepare(
            'INSERT INTO user_permission_overrides (user_id, permission_code, is_allowed)
             VALUES (:user_id, :permission_code, :is_allowed)
             ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)'
        );

        foreach ($validCodes as $code) {
            $stmt->execute([
                'user_id' => $userId,
                'permission_code' => $code,
                'is_allowed' => in_array($code, $allowedCodes, true) ? 1 : 0,
            ]);
        }
    }

    private function panelPermissionOverrides(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT permission_code, is_allowed
             FROM user_permission_overrides
             WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);

        $overrides = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $overrides[(string) $row['permission_code']] = (int) $row['is_allowed'] === 1;
        }

        return $overrides;
    }

    private function rolePermissionCodes(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT p.code
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id
             ORDER BY p.code'
        );
        $stmt->execute(['user_id' => $userId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function roleCodes(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT r.code
             FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id
             ORDER BY r.code'
        );
        $stmt->execute(['user_id' => $userId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function legacyPanelPermission(string $permissionCode): ?string
    {
        return [
            'dashboard.block.door' => 'dashboard.view',
            'dashboard.block.inside' => 'dashboard.view',
            'dashboard.block.activity' => 'dashboard.view',
            'dashboard.block.stats' => 'dashboard.view',
            'dashboard.block.verifications' => 'dashboard.view',
            'dashboard.view_settings' => 'dashboard.view',
            'external_movements.view' => 'visits.view_all',
            'external_movements.create_exit' => 'visits.create_entry',
            'external_movements.create_return' => 'visits.create_exit',
            'visitors.manage' => 'users.manage',
            'watchlist.manage' => 'users.manage',
            'notification_rules.manage' => 'notifications.manage',
            'mail_templates.manage' => 'notifications.manage',
            'notification_channels.manage' => 'notifications.manage',
        ][$permissionCode] ?? null;
    }

    private function ensurePanelPermissionTable(): void
    {
        if (self::$panelPermissionTableEnsured) {
            return;
        }

        Database::connection()->exec(
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

        self::$panelPermissionTableEnsured = true;
    }

    private function roles(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.code, r.name
             FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id
             ORDER BY r.name'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function roleIdByCode(string $code): ?int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    private function departmentIdByCode(string $code): ?int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM departments WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }
}
