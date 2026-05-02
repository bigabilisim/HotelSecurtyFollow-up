<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Backup;
use App\Services\Notifications\NotificationService;
use PDO;
use ZipArchive;

final class BackupService
{
    public function runDue(int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM backup_jobs
             WHERE is_active = 1
               AND (next_run_at IS NULL OR next_run_at <= NOW())
             ORDER BY COALESCE(next_run_at, created_at) ASC
             LIMIT ' . max(1, min($limit, 20))
        );
        $stmt->execute();

        $summary = ['processed' => 0, 'success' => 0, 'failed' => 0, 'mail_queued' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $job) {
            $summary['processed']++;
            try {
                $result = $this->runJob((int) $job['id']);
                $summary['success']++;
                $summary['mail_queued'] += (int) ($result['mail_queued'] ?? 0);
            } catch (\Throwable) {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    public function runJob(int $jobId, ?int $userId = null): array
    {
        $job = $this->job($jobId);
        if (!$job) {
            throw new \RuntimeException('Yedek işi bulunamadı.');
        }

        $logId = $this->startLog((int) $job['id'], $userId);

        try {
            $filePath = $this->createArchive($job);
            $size = is_file($filePath) ? filesize($filePath) : null;
            $checksum = is_file($filePath) ? hash_file('sha256', $filePath) : null;
            $this->finishLog($logId, $filePath, $size ?: null, $checksum ?: null);
            $this->touchJob($job);
            $this->applyRetention((int) $job['retention_days']);
            $mailQueued = $this->queueBackupMail($job, $filePath, $size ?: null, $checksum ?: null);

            return ['file_path' => $filePath, 'size' => $size, 'checksum' => $checksum, 'mail_queued' => $mailQueued];
        } catch (\Throwable $error) {
            $this->failLog($logId, $error->getMessage());
            throw $error;
        }
    }

    public function stageRestore(string $filePath, ?int $userId = null): string
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException('Yedek dosyası bulunamadı.');
        }

        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive eklentisi kurulu değil.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Yedek arşivi açılamadı.');
        }

        $target = BASE_PATH . '/storage/restores/restore-' . date('Ymd-His');
        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if ($name === '' || str_starts_with($name, '/') || str_contains($name, '../') || str_contains($name, '..\\')) {
                $zip->close();
                throw new \RuntimeException('Yedek arşivinde güvenli olmayan dosya yolu var.');
            }

