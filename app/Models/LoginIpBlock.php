<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class LoginIpBlock
{
    private const FIRST_TEMP_THRESHOLD = 5;
    private const SECOND_PERMANENT_THRESHOLD = 3;
    private const TEMP_BLOCK_MINUTES = 60;

    private static bool $tableEnsured = false;

    public function currentIp(): ?string
    {
        $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        $ip = $forwardedFor !== ''
            ? trim((string) explode(',', $forwardedFor)[0])
            : trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    public function activeBlock(?string $ipAddress): ?array
    {
        $ipAddress = $this->normalizeIp($ipAddress);
        if ($ipAddress === null) {
            return null;
        }

        $this->ensureTable();
        $this->releaseExpiredTemporaryBlocks();
        $row = $this->findByIp($ipAddress);

        if (!$row || !in_array((string) $row['status'], ['temporary', 'permanent'], true)) {
            return null;
        }

        if ((string) $row['status'] === 'temporary' && !$this->isTemporaryStillActive($row)) {
            $this->expireTemporaryBlock((int) $row['id']);
            return null;
        }

        return $this->present($row);
    }

    public function recordFailure(?string $ipAddress, string $username): array
    {
        $ipAddress = $this->normalizeIp($ipAddress);
        if ($ipAddress === null) {
            return [
                'blocked' => false,
                'status' => 'ignored',
                'message' => null,
            ];
        }

        $this->ensureTable();
        $this->releaseExpiredTemporaryBlocks();

        $existingBlock = $this->activeBlock($ipAddress);
        if ($existingBlock !== null) {
            return [
                'blocked' => true,
                'status' => $existingBlock['status'],
                'message' => $this->blockedMessage($existingBlock),
            ];
        }

        $row = $this->findByIp($ipAddress);
        if (!$row) {
            $this->insertFirstFailure($ipAddress, $username);

            return [
                'blocked' => false,
                'status' => 'watching',
                'remaining_attempts' => self::FIRST_TEMP_THRESHOLD - 1,
                'message' => null,
            ];
        }

        $failedCount = (int) $row['failed_attempt_count'] + 1;
        $banCount = (int) $row['ban_count'];
        $threshold = $banCount >= 1 ? self::SECOND_PERMANENT_THRESHOLD : self::FIRST_TEMP_THRESHOLD;
        $userAgent = $this->shortUserAgent();

        if ($failedCount >= $threshold && $banCount >= 1) {
            $stmt = Database::connection()->prepare(
                'UPDATE login_ip_blocks
                 SET failed_attempt_count = :failed_attempt_count,
                     total_failed_attempt_count = total_failed_attempt_count + 1,
                     ban_count = ban_count + 1,
                     status = "permanent",
                     blocked_until = NULL,
                     last_failed_at = NOW(),
                     last_username = :last_username,
                     last_user_agent = :last_user_agent,
                     last_block_reason = :last_block_reason,
                     released_at = NULL,
                     released_by_user_id = NULL,
                     release_note = NULL
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => (int) $row['id'],
                'failed_attempt_count' => $failedCount,
                'last_username' => $this->shortText($username, 180),
                'last_user_agent' => $userAgent,
                'last_block_reason' => 'İkinci deneme serisinde 3 hatalı giriş.',
            ]);

            $block = $this->activeBlock($ipAddress);

            return [
                'blocked' => true,
                'status' => 'permanent',
                'message' => $block ? $this->blockedMessage($block) : 'Bu IP adresi kalıcı olarak bloklandı.',
            ];
        }

        if ($failedCount >= $threshold) {
            $stmt = Database::connection()->prepare(
                'UPDATE login_ip_blocks
                 SET failed_attempt_count = :failed_attempt_count,
                     total_failed_attempt_count = total_failed_attempt_count + 1,
                     ban_count = 1,
                     status = "temporary",
                     blocked_until = DATE_ADD(NOW(), INTERVAL ' . self::TEMP_BLOCK_MINUTES . ' MINUTE),
                     last_failed_at = NOW(),
                     last_username = :last_username,
                     last_user_agent = :last_user_agent,
                     last_block_reason = :last_block_reason,
                     released_at = NULL,
                     released_by_user_id = NULL,
                     release_note = NULL
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => (int) $row['id'],
                'failed_attempt_count' => $failedCount,
                'last_username' => $this->shortText($username, 180),
                'last_user_agent' => $userAgent,
                'last_block_reason' => '5 hatalı giriş sonrası 1 saat geçici ban.',
            ]);

            $block = $this->activeBlock($ipAddress);

            return [
                'blocked' => true,
                'status' => 'temporary',
                'message' => $block ? $this->blockedMessage($block) : 'Bu IP adresi 1 saat süreyle bloklandı.',
            ];
        }

        $stmt = Database::connection()->prepare(
            'UPDATE login_ip_blocks
             SET failed_attempt_count = :failed_attempt_count,
                 total_failed_attempt_count = total_failed_attempt_count + 1,
                 status = "watching",
                 first_failed_at = COALESCE(first_failed_at, NOW()),
                 last_failed_at = NOW(),
                 last_username = :last_username,
                 last_user_agent = :last_user_agent
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => (int) $row['id'],
            'failed_attempt_count' => $failedCount,
            'last_username' => $this->shortText($username, 180),
            'last_user_agent' => $userAgent,
        ]);

        return [
            'blocked' => false,
            'status' => 'watching',
            'remaining_attempts' => max(0, $threshold - $failedCount),
            'message' => null,
        ];
    }

    public function recordSuccess(?string $ipAddress): void
    {
        $ipAddress = $this->normalizeIp($ipAddress);
        if ($ipAddress === null) {
            return;
        }

        $this->ensureTable();
        $stmt = Database::connection()->prepare(
            'UPDATE login_ip_blocks
             SET failed_attempt_count = 0,
                 first_failed_at = NULL,
                 status = CASE WHEN status = "watching" THEN "watching" ELSE status END
             WHERE ip_address = :ip_address
               AND status = "watching"'
        );
        $stmt->execute(['ip_address' => $ipAddress]);
    }

    public function all(): array
    {
        $this->ensureTable();
        $this->releaseExpiredTemporaryBlocks();

        $stmt = Database::connection()->query(
            'SELECT lib.*, u.full_name AS released_by_name
             FROM login_ip_blocks lib
             LEFT JOIN users u ON u.id = lib.released_by_user_id
             ORDER BY FIELD(lib.status, "permanent", "temporary", "watching"),
                      lib.last_failed_at DESC,
                      lib.updated_at DESC'
        );

        return array_map(fn (array $row): array => $this->present($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function release(int $id, ?int $userId, string $note = ''): bool
    {
        $this->ensureTable();

        $stmt = Database::connection()->prepare(
            'UPDATE login_ip_blocks
             SET status = "watching",
                 failed_attempt_count = 0,
                 blocked_until = NULL,
                 first_failed_at = NULL,
                 released_at = NOW(),
                 released_by_user_id = :released_by_user_id,
                 release_note = :release_note
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'released_by_user_id' => $userId,
            'release_note' => $this->shortText($note, 255),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        Database::connection()->exec(
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
                KEY idx_login_ip_blocks_released_by (released_by_user_id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$tableEnsured = true;
    }

    private function insertFirstFailure(string $ipAddress, string $username): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO login_ip_blocks (
                ip_address,
                failed_attempt_count,
                total_failed_attempt_count,
                status,
                first_failed_at,
                last_failed_at,
                last_username,
                last_user_agent
             ) VALUES (
                :ip_address,
                1,
                1,
                "watching",
                NOW(),
                NOW(),
                :last_username,
                :last_user_agent
             )'
        );
        $stmt->execute([
            'ip_address' => $ipAddress,
            'last_username' => $this->shortText($username, 180),
            'last_user_agent' => $this->shortUserAgent(),
        ]);
    }

    private function findByIp(string $ipAddress): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM login_ip_blocks
             WHERE ip_address = :ip_address
             LIMIT 1'
        );
        $stmt->execute(['ip_address' => $ipAddress]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function releaseExpiredTemporaryBlocks(): void
    {
        $this->ensureTable();

        Database::connection()->exec(
            'UPDATE login_ip_blocks
             SET status = "watching",
                 failed_attempt_count = 0,
                 blocked_until = NULL,
                 first_failed_at = NULL,
                 release_note = "Geçici ban süresi otomatik doldu."
             WHERE status = "temporary"
               AND blocked_until IS NOT NULL
               AND blocked_until <= NOW()'
        );
    }

    private function expireTemporaryBlock(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE login_ip_blocks
             SET status = "watching",
                 failed_attempt_count = 0,
                 blocked_until = NULL,
                 first_failed_at = NULL,
                 release_note = "Geçici ban süresi otomatik doldu."
             WHERE id = :id
               AND status = "temporary"'
        );
        $stmt->execute(['id' => $id]);
    }

    private function present(array $row): array
    {
        $row['is_active_block'] = in_array((string) ($row['status'] ?? ''), ['temporary', 'permanent'], true)
            && ((string) ($row['status'] ?? '') === 'permanent' || $this->isTemporaryStillActive($row));
        $row['status_label'] = match ((string) ($row['status'] ?? 'watching')) {
            'temporary' => '1 saat ban',
            'permanent' => 'Kalıcı blok',
            default => 'İzlemede',
        };
        $row['status_class'] = match ((string) ($row['status'] ?? 'watching')) {
            'temporary' => 'warning',
            'permanent' => 'risk',
            default => 'muted',
        };
        $row['remaining_minutes'] = $this->remainingMinutes($row);

        return $row;
    }

    private function blockedMessage(array $block): string
    {
        if ((string) ($block['status'] ?? '') === 'permanent') {
            return 'Bu IP adresi güvenlik nedeniyle kalıcı olarak bloklandı. Sadece admin panelinden açılabilir.';
        }

        $minutes = max(1, (int) ($block['remaining_minutes'] ?? 60));

        return 'Bu IP adresi çok fazla hatalı şifre denemesi nedeniyle geçici olarak bloklandı. Kalan süre: yaklaşık ' . $minutes . ' dakika.';
    }

    private function remainingMinutes(array $row): int
    {
        if ((string) ($row['status'] ?? '') !== 'temporary' || empty($row['blocked_until'])) {
            return 0;
        }

        $seconds = strtotime((string) $row['blocked_until']) - time();

        return $seconds > 0 ? (int) ceil($seconds / 60) : 0;
    }

    private function isTemporaryStillActive(array $row): bool
    {
        return (string) ($row['status'] ?? '') === 'temporary'
            && !empty($row['blocked_until'])
            && strtotime((string) $row['blocked_until']) > time();
    }

    private function normalizeIp(?string $ipAddress): ?string
    {
        $ipAddress = trim((string) $ipAddress);

        return filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : null;
    }

    private function shortUserAgent(): ?string
    {
        $value = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        return $value !== '' ? $this->shortText($value, 255) : null;
    }

    private function shortText(string $value, int $maxLength): string
    {
        $value = trim($value);

        return strlen($value) > $maxLength ? substr($value, 0, $maxLength - 3) . '...' : $value;
    }
}
