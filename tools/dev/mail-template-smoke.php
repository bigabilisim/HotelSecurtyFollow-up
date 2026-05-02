<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Notifications\NotificationService;

$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

$pdo = Database::connection();
$pdo->beginTransaction();

try {
    $mailEnabled = (int) $pdo->query("SELECT COUNT(*) FROM notification_channels WHERE code = 'mail' AND is_enabled = 1")->fetchColumn();
    if ($mailEnabled < 1) {
        throw new RuntimeException('Mail kanali aktif degil.');
    }

    $categoryId = (int) $pdo->query("SELECT id FROM visitor_categories WHERE code = 'DENETCI' LIMIT 1")->fetchColumn();
    if ($categoryId < 1) {
        $categoryId = (int) $pdo->query('SELECT id FROM visitor_categories ORDER BY id LIMIT 1')->fetchColumn();
    }

    $departmentId = (int) $pdo->query("SELECT id FROM departments WHERE code = 'GENEL' LIMIT 1")->fetchColumn();
    if ($departmentId < 1) {
        $departmentId = null;
    }

    $userId = (int) $pdo->query("SELECT id FROM users WHERE status = 'active' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
    if ($userId < 1) {
        throw new RuntimeException('Aktif kullanici bulunamadi.');
    }

    $settingStmt = $pdo->prepare(
        'INSERT INTO app_settings (setting_key, setting_value, is_encrypted)
         VALUES (:setting_key, :setting_value, 0)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = VALUES(is_encrypted)'
    );
    $settingStmt->execute([
        'setting_key' => 'mail_template.escalation.subject',
        'setting_value' => 'Smoke konu {visitor_name} {subject}',
    ]);
    $settingStmt->execute([
        'setting_key' => 'mail_template.escalation.body',
        'setting_value' => "Smoke govde {visitor_name}\n{message}\n{department_name}",
    ]);

    $visitorName = 'Mail Template Smoke ' . date('His');
    $visitorStmt = $pdo->prepare(
        'INSERT INTO visitors (full_name, normalized_name, phone, company, vehicle_plate, note)
         VALUES (:full_name, :normalized_name, :phone, :company, :vehicle_plate, :note)'
    );
    $visitorStmt->execute([
        'full_name' => $visitorName,
        'normalized_name' => strtoupper($visitorName),
        'phone' => '05550000111',
        'company' => 'Smoke Test',
        'vehicle_plate' => '34 SMK 001',
        'note' => 'Mail template smoke test',
    ]);
    $visitorId = (int) $pdo->lastInsertId();

    $visitStmt = $pdo->prepare(
        'INSERT INTO visits (
            visitor_id,
            category_id,
            department_id,
            host_name,
            purpose,
            entry_note,
            status,
            max_duration_minutes_snapshot
         ) VALUES (
            :visitor_id,
            :category_id,
            :department_id,
            :host_name,
            :purpose,
            :entry_note,
            "inside",
            30
         )'
    );
    $visitStmt->execute([
        'visitor_id' => $visitorId,
        'category_id' => $categoryId,
        'department_id' => $departmentId,
        'host_name' => 'Smoke Host',
        'purpose' => 'Smoke',
        'entry_note' => 'Mail template smoke test',
    ]);
    $visitId = (int) $pdo->lastInsertId();

    $queued = (new NotificationService())->queueDirectUser(
        $visitId,
        $userId,
        'Varsayilan Konu',
        'Varsayilan Mesaj',
        ['mail_template_event' => 'escalation']
    );

    if ($queued < 1) {
        throw new RuntimeException('Bildirim kuyruga eklenmedi.');
    }

    $logStmt = $pdo->prepare(
        "SELECT nl.subject, nl.message
         FROM notification_logs nl
         INNER JOIN notification_channels nc ON nc.id = nl.channel_id
         WHERE nl.visit_id = :visit_id
           AND nc.code = 'mail'
         ORDER BY nl.id DESC
         LIMIT 1"
    );
    $logStmt->execute(['visit_id' => $visitId]);
    $mailLog = $logStmt->fetch(PDO::FETCH_ASSOC);

    if (!$mailLog) {
        throw new RuntimeException('Mail bildirim logu bulunamadi.');
    }

    if (!str_contains((string) $mailLog['subject'], $visitorName) || !str_contains((string) $mailLog['subject'], 'Varsayilan Konu')) {
        throw new RuntimeException('Mail konusu sablon degiskenlerini islemedi.');
    }

    if (!str_contains((string) $mailLog['message'], $visitorName) || !str_contains((string) $mailLog['message'], 'Varsayilan Mesaj')) {
        throw new RuntimeException('Mail govdesi sablon degiskenlerini islemedi.');
    }

    $pdo->rollBack();
    echo "Mail template smoke OK\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $error;
}
