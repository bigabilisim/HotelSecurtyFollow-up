<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class UserSuggestion
{
    private static bool $tableEnsured = false;

    private array $types = [
        'improvement' => 'İyileştirme',
        'bug' => 'Hata Bildirimi',
        'feature' => 'Yeni Özellik',
        'support' => 'Destek / Eğitim',
    ];

    private array $priorities = [
        'normal' => 'Normal',
        'high' => 'Önemli',
    ];

    private array $statuses = [
        'new' => 'Yeni',
        'reviewing' => 'İnceleniyor',
        'done' => 'Tamamlandı',
        'rejected' => 'Uygun Değil',
    ];

    public function all(array $filters = []): array
    {
        $this->ensureTable();

        $where = ['s.deleted_at IS NULL'];
        $params = [];

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && isset($this->statuses[$status])) {
            $where[] = 's.status = :status';
            $params['status'] = $status;
        }

        $type = (string) ($filters['type'] ?? '');
        if ($type !== '' && isset($this->types[$type])) {
            $where[] = 's.suggestion_type = :type';
            $params['type'] = $type;
        }

        $stmt = Database::connection()->prepare(
            'SELECT
                s.*,
                handler.full_name AS handled_by_name
             FROM user_suggestions s
             LEFT JOIN users handler ON handler.id = s.handled_by_user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY
                CASE s.status
                    WHEN "new" THEN 1
                    WHEN "reviewing" THEN 2
                    WHEN "done" THEN 3
                    ELSE 4
                END,
                s.created_at DESC,
                s.id DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unfinishedCount(): int
    {
        $this->ensureTable();

        $stmt = Database::connection()->query(
            'SELECT COUNT(*)
             FROM user_suggestions
             WHERE deleted_at IS NULL
               AND status IN ("new", "reviewing")'
        );

        return (int) $stmt->fetchColumn();
    }

    public function create(array $data, array $user): int
    {
        $this->ensureTable();

        $type = $this->normalizeOption((string) ($data['suggestion_type'] ?? ''), $this->types, 'improvement');
        $priority = $this->normalizeOption((string) ($data['priority'] ?? ''), $this->priorities, 'normal');
        $title = trim((string) ($data['title'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $pageRoute = $this->cleanRoute((string) ($data['page_route'] ?? ''));

        if ($title === '') {
            throw new \RuntimeException('Öneri konusu zorunludur.');
        }

        if ($message === '') {
            throw new \RuntimeException('Öneri açıklaması zorunludur.');
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO user_suggestions (
                user_id,
                user_name,
                user_email,
                suggestion_type,
                priority,
                title,
                message,
                page_route,
                status
             ) VALUES (
                :user_id,
                :user_name,
                :user_email,
                :suggestion_type,
                :priority,
                :title,
                :message,
                :page_route,
                "new"
             )'
        );
        $stmt->execute([
            'user_id' => (int) ($user['id'] ?? 0) ?: null,
            'user_name' => trim((string) ($user['full_name'] ?? '')) ?: null,
            'user_email' => trim((string) ($user['email'] ?? '')) ?: null,
            'suggestion_type' => $type,
            'priority' => $priority,
            'title' => $this->truncate($title, 160),
            'message' => $message,
            'page_route' => $pageRoute ?: null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function updateStatus(int $id, string $status, int $handlerUserId): bool
    {
        $this->ensureTable();

        $status = $this->normalizeOption($status, $this->statuses, 'new');
        $stmt = Database::connection()->prepare(
            'UPDATE user_suggestions
             SET status = :status,
                 handled_by_user_id = :handled_by_user_id,
                 handled_at = CASE WHEN :handled_status IN ("done", "rejected") THEN NOW() ELSE handled_at END
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'handled_status' => $status,
            'handled_by_user_id' => $handlerUserId > 0 ? $handlerUserId : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $this->ensureTable();

        $stmt = Database::connection()->prepare(
            'UPDATE user_suggestions
             SET deleted_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function typeOptions(): array
    {
        return $this->types;
    }

    public function priorityOptions(): array
    {
        return $this->priorities;
    }

    public function statusOptions(): array
    {
        return $this->statuses;
    }

    public function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS user_suggestions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                user_name VARCHAR(160) NULL,
                user_email VARCHAR(180) NULL,
                suggestion_type ENUM("improvement", "bug", "feature", "support") NOT NULL DEFAULT "improvement",
                priority ENUM("normal", "high") NOT NULL DEFAULT "normal",
                title VARCHAR(160) NOT NULL,
                message TEXT NOT NULL,
                page_route VARCHAR(255) NULL,
                status ENUM("new", "reviewing", "done", "rejected") NOT NULL DEFAULT "new",
                handled_by_user_id BIGINT UNSIGNED NULL,
                handled_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                KEY idx_user_suggestions_user (user_id),
                KEY idx_user_suggestions_status (status, created_at),
                KEY idx_user_suggestions_type (suggestion_type),
                KEY idx_user_suggestions_deleted (deleted_at),
                CONSTRAINT fk_user_suggestions_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_user_suggestions_handler
                    FOREIGN KEY (handled_by_user_id) REFERENCES users(id)
                    ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$tableEnsured = true;
    }

    private function normalizeOption(string $value, array $options, string $default): string
    {
        return isset($options[$value]) ? $value : $default;
    }

    private function cleanRoute(string $route): string
    {
        $route = trim($route);
        if ($route === '' || !str_starts_with($route, '/')) {
            return '';
        }

        return $this->truncate($route, 255);
    }

    private function truncate(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length, 'UTF-8');
        }

        return substr($value, 0, $length);
    }
}
