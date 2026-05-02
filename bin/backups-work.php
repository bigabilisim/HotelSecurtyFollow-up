#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\BackupService;
use App\Services\Notifications\NotificationQueue;

require dirname(__DIR__) . '/app/bootstrap.php';

$limit = 5;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = (int) substr($argument, 8);
    }
}

$summary = (new BackupService())->runDue($limit);
$mail = (new NotificationQueue())->process(max(5, (int) ($summary['mail_queued'] ?? 0) + 3));

echo 'Yedekleme işleyici tamamlandı.' . PHP_EOL;
echo 'İşlenen: ' . $summary['processed'] . PHP_EOL;
echo 'Başarılı: ' . $summary['success'] . PHP_EOL;
echo 'Başarısız: ' . $summary['failed'] . PHP_EOL;
echo 'Mail kuyruğu: ' . ($summary['mail_queued'] ?? 0) . PHP_EOL;
echo 'Mail gönderilen: ' . $mail['sent'] . PHP_EOL;
echo 'Mail başarısız: ' . $mail['failed'] . PHP_EOL;
echo 'Mail atlanan: ' . $mail['skipped'] . PHP_EOL;
