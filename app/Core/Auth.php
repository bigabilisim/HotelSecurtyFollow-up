<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use PDO;

final class Auth
{
    private static ?array $permissions = null;
    private static ?array $permissionOverrides = null;
    private static ?array $roles = null;

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        if (!isset($_SESSION['user'])) {
            $_SESSION['user'] = (new User())->find((int) $_SESSION['user_id']);
        }

        return $_SESSION['user'];
    }

    public static function attempt(string $username, string $password): bool
    {
        $userModel = new User();
        $user = $userModel->findByUsername($username);

        if (!$user || $user['status'] !== 'active') {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user'] = $userModel->find((int) $user['id']);
        unset($_SESSION['permissions']);
        $userModel->touchLastLogin((int) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function hasRole(string $roleCode): bool
    {
        if (!self::check()) {
            return false;
        }

        return in_array($roleCode, self::roleCodes(), true);
    }

    public static function can(string $permissionCode): bool
    {
        if (!self::check()) {
            return false;
        }

        $override = self::permissionOverride($permissionCode);
        if ($override !== null) {
            return $override;
        }

        $permissions = self::permissions();

        if (in_array('*', $permissions, true) || in_array($permissionCode, $permissions, true)) {
            return true;
        }

        $legacyPermission = self::legacyPermission($permissionCode);

        return $legacyPermission !== null && in_array($legacyPermission, $permissions, true);
    }

    public static function canAny(array $permissionCodes): bool
    {
        foreach ($permissionCodes as $permissionCode) {
            if (self::can((string) $permissionCode)) {
                return true;
            }
        }

        return false;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/login');
        }
    }

    public static function requirePermission(string $permissionCode): void
    {
        self::requireLogin();

        if (!self::can($permissionCode)) {
            self::forbidden();
        }
    }

    public static function requireAny(array $permissionCodes): void
    {
        self::requireLogin();

        if (!self::canAny($permissionCodes)) {
            self::forbidden();
        }
    }

    private static function permissions(): array
    {
        if (self::$permissions === null) {
            self::$permissions = self::loadPermissions((int) self::id());
        }

        return self::$permissions;
    }

    private static function permissionOverride(string $permissionCode): ?bool
    {
        $overrides = self::permissionOverrides();
        if (!array_key_exists($permissionCode, $overrides)) {
            return null;
        }

        return (bool) $overrides[$permissionCode];
    }

    private static function permissionOverrides(): array
    {
        if (self::$permissionOverrides !== null) {
            return self::$permissionOverrides;
        }

        $userId = (int) self::id();
        if ($userId <= 0) {
            self::$permissionOverrides = [];

            return self::$permissionOverrides;
        }

        self::ensurePermissionOverrideTable();

        $stmt = Database::connection()->prepare(
            'SELECT permission_code, is_allowed
             FROM user_permission_overrides
             WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);

        self::$permissionOverrides = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            self::$permissionOverrides[(string) $row['permission_code']] = (int) $row['is_allowed'] === 1;
        }

        return self::$permissionOverrides;
    }

    private static function loadPermissions(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        if (in_array('admin', self::roleCodes(), true)) {
            return ['*'];
        }

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

    private static function roleCodes(): array
    {
        if (self::$roles !== null) {
            return self::$roles;
        }

        $userId = (int) self::id();
        if ($userId <= 0) {
            self::$roles = [];

            return self::$roles;
        }

        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT r.code
             FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id
             ORDER BY r.code'
        );
        $stmt->execute(['user_id' => $userId]);
        self::$roles = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return self::$roles;
    }

    private static function legacyPermission(string $permissionCode): ?string
    {
        return [
            'visitors.manage' => 'users.manage',
            'watchlist.manage' => 'users.manage',
            'notification_rules.manage' => 'notifications.manage',
            'mail_templates.manage' => 'notifications.manage',
            'notification_channels.manage' => 'notifications.manage',
        ][$permissionCode] ?? null;
    }

    private static function ensurePermissionOverrideTable(): void
    {
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
    }

    private static function forbidden(): never
    {
        http_response_code(403);
        echo view('errors/403', ['title' => 'Yetkisiz Erişim']);
        exit;
    }
}
