#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\Notifications\NotificationQueue;
use App\Services\VersionNotificationService;

require dirname(__DIR__) . '/app/bootstrap.php';

$send = in_array('--send', $argv, true);
$force = in_array('--force', $argv, true);
$process = in_array('--process', $argv, true);
$allowUnpublished = in_array('--allow-unpublished', $argv, true);

$summary = $send
    ? (new VersionNotificationService())->notify($force, $allowUnpublished)
    : (new VersionNotificationService())->preview($allowUnpublished);

echo 'Sürüm bildirimi: ' . $summary['message'] . PHP_EOL;
echo 'Durum: ' . $summary['status'] . PHP_EOL;
echo 'Sürüm: ' . ($summary['version'] ?? '-') . PHP_EOL;
echo 'Alıcı: ' . $summary['recipient_count'] . PHP_EOL;
echo 'Kuyruk: ' . $summary['queued_count'] . PHP_EOL;

if ($process && $send) {
    $queueSummary = (new NotificationQueue())->process(200);
    echo 'Bildirim kuyruğu işlendi.' . PHP_EOL;
    echo 'İşlenen: ' . $queueSummary['processed'] . PHP_EOL;
    echo 'Gönderilen: ' . $queueSummary['sent'] . PHP_EOL;
    echo 'Başarısız: ' . $queueSummary['failed'] . PHP_EOL;
    echo 'Atlanan: ' . $queueSummary['skipped'] . PHP_EOL;
}

if (!$send) {
    echo 'Not: Gerçek gönderim için --send kullanın.' . PHP_EOL;
}
