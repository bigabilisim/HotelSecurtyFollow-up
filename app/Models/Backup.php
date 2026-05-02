<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Backup
{
    private static bool $mailColumnsEnsured = false;

    public function jobs(): array
    {
        $this->ensureMailColumns();

        $stmt = Database::connection()->query(
            'SELECT *
             FROM backup_jobs
             ORDER BY is_active DESC, name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function logs(int $limit = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT bl.*, bj.name AS job_name
             FROM backup_logs bl
             LEFT JOIN backup_jobs bj ON bj.id = bl.job_id
             ORDER BY bl.started_at DESC
             LIMIT ' . max(1, min($limit, 100))
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLog(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT bl.*, bj.name AS job_name
             FROM backup_logs bl
             LEFT JOIN backup_jobs bj ON bj.id = bl.job_id
             WHERE bl.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        return $log ?: null;
    }

    public function updateJob(array $data): void
    {
        $this->ensureMailColumns();

        $stmt = Database::connection()->prepare(
            'UPDATE backup_jobs
             SET frequency = :frequency,
                 run_time = :run_time,
                 retention_days = :retention_days,
                 include_database = :include_database,
                 include_uploads = :include_uploads,
                 mail_enabled = :mail_enabled,
                 mail_recipient_name = :mail_recipient_name,
                 mail_recipient_email = :mail_recipient_email,
                 is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => (int) $data['id'],
            'frequency' => $data['frequency'] ?: 'daily',
            'run_time' => $data['run_time'] ?: '23:30:00',
            'retention_days' => max(1, (int) ($data['retention_days'] ?? 30)),
            'include_database' => !empty($data['include_database']) ? 1 : 0,
            'include_uploads' => !empty($data['include_uploads']) ? 1 : 0,
            'mail_enabled' => !empty($data['mail_enabled']) ? 1 : 0,
            'mail_recipient_name' => trim((string) ($data['mail_recipient_name'] ?? '')) ?: null,
            'mail_recipient_email' => trim((string) ($data['mail_recipient_email'] ?? '')) ?: null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
    }

    public function ensureMailColumns(): void
    {
        if (self::$mailColumnsEnsured) {
            return;
        }

        $pdo = Database::connection();
        $columns = $pdo->query('SHOW COLUMNS FROM backup_jobs')->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('mail_enabled', $columns, true)) {
            $pdo->exec('ALTER TABLE backup_jobs ADD COLUMN mail_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER include_uploads');
        }

        if (!in_array('mail_recipient_name', $columns, true)) {
            $pdo->exec('ALTER TABLE backup_jobs ADD COLUMN mail_recipient_name VARCHAR(160) NULL AFTER mail_enabled');
        }

        if (!in_array('mail_recipient_email', $columns, true)) {
            $pdo->exec('ALTER TABLE backup_jobs ADD COLUMN mail_recipient_email VARCHAR(190) NULL AFTER mail_recipient_name');
        }

        self::$mailColumnsEnsured = true;
    }
}