            $zip->extractTo($target, [$name]);
        }
        $zip->close();

        $stmt = Database::connection()->prepare(
            "INSERT INTO restore_logs (
                restored_by_user_id,
                source_file_name,
                source_checksum_sha256,
                status,
                notes,
                finished_at
             ) VALUES (
                :user_id,
                :source_file_name,
                :checksum,
                'success',
                :notes,
                NOW()
             )"
        );
        $stmt->execute([
            'user_id' => $userId,
            'source_file_name' => basename($filePath),
            'checksum' => hash_file('sha256', $filePath),
            'notes' => 'Yedek güvenli restore staging klasörüne açıldı: ' . $target,
        ]);

        return $target;
    }

    private function job(int $jobId): ?array
    {
        (new Backup())->ensureMailColumns();

        $stmt = Database::connection()->prepare('SELECT * FROM backup_jobs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        return $job ?: null;
    }

    private function startLog(int $jobId, ?int $userId): int
    {
        $fileName = 'backup-' . date('Ymd-His') . '.zip';
        $stmt = Database::connection()->prepare(
            "INSERT INTO backup_logs (
                job_id,
                file_name,
                file_path,
                status,
                created_by_user_id
             ) VALUES (
                :job_id,
                :file_name,
                :file_path,
                'running',
                :user_id
             )"
        );
        $stmt->execute([
            'job_id' => $jobId,
            'file_name' => $fileName,
            'file_path' => BASE_PATH . '/storage/backups/' . $fileName,
            'user_id' => $userId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function createArchive(array $job): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive eklentisi kurulu değil.');
        }

        $directory = BASE_PATH . '/storage/backups';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $filePath = $directory . '/otel-guvenlik-backup-' . date('Ymd-His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Yedek arşivi oluşturulamadı.');
        }

        $this->addPath($zip, BASE_PATH . '/app', 'app');
        $this->addPath($zip, BASE_PATH . '/config', 'config');
        $this->addPath($zip, BASE_PATH . '/database', 'database');
        $this->addPath($zip, BASE_PATH . '/public', 'public');
        $this->addPath($zip, BASE_PATH . '/docs', 'docs');
        $this->addFileIfExists($zip, BASE_PATH . '/README.md', 'README.md');
        $this->addFileIfExists($zip, BASE_PATH . '/.env', '.env');
        $this->addFileIfExists($zip, BASE_PATH . '/.env.example', '.env.example');

        if ((int) $job['include_database'] === 1) {
            $zip->addFromString('database/live-dump.sql', $this->databaseDump());
        }

        if ((int) $job['include_uploads'] === 1 && is_dir(BASE_PATH . '/storage/uploads')) {
            $this->addPath($zip, BASE_PATH . '/storage/uploads', 'storage/uploads');
        }

        $zip->addFromString('backup-manifest.json', json_encode([
            'created_at' => date('c'),
            'app' => config('app.name', 'Otel Güvenlik Sistemi'),
            'database' => config('database.database', 'hotel_security'),
            'include_database' => (bool) $job['include_database'],
            'database_dump' => (int) $job['include_database'] === 1 ? 'database/live-dump.sql' : null,
            'note' => 'Yedek dosyası hassas .env ve bildirim ayarlarını içerebilir. Güvenilir alıcılara gönderilmelidir.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->close();

        return $filePath;
    }

    private function databaseDump(): string
    {
        $pdo = Database::connection();
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $lines = [
            '-- Otel Güvenlik Sistemi canlı veri yedeği',
            '-- Oluşturma: ' . date('c'),
            'SET FOREIGN_KEY_CHECKS=0;',
            '',
        ];

        foreach ($tables as $table) {
            $table = (string) $table;
            if (!$this->isSafeIdentifier($table)) {
                continue;
            }

            $tableName = $this->quoteIdentifier($table);
            $lines[] = 'DELETE FROM ' . $tableName . ';';

            $stmt = $pdo->query('SELECT * FROM ' . $tableName);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_keys($row);
                $columnSql = implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));
                $valueSql = implode(', ', array_map(fn (mixed $value): string => $this->sqlValue($pdo, $value), array_values($row)));
                $lines[] = 'INSERT INTO ' . $tableName . ' (' . $columnSql . ') VALUES (' . $valueSql . ');';
            }

            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function queueBackupMail(array $job, string $filePath, ?int $size, ?string $checksum): int
    {
        if ((int) ($job['mail_enabled'] ?? 0) !== 1) {
            return 0;
        }

        $email = trim((string) ($job['mail_recipient_email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }

        $recipientName = trim((string) ($job['mail_recipient_name'] ?? ''));
        $fileName = basename($filePath);
        $subject = 'Otel Güvenlik Günlük Sistem Yedeği - ' . date('d.m.Y');
        $message = implode("\n", [
            'Otel Güvenlik Sistemi otomatik yedeği oluşturuldu.',
            '',
            'Yedek işi: ' . (string) ($job['name'] ?? '-'),
            'Dosya: ' . $fileName,
            'Boyut: ' . ($size ? round($size / 1024, 1) . ' KB' : '-'),
            'SHA-256: ' . ($checksum ?: '-'),
            'Oluşturma zamanı: ' . date('d.m.Y H:i:s'),
            '',
            'Not: Bu dosya sistem ayarları ve canlı veritabanı yedeği içerebilir. Güvenli şekilde saklayın.',
        ]);

        return (new NotificationService())->queueMail($email, $recipientName, $subject, $message, $filePath, $fileName);
    }

    private function isSafeIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1;
    }

    private function quoteIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    private function sqlValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $pdo->quote((string) $value);
    }

    private function addPath(ZipArchive $zip, string $path, string $localPath): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $relativePath = $localPath . '/' . substr($absolutePath, strlen($path) + 1);
            $zip->addFile($absolutePath, $relativePath);
        }
    }

    private function addFileIfExists(ZipArchive $zip, string $path, string $localPath): void
    {
        if (is_file($path)) {
            $zip->addFile($path, $localPath);
        }
    }

    private function finishLog(int $logId, string $filePath, ?int $size, ?string $checksum): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE backup_logs
             SET file_name = :file_name,
                 file_path = :file_path,
                 file_size_bytes = :file_size_bytes,
                 checksum_sha256 = :checksum_sha256,
                 status = 'success',
                 finished_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'id' => $logId,
            'file_name' => basename($filePath),
            'file_path' => $filePath,
            'file_size_bytes' => $size,
            'checksum_sha256' => $checksum,
        ]);
    }

    private function failLog(int $logId, string $error): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE backup_logs
             SET status = 'failed',
                 finished_at = NOW(),
                 error_message = :error_message
             WHERE id = :id"
        );
        $stmt->execute([
            'id' => $logId,
            'error_message' => $error,
        ]);
    }

    private function touchJob(array $job): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE backup_jobs
             SET last_run_at = NOW(), next_run_at = :next_run_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => (int) $job['id'],
            'next_run_at' => $this->nextRunAt((string) $job['frequency'], (string) $job['run_time']),
        ]);
    }

    private function nextRunAt(string $frequency, string $runTime): string
    {
        $date = match ($frequency) {
            'hourly' => '+1 hour',
            'weekly' => '+1 week',
            'monthly' => '+1 month',
            default => '+1 day',
        };

        return date('Y-m-d ' . $runTime, strtotime($date));
    }

    private function applyRetention(int $retentionDays): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - ($retentionDays * 86400));
        $stmt = Database::connection()->prepare(
            'SELECT id, file_path
             FROM backup_logs
             WHERE status = "success"
               AND started_at < :cutoff'
        );
        $stmt->bindValue(':cutoff', $cutoff);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
            if (is_file((string) $log['file_path'])) {
                unlink((string) $log['file_path']);
            }

            $update = Database::connection()->prepare("UPDATE backup_logs SET status = 'deleted' WHERE id = :id");
            $update->execute(['id' => (int) $log['id']]);
        }
    }
}
