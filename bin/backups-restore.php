#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\BackupService;

require dirname(__DIR__) . '/app/bootstrap.php';

$file = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--file=')) {
        $file = substr($argument, 7);
    }
}

if (!$file) {
    fwrite(STDERR, 'Kullanım: php bin/backups-restore.php --file=/tam/yol/yedek.zip' . PHP_EOL);
    exit(1);
}

$target = (new BackupService())->stageRestore($file);

echo 'Yedek staging klasörüne açıldı: ' . $target . PHP_EOL;
