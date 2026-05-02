#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\TimeoutMonitor;

require dirname(__DIR__) . '/app/bootstrap.php';

$limit = 100;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = (int) substr($argument, 8);
    }
}

$summary = (new TimeoutMonitor())->process($limit);

echo 'Süre aşımı kontrolü tamamlandı.' . PHP_EOL;
echo 'Uyarı: ' . $summary['warnings'] . PHP_EOL;
echo 'Süre dolan durum: ' . $summary['overdue'] . PHP_EOL;
echo 'Departman sorusu: ' . $summary['questions'] . PHP_EOL;
echo 'Eskalasyon: ' . $summary['escalations'] . PHP_EOL;
