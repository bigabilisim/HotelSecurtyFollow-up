<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$root = dirname(__DIR__, 2);
$connection = new mysqli('127.0.0.1', 'root', '', '', 3306);
$connection->set_charset('utf8mb4');

foreach (['database/schema.sql', 'database/seed.sql'] as $file) {
    $path = $root . '/' . $file;
    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException('SQL dosyası okunamadı: ' . $file);
    }

    if (!$connection->multi_query($sql)) {
        throw new RuntimeException('SQL çalıştırılamadı: ' . $file);
    }

    do {
        $result = $connection->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } while ($connection->more_results() && $connection->next_result());

    echo $file . " import edildi.\n";
}

echo "Veritabanı hazır.\n";
