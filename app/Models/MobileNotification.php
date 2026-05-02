<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class MobileNotification
{
    private static bool $ready = false;

    public function queueVisitEntry(int $visitId): int
    {
        return $this->queueVisitEvent('entry', $visitId);
    }

    public function queueVisitExit(int $visitId): int
    {
        return $this->queueVisitEvent('exit', $visitId);
    }

    public function queueVisitEvent(string $eventType, int $visitId): int
    {
        $this->ensureReady();

        $eventType = $eventType === 'exit' ? 'exit' : 'entry';
        $visit = $this->visit($visitId);
        if (!$visit) {
            return 0;
        }

        $recipients = $this->recipients($eventType);
        if (!$recipients) {
            return 0;
        }

        $title = $eventType === 'exit' ? 'Yeni çıkış kaydı' : 'Yeni giriş kaydı';
        $message = trim(implode(' · ', array_filter([
            (string) ($visit['visitor_name'] ?? ''),
            (string) ($visit['category_name'] ?? ''),
            (string) ($visit['department_name'] ?? ''),
        ], static fn (string $part): bool => $part !== '')));

        if ($message === '') {
            $message = $eventType === 'exit'
                ? 'Otelden yeni çıkış kaydı oluşturuldu.'
                : 'Otele yeni giriş kaydı oluşturuldu.';
        }

        $url = '/index.php?route=%2Fdashboard';
        $stmt = Database::connection()->prepare(
            'INSERT INTO mobile_notification_logs (
                user_id,
                visit_id,
                title,
                message,
                target_url,
                event_type,
                status
             ) VALUES (
                :user_id,
                :visit_id,
                :title,
                :message,
                :target_url,
                :event_type,
                "queued"
             )'
        );

        $queued = 0;
        foreach ($recipients as $recipient) {
            $stmt->execute([
                'user_id' => (int) $recipient['id'],
                'visit_id' => $visitId,
                'title' => $title,
                'message' => $message,
                'target_url' => $url,
                'event_type' => $eventType,
            ]);
            $queued++;
        }

        return $queued;
    }

    public function pullForUser(int $userId, int $limit = 10): array
    {
        $this->ensureReady();

        if ($userId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 25));
        $stmt = Database::connection()->prepare(
            'SELECT id, event_type, title, message, target_url, created_at
             FROM mobile_notification_logs
             WHERE user_id = :user_id
               AND status = "queued"
               AND deleted_at IS NULL
             ORDER BY created_at ASC, id ASC
             LIMIT ' . $limit
        );
        $stmt->execute(['user_id' => $userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($notifications) {
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $notifications);
            $this->markDelivered($userId, $ids);
        }

        return $notifications;
    }

    public function queuedLogsForVisit(int $visitId, ?string $eventType = null, int $limit = 100): array
    {
        $this->ensureReady();

        if ($visitId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 250));
        $eventSql = '';
        $params = ['visit_id' => $visitId];
        if ($eventType !== null) {
            $eventSql = ' AND nl.event_type = :event_type';
            $params['event_type'] = $eventType === 'exit' ? 'exit' : 'entry';
        }

        $stmt = Database::connection()->prepare(
            'SELECT
                nl.id,
                nl.user_id,
                nl.visit_id,
                nl.event_type,
                nl.title,
                nl.message,
                nl.target_url,
                nl.created_at
             FROM mobile_notification_logs nl
             INNER JOIN users u ON u.id = nl.user_id
             WHERE nl.visit_id = :visit_id
               AND nl.status = "queued"
               AND nl.deleted_at IS NULL
               ' . $eventSql . '
               AND u.status = "active"
               AND u.deleted_at IS NULL
               AND u.mobile_notification_enabled = 1
             ORDER BY nl.created_at ASC, nl.id ASC
             LIMIT ' . $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markDeliveredByLogIds(array $ids): void
    {
        $this->ensureReady();

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if (!$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            'UPDATE mobile_notification_logs
             SET status = "delivered", delivered_at = NOW()
             WHERE status = "queued"
               AND id IN (' . $placeholders . ')'
        );
        $stmt->execute($ids);
    }

    public function ensureReady(): void
    {
        if (self::$ready) {
            return;
        }

        $pdo = Database::connection();
        $columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
        $userDefinitions = [
            'mobile_notification_enabled' => 'ALTER TABLE users ADD COLUMN mobile_notification_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER report_monthly_enabled',
            'mobile_notification_entry_enabled' => 'ALTER TABLE users ADD COLUMN mobile_notification_entry_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER mobile_notification_enabled',
            'mobile_notification_exit_enabled' => 'ALTER TABLE users ADD COLUMN mobile_notification_exit_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER mobile_notification_entry_enabled',
        ];

        foreach ($userDefinitions as $column => $sql) {
            if (!in_array($column, $columns, true)) {
                $pdo->exec($sql);
                $columns[] = $column;
            }
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

        $logColumns = $pdo->query('SHOW COLUMNS FROM mobile_notification_logs')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('event_type', $logColumns, true)) {
            $pdo->exec('ALTER TABLE mobile_notification_logs ADD COLUMN event_type ENUM("entry", "exit") NOT NULL DEFAULT "entry" AFTER target_url');
        }

        self::$ready = true;
    }

    private function visit(int $visitId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                v.id,
                vi.full_name AS visitor_name,
                vi.vehicle_plate,
                vc.name AS category_name,
                d.name AS department_name
             FROM visits v
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             LEFT JOIN departments d ON d.id = v.department_id
             WHERE v.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $visitId]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);

        return $visit ?: null;
    }

    private function recipients(string $eventType): array
    {
        $eventColumn = $eventType === 'exit'
            ? 'mobile_notification_exit_enabled'
            : 'mobile_notification_entry_enabled';
        $stmt = Database::connection()->query(
            'SELECT id, full_name
             FROM users
             WHERE deleted_at IS NULL
               AND status = "active"
               AND mobile_notification_enabled = 1
               AND ' . $eventColumn . ' = 1
             ORDER BY full_name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function markDelivered(int $userId, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if (!$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            'UPDATE mobile_notification_logs
             SET status = "delivered", delivered_at = NOW()
             WHERE user_id = ?
               AND status = "queued"
               AND id IN (' . $placeholders . ')'
        );
        $stmt->execute(array_merge([$userId], $ids));
    }
}
