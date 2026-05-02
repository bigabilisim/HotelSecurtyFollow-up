<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\User;
use App\Models\Visit;
use App\Services\Notifications\NotificationQueue;
use App\Services\ReportService;
use App\Services\TimeoutMonitor;

$userModel = new User();

if (!$userModel->hasAnyUsers()) {
    $userId = $userModel->createAdmin([
        'full_name' => 'Test Admin',
        'username' => 'admin',
        'email' => 'admin@example.test',
        'phone' => '05550000000',
        'password' => 'Test123456',
    ]);
} else {
    $user = $userModel->findByUsername('admin');
    $userId = (int) ($user['id'] ?? 1);
}

$pdo = App\Core\Database::connection();
$categoryId = (int) $pdo->query("SELECT id FROM visitor_categories WHERE code = 'DENETCI' LIMIT 1")->fetchColumn();
$departmentId = (int) $pdo->query("SELECT id FROM departments WHERE code = 'GENEL' LIMIT 1")->fetchColumn();

$visitId = (new Visit())->createEntry([
    'full_name' => 'Test Ziyaretçi',
    'category_id' => $categoryId,
    'department_id' => $departmentId,
    'phone' => '05551112233',
    'company' => 'Test Firma',
    'vehicle_plate' => '34 TEST 34',
    'host_name' => 'Genel Müdür',
    'purpose' => 'Test',
    'note' => 'Smoke test girişi',
], $userId);

(new Visit())->createExit($visitId, $userId, 'Smoke test çıkışı');
$timeouts = (new TimeoutMonitor())->process(10);
$reports = (new ReportService())->runDue(2);
$notifications = (new NotificationQueue())->process(20);

echo "Admin user id: {$userId}\n";
echo "Visit id: {$visitId}\n";
echo 'Timeouts: ' . json_encode($timeouts, JSON_UNESCAPED_UNICODE) . "\n";
echo 'Reports: ' . json_encode($reports, JSON_UNESCAPED_UNICODE) . "\n";
echo 'Notifications: ' . json_encode($notifications, JSON_UNESCAPED_UNICODE) . "\n";
echo "Smoke test tamamlandı.\n";
