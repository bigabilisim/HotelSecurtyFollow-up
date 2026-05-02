<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Report
{
    private static bool $schemaEnsured = false;

    public function schedules(): array
    {
        $this->ensureReady();

        $stmt = Database::connection()->query(
            'SELECT *
             FROM report_schedules
             WHERE deleted_at IS NULL
             ORDER BY report_type, name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSchedule(int $id): ?array
    {
        $this->ensureReady();

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM report_schedules
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        return $schedule ?: null;
    }

    public function logs(int $limit = 25): array
    {
        $this->ensureReady();

        $stmt = Database::connection()->prepare(
            'SELECT rl.*, rs.name AS schedule_name
             FROM report_logs rl
             LEFT JOIN report_schedules rs ON rs.id = rl.schedule_id
             ORDER BY rl.generated_at DESC
             LIMIT ' . max(1, min($limit, 100))
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateSchedule(array $data): void
    {
        $this->saveSchedule($data);
    }

    public function saveSchedule(array $data): int
    {
        $this->ensureReady();

        $id = (int) ($data['id'] ?? 0);
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'report_type' => $this->reportType((string) ($data['report_type'] ?? 'daily')),
            'run_time' => $this->runTime((string) ($data['run_time'] ?? '23:30:00')),
            'day_of_month' => $data['day_of_month'] ? max(1, min((int) $data['day_of_month'], 28)) : null,
            'output_format' => $this->outputFormat((string) ($data['output_format'] ?? 'html')),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];

        if ($payload['name'] === '') {
            throw new \RuntimeException('Rapor adı zorunludur.');
        }

        if ($id > 0) {
            $stmt = Database::connection()->prepare(
                'UPDATE report_schedules
                 SET name = :name,
                     report_type = :report_type,
                     run_time = :run_time,
                     day_of_month = :day_of_month,
                     output_format = :output_format,
                     is_active = :is_active
                 WHERE id = :id
                   AND deleted_at IS NULL'
            );
            $stmt->execute($payload + ['id' => $id]);

            return $id;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO report_schedules (name, report_type, run_time, day_of_month, output_format, is_active, deleted_at)
             VALUES (:name, :report_type, :run_time, :day_of_month, :output_format, :is_active, NULL)
             ON DUPLICATE KEY UPDATE
                report_type = VALUES(report_type),
                run_time = VALUES(run_time),
                day_of_month = VALUES(day_of_month),
                output_format = VALUES(output_format),
                is_active = VALUES(is_active),
                deleted_at = NULL'
        );
        $stmt->execute($payload);

        $insertId = (int) Database::connection()->lastInsertId();
        if ($insertId > 0) {
            return $insertId;
        }

        $existing = Database::connection()->prepare('SELECT id FROM report_schedules WHERE name = :name LIMIT 1');
        $existing->execute(['name' => $payload['name']]);

        return (int) $existing->fetchColumn();
    }

    public function deleteSchedule(int $id): bool
    {
        $this->ensureReady();

        $stmt = Database::connection()->prepare(
            'UPDATE report_schedules
             SET deleted_at = NOW(),
                 is_active = 0
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function ensureReady(): void
    {
        if (!self::$schemaEnsured) {
            $pdo = Database::connection();
            $this->ensureReportEnum($pdo, 'report_schedules');
            $this->ensureReportEnum($pdo, 'report_logs');
            $this->ensureDeletedAtColumn($pdo);
            self::$schemaEnsured = true;
        }

        $this->ensureDefaultSchedules();
    }

    private function ensureReportEnum(\PDO $pdo, string $table): void
    {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE "report_type"');
        $stmt->execute();
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = (string) ($column['Type'] ?? '');

        if (!str_contains($type, 'weekly')) {
            $pdo->exec('ALTER TABLE ' . $table . ' MODIFY report_type ENUM("daily", "weekly", "monthly") NOT NULL');
        }
    }

    private function ensureDeletedAtColumn(\PDO $pdo): void
    {
        $columns = $pdo->query('SHOW COLUMNS FROM report_schedules')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('deleted_at', $columns, true)) {
            $pdo->exec('ALTER TABLE report_schedules ADD COLUMN deleted_at TIMESTAMP NULL AFTER updated_at');
        }
    }

    private function ensureDefaultSchedules(): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO report_schedules (name, report_type, run_time, day_of_month, output_format, is_active)
             VALUES (:name, :report_type, :run_time, :day_of_month, "html", 1)'
        );

        foreach ([
            ['Gün sonu giriş çıkış raporu', 'daily', '23:30:00', null],
            ['Haftalık giriş çıkış raporu', 'weekly', '23:40:00', null],
            ['Ay sonu giriş çıkış raporu', 'monthly', '23:45:00', 1],
        ] as [$name, $type, $runTime, $dayOfMonth]) {
            $stmt->execute([
                'name' => $name,
                'report_type' => $type,
                'run_time' => $runTime,
                'day_of_month' => $dayOfMonth,
            ]);
        }
    }

    private function reportType(string $value): string
    {
        return in_array($value, ['daily', 'weekly', 'monthly'], true) ? $value : 'daily';
    }

    private function outputFormat(string $value): string
    {
        return in_array($value, ['pdf', 'xlsx', 'csv', 'html'], true) ? $value : 'html';
    }

    private function runTime(string $value): string
    {
        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? $value : '23:30:00';
    }
}
