<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Report;
use App\Services\Notifications\NotificationQueue;
use App\Services\ReportService;

$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

(new Report())->ensureReady();

$pdo = Database::connection();
$scheduleId = (int) $pdo->query("SELECT id FROM report_schedules WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
if ($scheduleId < 1) {
    throw new RuntimeException('Rapor plani bulunamadi.');
}

$channel = $pdo->query("SELECT id, config_json FROM notification_channels WHERE code = 'mail' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$channel) {
    throw new RuntimeException('Mail kanali bulunamadi.');
}

$pdo->beginTransaction();

try {
    $config = json_decode((string) $channel['config_json'], true);
    $config = is_array($config) ? $config : [];
    $config['configured'] = false;
    $pdo->prepare('UPDATE notification_channels SET config_json = :config_json WHERE id = :id')->execute([
        'id' => (int) $channel['id'],
        'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $result = (new ReportService())->runSchedule($scheduleId);
    $processed = (new NotificationQueue())->process(max(10, (int) $result['notifications'] + 5));

    if ((int) $result['notifications'] < 1) {
        throw new RuntimeException('Rapor bildirimi kuyruga eklenmedi.');
    }

    if ((int) $processed['processed'] < 1 || (int) $processed['skipped'] < 1) {
        throw new RuntimeException('Rapor calistirma sonrasi kuyruk hemen islenmedi.');
    }

    if (!empty($result['file_path']) && is_file((string) $result['file_path'])) {
        unlink((string) $result['file_path']);
    }

    $pdo->rollBack();
    echo "Report run immediate smoke OK\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $error;
}
