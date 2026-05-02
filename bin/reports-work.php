#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\ReportService;

require dirname(__DIR__) . '/app/bootstrap.php';

$limit = 10;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = (int) substr($argument, 8);
    }
}

$summary = (new ReportService())->runDue($limit);

echo 'Rapor işleyici tamamlandı.' . PHP_EOL;
echo 'İşlenen: ' . $summary['processed'] . PHP_EOL;
echo 'Oluşturulan: ' . $summary['created'] . PHP_EOL;
echo 'Başarısız: ' . $summary['failed'] . PHP_EOL;
echo 'Bildirim kuyruğu: ' . $summary['notifications'] . PHP_EOL;
