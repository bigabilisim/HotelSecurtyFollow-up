<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportService;

$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

(new User())->ensureReportColumns();
(new Report())->ensureReady();

$pdo = Database::connection();
$scheduleId = (int) $pdo->query("SELECT id FROM report_schedules WHERE report_type = 'weekly' ORDER BY id LIMIT 1")->fetchColumn();
$userId = (int) $pdo->query("SELECT id FROM users WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();

if ($scheduleId < 1) {
    throw new RuntimeException('Haftalik rapor plani bulunamadi.');
}

if ($userId < 1) {
    throw new RuntimeException('Test icin kullanici bulunamadi.');
}

$pdo->beginTransaction();

try {
    $email = 'report.smoke.' . date('His') . '@otel.local';
    $pdo->prepare('UPDATE report_schedules SET output_format = "pdf" WHERE id = :id')
        ->execute(['id' => $scheduleId]);

    $pdo->prepare(
        'UPDATE users
         SET email = :email,
             status = "active",
             report_daily_enabled = 0,
             report_weekly_enabled = 1,
             report_monthly_enabled = 0
         WHERE id = :id'
    )->execute([
        'email' => $email,
        'id' => $userId,
    ]);

    $result = (new ReportService())->runSchedule($scheduleId);

    if (strtolower(pathinfo((string) $result['file_path'], PATHINFO_EXTENSION)) !== 'pdf') {
        throw new RuntimeException('PDF secili rapor PDF dosyasi uretmedi.');
    }

    if (!is_file((string) $result['file_path']) || substr((string) file_get_contents((string) $result['file_path'], false, null, 0, 4), 0, 4) !== '%PDF') {
        throw new RuntimeException('Rapor PDF dosyasi gecersiz.');
    }

    $stmt = $pdo->prepare(
        "SELECT subject, message, attachment_path, attachment_name
         FROM notification_logs
         WHERE recipient_address = :email
           AND visit_id IS NULL
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute(['email' => $email]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        throw new RuntimeException('Kullanici rapor aboneligi mail kuyrugu olusturmadi.');
    }

    if (!str_contains((string) $log['subject'], 'haftalık') && !str_contains((string) $log['subject'], 'Haftalık')) {
        throw new RuntimeException('Haftalik rapor mail konusu beklenen metni icermiyor.');
    }

    if (!str_contains((string) $log['message'], 'Özet') && !str_contains((string) $log['message'], 'Ozet')) {
        throw new RuntimeException('Haftalik rapor mail govdesi beklenen ozeti icermiyor.');
    }

    if ((string) $log['attachment_path'] !== (string) $result['file_path'] || (string) $log['attachment_name'] !== basename((string) $result['file_path'])) {
        throw new RuntimeException('PDF rapor mail ek bilgisi notification_logs kaydina yazilmadi.');
    }

    if (!empty($result['file_path']) && is_file((string) $result['file_path'])) {
        unlink((string) $result['file_path']);
    }

    $pdo->rollBack();
    echo "Report subscription smoke OK\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $error;
}
