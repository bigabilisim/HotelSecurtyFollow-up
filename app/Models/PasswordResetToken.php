<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class PasswordResetToken
{
    private static bool $tableEnsured = false;

    public function create(int $userId, string $email, int $ttlMinutes = 60): string
    {
        $this->ensureTable();
        $this->expireOpenTokens($userId);

        $token = bin2hex(random_bytes(32));
        $ttlMinutes = max(10, $ttlMinutes);

        $stmt = Database::connection()->prepare(
            'INSERT INTO password_reset_tokens (user_id, email, token_hash, expires_at)
             VALUES (:user_id, :email, :token_hash, DATE_ADD(NOW(), INTERVAL ' . $ttlMinutes . ' MINUTE))'
        );
        $stmt->execute([
            'user_id' => $userId,
            'email' => trim($email),
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }

    public function findValid(string $token): ?array
    {
        $this->ensureTable();
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT
                prt.id,
                prt.user_id,
                prt.email,
                prt.expires_at,
                u.full_name,
                u.username,
                u.status
             FROM password_reset_tokens prt
             INNER JOIN users u ON u.id = prt.user_id
             WHERE prt.token_hash = :token_hash
               AND prt.used_at IS NULL
               AND prt.expires_at > NOW()
               AND u.status = "active"
               AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        return $reset ?: null;
    }

    public function markUsed(int $id): void
    {
        $this->ensureTable();

        $stmt = Database::connection()->prepare(
            'UPDATE password_reset_tokens
             SET used_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function cleanupExpired(): void
    {
        $this->ensureTable();

        Database::connection()->exec(
            'DELETE FROM password_reset_tokens
             WHERE used_at IS NOT NULL
                OR expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)'
        );
    }

    private function expireOpenTokens(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE password_reset_tokens
             SET used_at = NOW()
             WHERE user_id = :user_id
               AND used_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    private function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        Database::connection()->exec(
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

        self::$tableEnsured = true;
    }
}
