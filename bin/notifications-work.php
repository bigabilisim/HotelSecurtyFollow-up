#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\Notifications\NotificationQueue;

require dirname(__DIR__) . '/app/bootstrap.php';

$limit = 20;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = (int) substr($argument, 8);
    }
}

$summary = (new NotificationQueue())->process($limit);

echo 'Bildirim kuyruğu işlendi.' . PHP_EOL;
echo 'Toplam: ' . $summary['processed'] . PHP_EOL;
echo 'Gönderilen: ' . $summary['sent'] . PHP_EOL;
echo 'Başarısız: ' . $summary['failed'] . PHP_EOL;
echo 'Atlanan: ' . $summary['skipped'] . PHP_EOL;
