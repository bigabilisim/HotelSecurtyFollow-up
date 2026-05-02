<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Visit;
use App\Models\Watchlist;

$pdo = Database::connection();
$watchlist = new Watchlist();
$watchlist->ensureTable();

$userId = (int) $pdo->query("SELECT id FROM users WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
$categoryId = (int) $pdo->query("SELECT id FROM visitor_categories WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
$departmentId = (int) $pdo->query("SELECT id FROM departments WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();

if ($userId <= 0 || $categoryId <= 0) {
    throw new RuntimeException('Smoke test için kullanıcı ve kategori kaydı gerekli.');
}

$suffix = date('YmdHis');
$createdWatchlistIds = [];
$createdVisitIds = [];
$createdVisitorNames = [];

try {
    $warningName = 'Watchlist Smoke Uyari ' . $suffix;
    $warningId = $watchlist->save([
        'list_type' => 'warning',
        'match_type' => 'name',
        'match_value' => $warningName,
        'reason' => 'Smoke uyarı testi',
        'action_note' => 'Güvenlik notunu kontrol et',
        'is_active' => true,
    ], $userId);
    $createdWatchlistIds[] = $warningId;

    $warningMatches = $watchlist->matches([
        'full_name' => $warningName,
        'phone' => '',
        'vehicle_plate' => '',
    ]);

    if (!$warningMatches || $warningMatches[0]['list_type'] !== 'warning') {
        throw new RuntimeException('Uyarı listesi eşleşmesi bulunamadı.');
    }

    $blacklistPlate = '34 SMK 123';
    $blacklistId = $watchlist->save([
        'list_type' => 'blacklist',
        'match_type' => 'plate',
        'match_value' => $blacklistPlate,
        'reason' => 'Smoke kara liste testi',
        'action_note' => 'Giriş verme',
        'is_active' => true,
    ], $userId);
    $createdWatchlistIds[] = $blacklistId;

    $blacklistMatches = $watchlist->matches([
        'full_name' => 'Plaka Test',
        'phone' => '',
        'vehicle_plate' => '34SMK123',
    ]);

    if (!$blacklistMatches || $blacklistMatches[0]['list_type'] !== 'blacklist') {
        throw new RuntimeException('Kara liste plaka eşleşmesi bulunamadı.');
    }

    $visitName = 'Appointment Smoke ' . $suffix;
    $createdVisitorNames[] = $visitName;
    $visitId = (new Visit())->createEntry([
        'full_name' => $visitName,
        'category_id' => $categoryId,
        'department_id' => $departmentId,
        'phone' => '05550000123',
        'company' => 'Smoke Test',
        'vehicle_plate' => '34 TST 123',
        'host_name' => 'Smoke Host',
        'purpose' => 'Smoke',
        'appointment_status' => 'appointment',
        'note' => 'Randevulu giriş smoke testi',
    ], $userId);
    $createdVisitIds[] = $visitId;

    $visitStmt = $pdo->prepare(
        'SELECT v.appointment_status, vi.vehicle_plate
         FROM visits v
         INNER JOIN visitors vi ON vi.id = v.visitor_id
         WHERE v.id = :id
         LIMIT 1'
    );
    $visitStmt->execute(['id' => $visitId]);
    $visit = $visitStmt->fetch(PDO::FETCH_ASSOC);

    if (!$visit || $visit['appointment_status'] !== 'appointment') {
        throw new RuntimeException('Randevulu giriş veritabanına kaydedilmedi.');
    }

    echo "Watchlist eşleşmeleri OK\n";
    echo "Randevulu giriş DB: visit #{$visitId}\n";
} finally {
    foreach ($createdVisitIds as $visitId) {
        $pdo->prepare('DELETE FROM visit_events WHERE visit_id = :visit_id')->execute(['visit_id' => $visitId]);
        $pdo->prepare('DELETE FROM visits WHERE id = :id')->execute(['id' => $visitId]);
    }

    foreach ($createdVisitorNames as $visitorName) {
        $pdo->prepare('DELETE FROM visitors WHERE full_name = :full_name')->execute(['full_name' => $visitorName]);
    }

    foreach ($createdWatchlistIds as $watchlistId) {
        $pdo->prepare('DELETE FROM watchlist_entries WHERE id = :id')->execute(['id' => $watchlistId]);
    }
}

echo "Watchlist / randevu smoke test tamamlandı.\n";
