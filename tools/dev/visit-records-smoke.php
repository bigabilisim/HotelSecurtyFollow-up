<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\VisitRecord;
use App\Services\Notifications\NotificationService;
use App\Services\VisitRecordPdfService;

$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

$recordModel = new VisitRecord();
$filters = $recordModel->filters([
    'date_from' => date('Y-m-d', strtotime('-7 days')),
    'date_to' => date('Y-m-d'),
]);
$records = $recordModel->records($filters, 100);
$total = $recordModel->count($filters);
$filePath = (new VisitRecordPdfService())->create($records, $filters, $total);

if (!is_file($filePath) || substr((string) file_get_contents($filePath, false, null, 0, 4), 0, 4) !== '%PDF') {
    throw new RuntimeException('PDF dosyasi olusturulamadi.');
}

$recipient = 'visit.records.smoke.' . date('His') . '@otel.local';
$queued = (new NotificationService())->queueMail(
    $recipient,
    'Visit Records Smoke',
    'Kayıtlar PDF Smoke',
    'Kayıtlar PDF smoke testi.',
    $filePath,
    basename($filePath)
);

if ($queued !== 1) {
    @unlink($filePath);
    throw new RuntimeException('PDF mail kuyrugu olusmadi.');
}

$pdo = Database::connection();
$stmt = $pdo->prepare(
    'SELECT id, attachment_path, attachment_name
     FROM notification_logs
     WHERE recipient_address = :recipient
     ORDER BY id DESC
     LIMIT 1'
);
$stmt->execute(['recipient' => $recipient]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log || (string) $log['attachment_path'] !== $filePath || (string) $log['attachment_name'] !== basename($filePath)) {
    @unlink($filePath);
    throw new RuntimeException('PDF eki notification_logs kaydina yazilmadi.');
}

$pdo->prepare('DELETE FROM notification_logs WHERE id = :id')->execute(['id' => (int) $log['id']]);
@unlink($filePath);

echo "Visit records PDF smoke OK\n";
