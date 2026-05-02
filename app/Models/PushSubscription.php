<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use Minishlink\WebPush\VAPID;
use PDO;

final class PushSubscription
{
    private static bool $ready = false;

    public function publicKey(): string
    {
        $this->ensureReady();

        return (string) ((new Settings())->all()['web_push.vapid_public_key'] ?? '');
    }

    public function auth(): array
    {
        $this->ensureReady();

        $settings = (new Settings())->all();

        return [
            'VAPID' => [
                'subject' => (string) ($settings['web_push.vapid_subject'] ?? $this->defaultSubject()),
                'publicKey' => (string) ($settings['web_push.vapid_public_key'] ?? ''),
                'privateKey' => (string) ($settings['web_push.vapid_private_key'] ?? ''),
            ],
        ];
    }

    public function saveForUser(int $userId, array $subscription, string $userAgent = ''): void
    {
        $this->ensureReady();

        $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
        $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
        $publicKey = trim((string) ($keys['p256dh'] ?? $subscription['publicKey'] ?? ''));
        $authToken = trim((string) ($keys['auth'] ?? $subscription['authToken'] ?? ''));
        $contentEncoding = trim((string) ($subscription['contentEncoding'] ?? 'aes128gcm')) ?: 'aes128gcm';

        if ($userId <= 0 || $endpoint === '' || $publicKey === '' || $authToken === '') {
            throw new \InvalidArgumentException('Push abonelik bilgisi eksik.');
        }

        if (!in_array($contentEncoding, ['aes128gcm', 'aesgcm'], true)) {
            $contentEncoding = 'aes128gcm';
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO push_subscriptions (
                user_id,
                endpoint_hash,
                endpoint,
                public_key,
                auth_token,
                content_encoding,
                user_agent,
                status,
                last_seen_at
             ) VALUES (
                :user_id,
                :endpoint_hash,
                :endpoint,
                :public_key,
                :auth_token,
                :content_encoding,
                :user_agent,
                "active",
                NOW()
             )
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                endpoint = VALUES(endpoint),
                public_key = VALUES(public_key),
                auth_token = VALUES(auth_token),
                content_encoding = VALUES(content_encoding),
                user_agent = VALUES(user_agent),
                status = "active",
                last_seen_at = NOW(),
                deleted_at = NULL,
                error_message = NULL'
        );
        $stmt->execute([
            'user_id' => $userId,
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'public_key' => $publicKey,
            'auth_token' => $authToken,
            'content_encoding' => $contentEncoding,
            'user_agent' => mb_substr($userAgent, 0, 255),
        ]);
    }

    public function removeForUser(int $userId, string $endpoint): void
    {
        $this->ensureReady();

        if ($userId <= 0 || trim($endpoint) === '') {
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE push_subscriptions
             SET status = "expired", deleted_at = NOW()
             WHERE user_id = :user_id AND endpoint_hash = :endpoint_hash'
        );
        $stmt->execute([
            'user_id' => $userId,
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);
    }

    public function activeForUsers(array $userIds): array
    {
        $this->ensureReady();

        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT id, user_id, endpoint, public_key, auth_token, content_encoding
             FROM push_subscriptions
             WHERE status = "active"
               AND deleted_at IS NULL
               AND user_id IN (' . $placeholders . ')
             ORDER BY last_seen_at DESC, id DESC'
        );
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markSuccess(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE push_subscriptions
             SET last_success_at = NOW(), error_message = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function markError(int $id, string $message, bool $expired = false): void
    {
        if ($id <= 0) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE push_subscriptions
             SET status = :status,
                 last_error_at = NOW(),
                 error_message = :error_message,
                 deleted_at = IF(:expired = 1, NOW(), deleted_at)
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => $expired ? 'expired' : 'active',
            'error_message' => mb_substr($message, 0, 500),
            'expired' => $expired ? 1 : 0,
        ]);
    }

    public function ensureReady(): void
    {
        if (self::$ready) {
            return;
        }

        $pdo = Database::connection();
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

        $this->ensureVapidKeys();
        self::$ready = true;
    }

    private function ensureVapidKeys(): void
    {
        $settingsModel = new Settings();
        $settings = $settingsModel->all();

        $publicKey = trim((string) ($settings['web_push.vapid_public_key'] ?? ''));
        $privateKey = trim((string) ($settings['web_push.vapid_private_key'] ?? ''));
        $subject = trim((string) ($settings['web_push.vapid_subject'] ?? ''));

        if ($publicKey !== '' && $privateKey !== '' && $subject !== '') {
            return;
        }

        $keys = VAPID::createVapidKeys();
        $settingsModel->setMany([
            'web_push.vapid_public_key' => $keys['publicKey'],
            'web_push.vapid_private_key' => $keys['privateKey'],
            'web_push.vapid_subject' => $subject !== '' ? $subject : $this->defaultSubject(),
        ]);
    }

    private function defaultSubject(): string
    {
        $appUrl = trim((string) config('app.url', ''));
        if ($appUrl !== '' && filter_var($appUrl, FILTER_VALIDATE_URL)) {
            return $appUrl;
        }

        return 'mailto:info@bigabilisim.com';
    }
}
