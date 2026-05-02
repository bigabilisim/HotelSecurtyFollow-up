<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Backup;
use App\Services\BackupService;

$pdo = Database::connection();
$backup = new Backup();
$backup->ensureMailColumns();

$job = $pdo->query('SELECT * FROM backup_jobs ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$job) {
    throw new RuntimeException('Yedek işi bulunamadı.');
}

$mail = 'backup.smoke.' . date('His') . '@example.test';
$filePath = null;

try {
    $backup->updateJob([
        'id' => (int) $job['id'],
        'frequency' => 'daily',
        'run_time' => '23:30:00',
        'retention_days' => 30,
        'include_database' => true,
        'include_uploads' => false,
        'mail_enabled' => true,
        'mail_recipient_name' => 'Backup Smoke',
        'mail_recipient_email' => $mail,
        'is_active' => true,
    ]);

    $result = (new BackupService())->runJob((int) $job['id']);
    $filePath = (string) ($result['file_path'] ?? '');

    if ($filePath === '' || !is_file($filePath)) {
        throw new RuntimeException('Yedek zip dosyası oluşmadı.');
    }

    if ((int) ($result['mail_queued'] ?? 0) !== 1) {
        throw new RuntimeException('Yedek mail kuyruğu oluşmadı.');
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Yedek zip dosyası açılamadı.');
    }

    if ($zip->locateName('database/live-dump.sql') === false) {
        $zip->close();
        throw new RuntimeException('Yedek zip içinde canlı veritabanı dump dosyası yok.');
    }
    $zip->close();

    $notificationStmt = $pdo->prepare(
        'SELECT id, attachment_path, attachment_name
         FROM notification_logs
         WHERE recipient_address = :mail
           AND status = "queued"
         ORDER BY id DESC
         LIMIT 1'
    );
    $notificationStmt->execute(['mail' => $mail]);
    $notification = $notificationStmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification || (string) $notification['attachment_path'] !== $filePath || !str_ends_with((string) $notification['attachment_name'], '.zip')) {
        throw new RuntimeException('Yedek mail logu zip ekiyle oluşmadı.');
    }

    echo "Yedek zip: OK\n";
    echo "Canlı DB dump: OK\n";
    echo "Yedek mail kuyruğu: OK\n";
} finally {
    $restore = $pdo->prepare(
        'UPDATE backup_jobs
         SET frequency = :frequency,
             run_time = :run_time,
             retention_days = :retention_days,
             include_database = :include_database,
             include_uploads = :include_uploads,
             mail_enabled = :mail_enabled,
             mail_recipient_name = :mail_recipient_name,
             mail_recipient_email = :mail_recipient_email,
             is_active = :is_active,
             last_run_at = :last_run_at,
             next_run_at = :next_run_at
         WHERE id = :id'
    );
    $restore->execute([
        'id' => (int) $job['id'],
        'frequency' => $job['frequency'],
        'run_time' => $job['run_time'],
        'retention_days' => (int) $job['retention_days'],
        'include_database' => (int) $job['include_database'],
        'include_uploads' => (int) $job['include_uploads'],
        'mail_enabled' => (int) ($job['mail_enabled'] ?? 0),
        'mail_recipient_name' => $job['mail_recipient_name'] ?? null,
        'mail_recipient_email' => $job['mail_recipient_email'] ?? null,
        'is_active' => (int) $job['is_active'],
        'last_run_at' => $job['last_run_at'] ?? null,
        'next_run_at' => $job['next_run_at'] ?? null,
    ]);

    $pdo->prepare('DELETE FROM notification_logs WHERE recipient_address = :mail')->execute(['mail' => $mail]);

    if ($filePath) {
        $pdo->prepare('DELETE FROM backup_logs WHERE file_path = :file_path')->execute(['file_path' => $filePath]);
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
}

echo "Yedek mail smoke test tamamlandı.\n";
