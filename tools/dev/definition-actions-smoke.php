<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/bootstrap.php';

$pdo = App\Core\Database::connection();
$suffix = date('His');

function ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$category = new App\Models\Category();
$category->save([
    'code' => 'SMOKE_CAT_' . $suffix,
    'name' => 'Smoke Kategori ' . $suffix,
    'color' => '#0f766e',
    'max_duration_minutes' => 30,
    'warning_before_minutes' => 5,
    'requires_department_approval' => true,
    'escalation_after_minutes' => 3,
    'is_notification_enabled' => true,
    'status' => 'active',
], 1);
$categoryId = (int) $pdo->query("SELECT id FROM visitor_categories WHERE code = 'SMOKE_CAT_{$suffix}' LIMIT 1")->fetchColumn();
$category->save([
    'id' => $categoryId,
    'code' => 'SMOKE_CAT_' . $suffix,
    'name' => 'Smoke Kategori Güncel ' . $suffix,
    'color' => '#16803a',
    'max_duration_minutes' => 45,
    'warning_before_minutes' => 7,
    'requires_department_approval' => false,
    'escalation_after_minutes' => 4,
    'is_notification_enabled' => false,
    'status' => 'passive',
], 1);
ensure($category->find($categoryId)['name'] === 'Smoke Kategori Güncel ' . $suffix, 'Kategori güncellenmedi.');
$category->delete($categoryId);
ensure($category->find($categoryId) === null, 'Kategori silinmedi.');
echo "Kategori düzenle/sil OK\n";

$department = new App\Models\Department();
$department->save([
    'code' => 'SMOKE_DEP_' . $suffix,
    'name' => 'Smoke Departman ' . $suffix,
    'manager_user_id' => 0,
    'email' => '',
    'phone' => '',
    'status' => 'active',
]);
$departmentId = (int) $pdo->query("SELECT id FROM departments WHERE code = 'SMOKE_DEP_{$suffix}' LIMIT 1")->fetchColumn();
$department->save([
    'id' => $departmentId,
    'code' => 'SMOKE_DEP_' . $suffix,
    'name' => 'Smoke Departman Güncel ' . $suffix,
    'manager_user_id' => 0,
    'email' => 'smoke@example.test',
    'phone' => '05550000000',
    'status' => 'passive',
]);
ensure($department->find($departmentId)['name'] === 'Smoke Departman Güncel ' . $suffix, 'Departman güncellenmedi.');
$department->delete($departmentId);
ensure($department->find($departmentId) === null, 'Departman silinmedi.');
echo "Departman düzenle/sil OK\n";

$roleId = (int) $pdo->query("SELECT id FROM roles ORDER BY id LIMIT 1")->fetchColumn();
$user = new App\Models\User();
$userId = $user->save([
    'department_id' => 0,
    'full_name' => 'Smoke Kullanıcı ' . $suffix,
    'username' => 'smoke_user_' . $suffix,
    'email' => '',
    'phone' => '',
    'password' => 'Test123456',
    'status' => 'active',
    'role_ids' => [$roleId],
]);
$user->save([
    'id' => $userId,
    'department_id' => 0,
    'full_name' => 'Smoke Kullanıcı Güncel ' . $suffix,
    'username' => 'smoke_user_' . $suffix,
    'email' => '',
    'phone' => '05551112233',
    'password' => '',
    'status' => 'passive',
    'role_ids' => [$roleId],
]);
ensure($user->findForEdit($userId)['full_name'] === 'Smoke Kullanıcı Güncel ' . $suffix, 'Kullanıcı güncellenmedi.');
ensure($user->delete($userId, 1) === true, 'Kullanıcı silinemedi.');
ensure($user->findForEdit($userId) === null, 'Kullanıcı silme sonrası listeden çıkmadı.');
echo "Kullanıcı düzenle/sil OK\n";

$baseCategoryId = (int) $pdo->query('SELECT id FROM visitor_categories WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
$channelId = (int) $pdo->query('SELECT id FROM notification_channels ORDER BY id LIMIT 1')->fetchColumn();
$rule = new App\Models\NotificationRule();
$rule->save([
    'category_id' => $baseCategoryId,
    'name' => 'Smoke Kural ' . $suffix,
    'event_type' => 'entry',
    'priority' => 100,
    'condition_json' => '',
    'message_template' => 'Smoke mesaj',
    'is_active' => true,
    'channel_ids' => [$channelId],
    'recipient_type' => 'custom',
    'recipient_department_id' => 0,
    'recipient_role_id' => 0,
    'custom_name' => 'Smoke',
    'custom_email' => 'smoke@example.test',
    'custom_phone' => '',
    'custom_telegram_chat_id' => '',
    'custom_whatsapp_number' => '',
]);
$ruleId = (int) $pdo->query("SELECT id FROM notification_rules WHERE name = 'Smoke Kural {$suffix}' LIMIT 1")->fetchColumn();
$rule->save([
    'id' => $ruleId,
    'category_id' => $baseCategoryId,
    'name' => 'Smoke Kural Güncel ' . $suffix,
    'event_type' => 'entry',
    'priority' => 90,
    'condition_json' => '',
    'message_template' => 'Smoke mesaj güncel',
    'is_active' => false,
    'channel_ids' => [$channelId],
    'recipient_type' => 'custom',
    'recipient_department_id' => 0,
    'recipient_role_id' => 0,
    'custom_name' => 'Smoke Güncel',
    'custom_email' => 'smoke@example.test',
    'custom_phone' => '',
    'custom_telegram_chat_id' => '',
    'custom_whatsapp_number' => '',
]);
ensure($rule->findForEdit($ruleId)['name'] === 'Smoke Kural Güncel ' . $suffix, 'Bildirim kuralı güncellenmedi.');
$rule->delete($ruleId);
ensure($rule->findForEdit($ruleId) === null, 'Bildirim kuralı silinmedi.');
echo "Bildirim kuralı düzenle/sil OK\n";
