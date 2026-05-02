<?php

declare(strict_types=1);

$baseUrl = $argv[1] ?? 'http://127.0.0.1:8002';
$cookieFile = sys_get_temp_dir() . '/otel-security-http-smoke-cookie.txt';
$root = dirname(__DIR__, 2);

require $root . '/app/bootstrap.php';

if (is_file($cookieFile)) {
    unlink($cookieFile);
}

function request(string $method, string $url, ?array $fields, string $cookieFile): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 10,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        $hasUpload = false;
        foreach (($fields ?? []) as $field) {
            if ($field instanceof CURLFile) {
                $hasUpload = true;
                break;
            }
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $hasUpload ? $fields : http_build_query($fields ?? []));
    }

    $response = curl_exec($ch);
    if ($response === false) {
        throw new RuntimeException(curl_error($ch));
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
    ];
}

function assertStatus(array $response, int $expected, string $label): void
{
    if ($response['status'] !== $expected) {
        file_put_contents(sys_get_temp_dir() . '/otel-security-http-smoke-failure.html', $response['body']);
        throw new RuntimeException($label . ' beklenen HTTP ' . $expected . ', gelen ' . $response['status']);
    }

    echo $label . ': HTTP ' . $response['status'] . "\n";
}

$login = request('GET', $baseUrl . '/login', null, $cookieFile);
assertStatus($login, 200, 'Login ekranı');

if (!preg_match('/name="_csrf" value="([^"]+)"/', $login['body'], $match)) {
    throw new RuntimeException('CSRF token bulunamadı.');
}

$auth = request('POST', $baseUrl . '/login', [
    '_csrf' => $match[1],
    'username' => 'admin',
    'password' => 'admin',
], $cookieFile);
assertStatus($auth, 302, 'Login POST');

$dashboard = request('GET', $baseUrl . '/dashboard', null, $cookieFile);
assertStatus($dashboard, 200, 'Dashboard');

if (!preg_match('/name="_csrf" value="([^"]+)"/', $dashboard['body'], $dashboardCsrf)) {
    throw new RuntimeException('Dashboard CSRF token bulunamadı.');
}

$pdo = App\Core\Database::connection();
$categoryId = (int) $pdo->query("SELECT id FROM visitor_categories WHERE code = 'DENETCI' LIMIT 1")->fetchColumn();
$departmentId = (int) $pdo->query("SELECT id FROM departments WHERE code = 'GENEL' LIMIT 1")->fetchColumn();
$visitorName = 'HTTP Test Ziyaretçi ' . date('His');

$entry = request('POST', $baseUrl . '/visits/entry', [
    '_csrf' => $dashboardCsrf[1],
    'category_id' => $categoryId,
    'full_name' => $visitorName,
    'phone' => '05553334455',
    'vehicle_plate' => '34 HTTP 34',
    'company' => 'HTTP Test Firma',
    'department_id' => $departmentId,
    'host_name' => 'HTTP Test Host',
    'purpose' => 'Test',
    'has_appointment' => 'on',
    'note' => 'HTTP smoke test girişi',
], $cookieFile);
assertStatus($entry, 302, 'Giriş POST');

$visitStmt = $pdo->prepare(
    'SELECT v.id, v.status, v.appointment_status
     FROM visits v
     INNER JOIN visitors vi ON vi.id = v.visitor_id
     WHERE vi.full_name = :full_name
     ORDER BY v.id DESC
     LIMIT 1'
);
$visitStmt->execute(['full_name' => $visitorName]);
$visit = $visitStmt->fetch(PDO::FETCH_ASSOC);

if (!$visit || $visit['status'] !== 'inside') {
    throw new RuntimeException('HTTP giriş kaydı veritabanında inside durumunda bulunamadı.');
}

if (($visit['appointment_status'] ?? '') !== 'appointment') {
    throw new RuntimeException('HTTP giriş kaydı randevulu olarak kaydedilmedi.');
}

echo 'Giriş kaydı DB: visit #' . $visit['id'] . "\n";

$dashboardAfterEntry = request('GET', $baseUrl . '/dashboard', null, $cookieFile);
assertStatus($dashboardAfterEntry, 200, 'Dashboard giriş sonrası');

if (!preg_match('/name="_csrf" value="([^"]+)"/', $dashboardAfterEntry['body'], $exitCsrf)) {
    throw new RuntimeException('Çıkış CSRF token bulunamadı.');
}

$updatedVisitorName = $visitorName . ' Düzenlendi';
$updateVisit = request('POST', $baseUrl . '/visits/update', [
    '_csrf' => $exitCsrf[1],
    'visit_id' => (int) $visit['id'],
    'category_id' => $categoryId,
    'full_name' => $updatedVisitorName,
    'phone' => '05556667788',
    'vehicle_plate' => '34 EDT 001',
    'company' => 'HTTP Test Firma Güncel',
    'department_id' => $departmentId,
    'host_name' => 'HTTP Test Host Güncel',
    'purpose' => 'Ziyaret',
    'has_appointment' => 'on',
    'note' => 'HTTP smoke test güncelleme notu',
], $cookieFile);
assertStatus($updateVisit, 302, 'Giriş düzenleme POST');

$updatedStmt = $pdo->prepare(
    'SELECT
        vi.full_name,
        vi.phone,
        vi.company,
        vi.vehicle_plate,
        v.host_name,
        v.entry_note,
        v.appointment_status
     FROM visits v
     INNER JOIN visitors vi ON vi.id = v.visitor_id
     WHERE v.id = :id
     LIMIT 1'
);
$updatedStmt->execute(['id' => (int) $visit['id']]);
$updatedVisit = $updatedStmt->fetch(PDO::FETCH_ASSOC);
if (!$updatedVisit || $updatedVisit['full_name'] !== $updatedVisitorName || $updatedVisit['vehicle_plate'] !== '34 EDT 001' || $updatedVisit['host_name'] !== 'HTTP Test Host Güncel') {
    throw new RuntimeException('Giriş düzenleme veritabanına beklenen şekilde yansımadı.');
}

echo 'Giriş düzenleme DB: visit #' . $visit['id'] . "\n";

$exit = request('POST', $baseUrl . '/visits/exit', [
    '_csrf' => $exitCsrf[1],
    'visit_id' => (int) $visit['id'],
    'exit_note' => 'HTTP smoke test çıkışı',
], $cookieFile);
assertStatus($exit, 302, 'Çıkış POST');

$statusStmt = $pdo->prepare('SELECT status FROM visits WHERE id = :id LIMIT 1');
$statusStmt->execute(['id' => (int) $visit['id']]);
$status = (string) $statusStmt->fetchColumn();

if ($status !== 'exited') {
    throw new RuntimeException('HTTP çıkış kaydı veritabanında exited durumuna geçmedi.');
}

echo 'Çıkış kaydı DB: visit #' . $visit['id'] . "\n";

$blacklistedName = 'HTTP Kara Liste Test ' . date('His');
$blacklistId = (new App\Models\Watchlist())->save([
    'list_type' => 'blacklist',
    'match_type' => 'name',
    'match_value' => $blacklistedName,
    'reason' => 'HTTP smoke kara liste sebebi',
    'action_note' => 'Giriş verme',
    'is_active' => true,
], (int) $pdo->query('SELECT id FROM users WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn());

$dashboardBeforeBlacklist = request('GET', $baseUrl . '/dashboard', null, $cookieFile);
assertStatus($dashboardBeforeBlacklist, 200, 'Dashboard kara liste öncesi');
if (!preg_match('/name="_csrf" value="([^"]+)"/', $dashboardBeforeBlacklist['body'], $blacklistCsrf)) {
    throw new RuntimeException('Kara liste giriş CSRF token bulunamadı.');
}

$blockedEntry = request('POST', $baseUrl . '/visits/entry', [
    '_csrf' => $blacklistCsrf[1],
    'category_id' => $categoryId,
    'full_name' => $blacklistedName,
    'phone' => '05554443322',
    'vehicle_plate' => '34 BLK 34',
    'company' => 'HTTP Kara Liste Test',
    'department_id' => $departmentId,
    'host_name' => 'HTTP Test Host',
    'purpose' => 'Test',
    'note' => 'Kara liste smoke test girişi',
], $cookieFile);
assertStatus($blockedEntry, 302, 'Kara liste giriş POST');

$dashboardBlocked = request('GET', $baseUrl . '/dashboard', null, $cookieFile);
assertStatus($dashboardBlocked, 200, 'Dashboard kara liste uyarısı');
foreach (['security-alert-dialog', 'Kara Liste Uyarısı', 'Okudum', 'Giriş kaydı oluşturulmadı'] as $needle) {
    if (!str_contains($dashboardBlocked['body'], $needle)) {
        throw new RuntimeException('Kara liste büyük uyarısı beklenen içeriği göstermedi: ' . $needle);
    }
}

$blockedVisitStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM visits v
     INNER JOIN visitors vi ON vi.id = v.visitor_id
     WHERE vi.full_name = :full_name'
);
$blockedVisitStmt->execute(['full_name' => $blacklistedName]);
if ((int) $blockedVisitStmt->fetchColumn() !== 0) {
    throw new RuntimeException('Kara listeye rağmen giriş kaydı oluşturuldu.');
}

$pdo->prepare('DELETE FROM watchlist_entries WHERE id = :id')->execute(['id' => $blacklistId]);
echo "Kara liste büyük uyarı: OK\n";

foreach (['/admin', '/admin/categories', '/admin/users', '/admin/visitors', '/admin/watchlist', '/admin/ip-blocks', '/admin/mail-templates', '/admin/notification-rules', '/admin/records', '/admin/reports', '/admin/backups'] as $path) {
    $response = request('GET', $baseUrl . $path, null, $cookieFile);
    assertStatus($response, 200, $path);
}

$securityCookieFile = sys_get_temp_dir() . '/otel-security-http-smoke-security-cookie.txt';
if (is_file($securityCookieFile)) {
    unlink($securityCookieFile);
}

$securityRoleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'security' LIMIT 1")->fetchColumn();
if ($securityRoleId <= 0) {
    throw new RuntimeException('Güvenlik rolü bulunamadı.');
}

$securityUsername = 'security.smoke';
$securityPassword = 'security1234';
$securityUserId = (int) $pdo->query("SELECT id FROM users WHERE username = 'security.smoke' LIMIT 1")->fetchColumn();
if ($securityUserId > 0) {
    $stmt = $pdo->prepare(
        'UPDATE users
         SET full_name = "Security Smoke",
             password_hash = :password_hash,
             status = "active",
             deleted_at = NULL
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $securityUserId,
        'password_hash' => password_hash($securityPassword, PASSWORD_DEFAULT),
    ]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, username, password_hash, status)
         VALUES ("Security Smoke", :username, :password_hash, "active")'
    );
    $stmt->execute([
        'username' => $securityUsername,
        'password_hash' => password_hash($securityPassword, PASSWORD_DEFAULT),
    ]);
    $securityUserId = (int) $pdo->lastInsertId();
}

$pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute(['user_id' => $securityUserId]);
$pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute([
    'user_id' => $securityUserId,
    'role_id' => $securityRoleId,
]);

$securityMaskName = 'Bilal Bozduman Smoke';
$securityMaskVisitId = (new App\Models\Visit())->createEntry([
    'full_name' => $securityMaskName,
    'category_id' => $categoryId,
    'department_id' => $departmentId,
    'phone' => '05550001122',
    'company' => 'Maskeleme Test',
    'vehicle_plate' => '34 MSK 123',
    'host_name' => 'Detay Test',
    'purpose' => 'Maskeleme',
    'note' => 'Güvenlik canlı ekran maskeleme testi',
], $securityUserId);

$securityLogin = request('GET', $baseUrl . '/login', null, $securityCookieFile);
assertStatus($securityLogin, 200, 'Güvenlik login ekranı');
if (!preg_match('/name="_csrf" value="([^"]+)"/', $securityLogin['body'], $securityCsrf)) {
    throw new RuntimeException('Güvenlik login CSRF token bulunamadı.');
}

$securityAuth = request('POST', $baseUrl . '/login', [
    '_csrf' => $securityCsrf[1],
    'username' => $securityUsername,
    'password' => $securityPassword,
], $securityCookieFile);
assertStatus($securityAuth, 302, 'Güvenlik login POST');

$securityDashboard = request('GET', $baseUrl . '/dashboard', null, $securityCookieFile);
assertStatus($securityDashboard, 200, 'Güvenlik dashboard');
if (!str_contains($securityDashboard['body'], 'Bil**** Boz**** Smo****')) {
    throw new RuntimeException('Güvenlik canlı ekranında ad soyad maskesi görünmedi.');
}

if (!str_contains($securityDashboard['body'], '<dt>Ad Soyad</dt>') || !str_contains($securityDashboard['body'], $securityMaskName)) {
    throw new RuntimeException('Güvenlik detay panelinde açık ad soyad bulunmadı.');
}

foreach (['>Yönetim</a>', '>Kurulum</a>'] as $hiddenLink) {
    if (str_contains($securityDashboard['body'], $hiddenLink)) {
        throw new RuntimeException('Güvenlik kullanıcısında gizlenmesi gereken menü göründü: ' . $hiddenLink);
    }
}

foreach (['/admin', '/admin/users', '/setup'] as $blockedPath) {
    $blocked = request('GET', $baseUrl . $blockedPath, null, $securityCookieFile);
    assertStatus($blocked, 403, 'Güvenlik engel ' . $blockedPath);
}
echo "Güvenlik rol yetki kısıtları: OK\n";

(new App\Models\Visit())->createExit($securityMaskVisitId, $securityUserId, 'Maskeleme smoke test temizliği');
echo "Güvenlik canlı ekran maskeleme: OK\n";

$adminPage = request('GET', $baseUrl . '/admin', null, $cookieFile);
assertStatus($adminPage, 200, 'Yönetim global eskalasyon');
if (!str_contains($adminPage['body'], 'Global Zincir') || !str_contains($adminPage['body'], 'Eskalasyon Zinciri')) {
    throw new RuntimeException('Global eskalasyon zinciri yönetim ekranında render edilmedi.');
}
foreach (['Hatalı Şifre Denemesi Maili', 'name="failed_login_alert_email"', 'security-settings'] as $needle) {
    if (!str_contains($adminPage['body'], $needle)) {
        throw new RuntimeException('Hatalı şifre uyarı ayarı yönetim ekranında görünmedi: ' . $needle);
    }
}
foreach (['data-admin-sortable', 'data-admin-sort-reset', 'Varsayılan Sıra'] as $needle) {
    if (!str_contains($adminPage['body'], $needle)) {
        throw new RuntimeException('Sürükle bırak yönetim kartı düzeni görünmedi: ' . $needle);
    }
}
foreach (['IP Blokları', 'Giriş Güvenliği'] as $needle) {
    if (!str_contains($adminPage['body'], $needle)) {
        throw new RuntimeException('IP blokları yönetim kartı görünmedi: ' . $needle);
    }
}

$ipBlocksPage = request('GET', $baseUrl . '/admin/ip-blocks', null, $cookieFile);
assertStatus($ipBlocksPage, 200, 'IP blokları');
foreach (['IP Blokları', 'İlk seride 5 hatalı şifre denemesi'] as $needle) {
    if (!str_contains($ipBlocksPage['body'], $needle)) {
        throw new RuntimeException('IP blokları ekranında beklenen içerik görünmedi: ' . $needle);
    }
}

$categoriesPage = request('GET', $baseUrl . '/admin/categories', null, $cookieFile);
assertStatus($categoriesPage, 200, 'Kategori global zincir notu');
if (str_contains($categoriesPage['body'], 'name="escalation_level_1_user_id"')) {
    throw new RuntimeException('Kategori ekranında kategori bazlı N+ amir alanı kalmış.');
}

$visitorsPage = request('GET', $baseUrl . '/admin/visitors', null, $cookieFile);
assertStatus($visitorsPage, 200, 'Kayıtlı kişiler');
foreach (['Kayıtlı Kişiler', 'Kişi Kaydet', 'Düzenle', 'Kara Listeye Al', 'Sil'] as $needle) {
    if (!str_contains($visitorsPage['body'], $needle)) {
        throw new RuntimeException('Kayıtlı kişiler ekranında beklenen aksiyon görünmedi: ' . $needle);
    }
}

$watchlistPage = request('GET', $baseUrl . '/admin/watchlist', null, $cookieFile);
assertStatus($watchlistPage, 200, 'Kara uyarı listesi');
foreach (['Kara / Uyarı Listesi', 'Liste Kaydet'] as $needle) {
    if (!str_contains($watchlistPage['body'], $needle)) {
        throw new RuntimeException('Kara / uyarı listesi ekranında beklenen aksiyon görünmedi: ' . $needle);
    }
}

if (!preg_match('/name="_csrf" value="([^"]+)"/', $watchlistPage['body'], $watchlistCsrf)) {
    throw new RuntimeException('Kara / uyarı listesi CSRF token bulunamadı.');
}

$watchlistValue = 'HTTP Smoke Uyarı ' . date('His');
$createWatchlist = request('POST', $baseUrl . '/admin/watchlist', [
    '_csrf' => $watchlistCsrf[1],
    'list_type' => 'warning',
    'match_type' => 'name',
    'match_value' => $watchlistValue,
    'reason' => 'HTTP smoke uyarı sebebi',
    'action_note' => 'HTTP smoke aksiyon notu',
    'is_active' => 'on',
], $cookieFile);
assertStatus($createWatchlist, 302, 'Kara uyarı listesi kaydet POST');

$watchlistStmt = $pdo->prepare('SELECT id FROM watchlist_entries WHERE match_value = :match_value AND deleted_at IS NULL LIMIT 1');
$watchlistStmt->execute(['match_value' => $watchlistValue]);
$watchlistId = (int) $watchlistStmt->fetchColumn();

if ($watchlistId <= 0) {
    throw new RuntimeException('Kaydedilen kara / uyarı listesi kaydı veritabanında bulunamadı.');
}

$watchlistWithEntry = request('GET', $baseUrl . '/admin/watchlist?edit_id=' . $watchlistId, null, $cookieFile);
assertStatus($watchlistWithEntry, 200, 'Kara uyarı listesi düzenle');
foreach ([$watchlistValue, 'Değişiklikleri Kaydet', 'Düzenle', 'Sil'] as $needle) {
    if (!str_contains($watchlistWithEntry['body'], $needle)) {
        throw new RuntimeException('Kara / uyarı listesi düzenleme aksiyonu görünmedi: ' . $needle);
    }
}

if (!preg_match('/name="_csrf" value="([^"]+)"/', $watchlistWithEntry['body'], $watchlistEditCsrf)) {
    throw new RuntimeException('Kara / uyarı listesi düzenleme CSRF token bulunamadı.');
}

$deleteWatchlist = request('POST', $baseUrl . '/admin/watchlist', [
    '_csrf' => $watchlistEditCsrf[1],
    'action' => 'delete',
    'id' => $watchlistId,
], $cookieFile);
assertStatus($deleteWatchlist, 302, 'Kara uyarı listesi sil POST');

$watchlistDeletedStmt = $pdo->prepare('SELECT deleted_at FROM watchlist_entries WHERE id = :id LIMIT 1');
$watchlistDeletedStmt->execute(['id' => $watchlistId]);
if (!$watchlistDeletedStmt->fetchColumn()) {
    throw new RuntimeException('Silinen kara / uyarı listesi kaydı deleted_at ile işaretlenmedi.');
}

$mailTemplatesPage = request('GET', $baseUrl . '/admin/mail-templates', null, $cookieFile);
assertStatus($mailTemplatesPage, 200, 'Mail şablonları');
if (!str_contains($mailTemplatesPage['body'], 'Mail Şablonları') || !str_contains($mailTemplatesPage['body'], '{visitor_name}')) {
    throw new RuntimeException('Mail şablonları ekranı beklenen içerikle render edilmedi.');
}

$recordsPage = request('GET', $baseUrl . '/admin/records', null, $cookieFile);
assertStatus($recordsPage, 200, 'Kayıtlar');
if (!str_contains($recordsPage['body'], 'Kayıt Filtrele') || !str_contains($recordsPage['body'], 'PDF Mail Gönder') || !str_contains($recordsPage['body'], 'Kara Listeye Al')) {
    throw new RuntimeException('Kayıtlar ekranı beklenen içerikle render edilmedi.');
}

if (!preg_match('/name="_csrf" value="([^"]+)"/', $recordsPage['body'], $recordsCsrf)) {
    throw new RuntimeException('Kayıtlar ekranı CSRF token bulunamadı.');
}

$quickBlacklist = request('POST', $baseUrl . '/admin/watchlist/quick-add', [
    '_csrf' => $recordsCsrf[1],
    'return_route' => '/admin/records',
    'full_name' => $visitorName,
], $cookieFile);
assertStatus($quickBlacklist, 302, 'Kayıttan kara listeye al POST');

$quickBlacklistStmt = $pdo->prepare(
    'SELECT id
     FROM watchlist_entries
     WHERE list_type = "blacklist"
       AND match_type = "name"
       AND match_value = :match_value
       AND is_active = 1
       AND deleted_at IS NULL
     ORDER BY id DESC
     LIMIT 1'
);
$quickBlacklistStmt->execute(['match_value' => $visitorName]);
$quickBlacklistId = (int) $quickBlacklistStmt->fetchColumn();

if ($quickBlacklistId <= 0) {
    throw new RuntimeException('Kayıttan kara listeye alınan kişi veritabanında bulunamadı.');
}

$pdo->prepare('DELETE FROM watchlist_entries WHERE id = :id')->execute(['id' => $quickBlacklistId]);
echo "Kayıttan kara listeye al DB: OK\n";

$recordsPdf = request('GET', $baseUrl . '/admin/records/pdf', null, $cookieFile);
assertStatus($recordsPdf, 200, 'Kayıtlar PDF');
if (!str_starts_with($recordsPdf['body'], '%PDF')) {
    throw new RuntimeException('Kayıtlar PDF çıktısı geçerli PDF başlangıcı içermiyor.');
}

$reportsPage = request('GET', $baseUrl . '/admin/reports', null, $cookieFile);
assertStatus($reportsPage, 200, 'Rapor düzenleme ekranı');
foreach (['Rapor Ekle', 'Düzenle', 'Sil', 'Rapor Kaydet'] as $needle) {
    if (!str_contains($reportsPage['body'], $needle)) {
        throw new RuntimeException('Rapor ekranında beklenen aksiyon görünmedi: ' . $needle);
    }
}

if (!preg_match('/name="_csrf" value="([^"]+)"/', $reportsPage['body'], $reportsCsrf)) {
    throw new RuntimeException('Rapor ekranı CSRF token bulunamadı.');
}

$reportName = 'HTTP Smoke Rapor ' . date('His');
$createReport = request('POST', $baseUrl . '/admin/reports', [
    '_csrf' => $reportsCsrf[1],
    'action' => 'save',
    'schedule_id' => '',
    'name' => $reportName,
    'report_type' => 'weekly',
    'run_time' => '22:15',
    'day_of_month' => '',
    'output_format' => 'html',
    'is_active' => 'on',
], $cookieFile);
assertStatus($createReport, 302, 'Rapor planı kaydet POST');

$reportStmt = $pdo->prepare('SELECT id FROM report_schedules WHERE name = :name AND deleted_at IS NULL LIMIT 1');
$reportStmt->execute(['name' => $reportName]);
$reportId = (int) $reportStmt->fetchColumn();
if ($reportId <= 0) {
    throw new RuntimeException('Kaydedilen rapor planı veritabanında bulunamadı.');
}

$editReportPage = request('GET', $baseUrl . '/admin/reports?edit_id=' . $reportId, null, $cookieFile);
assertStatus($editReportPage, 200, 'Rapor planı düzenle');
if (!str_contains($editReportPage['body'], $reportName) || !str_contains($editReportPage['body'], 'Değişiklikleri Kaydet')) {
    throw new RuntimeException('Rapor düzenleme ekranı seçili planı göstermedi.');
}

if (!preg_match('/name="_csrf" value="([^"]+)"/', $editReportPage['body'], $editReportCsrf)) {
    throw new RuntimeException('Rapor düzenleme CSRF token bulunamadı.');
}

$deleteReport = request('POST', $baseUrl . '/admin/reports', [
    '_csrf' => $editReportCsrf[1],
    'action' => 'delete',
    'schedule_id' => $reportId,
], $cookieFile);
assertStatus($deleteReport, 302, 'Rapor planı sil POST');

$deletedStmt = $pdo->prepare('SELECT deleted_at FROM report_schedules WHERE id = :id LIMIT 1');
$deletedStmt->execute(['id' => $reportId]);
if (!$deletedStmt->fetchColumn()) {
    throw new RuntimeException('Silinen rapor planı deleted_at ile işaretlenmedi.');
}

$usersPage = request('GET', $baseUrl . '/admin/users', null, $cookieFile);
assertStatus($usersPage, 200, 'Kullanıcı yönetimi');

foreach (['Toplu Kullanıcı Aktar', 'Operasyon Müdürü', 'Gece Müdürü', 'Yönetim ekranı panelleri', 'Kayıtlı Kişiler', 'Kanal Ayarları', 'Günlük Rapor Gönder', 'Haftalık Rapor Gönder', 'Aylık Rapor Gönder', 'Web Push bildirimini aktif et', 'İçeri giriş kayıtlarında bildirim gönder', 'Çıkış kayıtlarında bildirim gönder'] as $needle) {
    if (!str_contains($usersPage['body'], $needle)) {
        throw new RuntimeException('Kullanıcı yönetiminde beklenen alan görünmedi: ' . $needle);
    }
}

if (!preg_match('/name="_csrf" value="([^"]+)"/', $usersPage['body'], $usersCsrf)) {
    throw new RuntimeException('Kullanıcı yönetimi CSRF token bulunamadı.');
}

$panelCookieFile = sys_get_temp_dir() . '/otel-security-http-smoke-panel-cookie.txt';
if (is_file($panelCookieFile)) {
    unlink($panelCookieFile);
}

$panelUsername = 'panel.smoke';
$panelPassword = 'panel1234';
$panelUser = request('POST', $baseUrl . '/admin/users', [
    '_csrf' => $usersCsrf[1],
    'id' => '',
    'full_name' => 'Panel Smoke',
    'username' => $panelUsername,
    'email' => '',
    'phone' => '',
    'department_id' => '',
    'status' => 'active',
    'password' => $panelPassword,
    'role_ids' => [$securityRoleId],
    'panel_permissions' => ['visitors.manage'],
], $cookieFile);
assertStatus($panelUser, 302, 'Panel izinli kullanıcı kaydet POST');

$panelLogin = request('GET', $baseUrl . '/login', null, $panelCookieFile);
assertStatus($panelLogin, 200, 'Panel izinli login ekranı');
if (!preg_match('/name="_csrf" value="([^"]+)"/', $panelLogin['body'], $panelCsrf)) {
    throw new RuntimeException('Panel izinli login CSRF token bulunamadı.');
}

$panelAuth = request('POST', $baseUrl . '/login', [
    '_csrf' => $panelCsrf[1],
    'username' => $panelUsername,
    'password' => $panelPassword,
], $panelCookieFile);
assertStatus($panelAuth, 302, 'Panel izinli login POST');

$panelAdmin = request('GET', $baseUrl . '/admin', null, $panelCookieFile);
assertStatus($panelAdmin, 200, 'Panel izinli yönetim');
if (!str_contains($panelAdmin['body'], 'Kayıtlı Kişiler') || str_contains($panelAdmin['body'], 'Kullanıcı ve Yetki')) {
    throw new RuntimeException('Kullanıcı bazlı panel yetkisi yönetim kartlarını doğru filtrelemedi.');
}

$panelVisitors = request('GET', $baseUrl . '/admin/visitors', null, $panelCookieFile);
assertStatus($panelVisitors, 200, 'Panel izinli kayıtlı kişiler');
$panelUsersBlocked = request('GET', $baseUrl . '/admin/users', null, $panelCookieFile);
assertStatus($panelUsersBlocked, 403, 'Panel kapalı kullanıcılar');
$panelWatchlistBlocked = request('GET', $baseUrl . '/admin/watchlist', null, $panelCookieFile);
assertStatus($panelWatchlistBlocked, 403, 'Panel kapalı kara liste');
echo "Kullanıcı bazlı panel yetkileri: OK\n";

$template = request('GET', $baseUrl . '/admin/users/import-template', null, $cookieFile);
assertStatus($template, 200, 'Kullanıcı import örneği');
if (!str_contains($template['body'], 'full_name;username;email;phone;department_code;password;roles;status;daily_report;weekly_report;monthly_report')) {
    throw new RuntimeException('Kullanıcı import örnek dosyası beklenen başlığı içermiyor.');
}

$backupsPage = request('GET', $baseUrl . '/admin/backups', null, $cookieFile);
assertStatus($backupsPage, 200, 'Yedekleme ayarları');
foreach (['Planı Kaydet', 'Şimdi Yedek Al', 'Yedek tamamlanınca mail gönder', 'Canlı veritabanı dump'] as $needle) {
    if (!str_contains($backupsPage['body'], $needle)) {
        throw new RuntimeException('Yedekleme ekranında beklenen alan görünmedi: ' . $needle);
    }
}

$backupDirectory = dirname(__DIR__, 2) . '/storage/backups';
if (!is_dir($backupDirectory)) {
    mkdir($backupDirectory, 0775, true);
}

$backupFileName = 'http-smoke-backup-' . date('His') . '.zip';
$backupFilePath = $backupDirectory . '/' . $backupFileName;
$zip = new ZipArchive();
if ($zip->open($backupFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('HTTP smoke yedek zip dosyası oluşturulamadı.');
}
$zip->addFromString('smoke.txt', 'backup download smoke');
$zip->close();

$backupJobId = (int) $pdo->query('SELECT id FROM backup_jobs ORDER BY id LIMIT 1')->fetchColumn();
$backupLogStmt = $pdo->prepare(
    'INSERT INTO backup_logs (
        job_id,
        file_name,
        file_path,
        file_size_bytes,
        checksum_sha256,
        status,
        finished_at
     ) VALUES (
        :job_id,
        :file_name,
        :file_path,
        :file_size_bytes,
        :checksum_sha256,
        "success",
        NOW()
     )'
);
$backupLogStmt->execute([
    'job_id' => $backupJobId > 0 ? $backupJobId : null,
    'file_name' => $backupFileName,
    'file_path' => $backupFilePath,
    'file_size_bytes' => filesize($backupFilePath),
    'checksum_sha256' => hash_file('sha256', $backupFilePath),
]);
$backupLogId = (int) $pdo->lastInsertId();

$backupsPageWithDownload = request('GET', $baseUrl . '/admin/backups', null, $cookieFile);
assertStatus($backupsPageWithDownload, 200, 'Yedekleme indirme butonu');
if (!str_contains($backupsPageWithDownload['body'], 'İndir')) {
    throw new RuntimeException('Yedekleme geçmişinde indirme butonu görünmedi.');
}

$backupDownload = request('GET', $baseUrl . '/admin/backups/download?log_id=' . $backupLogId, null, $cookieFile);
assertStatus($backupDownload, 200, 'Yedek indirme');
if (!str_starts_with($backupDownload['body'], 'PK')) {
    throw new RuntimeException('Yedek indirme çıktısı zip başlangıcı içermiyor.');
}

$pdo->prepare('DELETE FROM backup_logs WHERE id = :id')->execute(['id' => $backupLogId]);
@unlink($backupFilePath);
echo "Yedek indirme DB: OK\n";

if (!preg_match('/name="_csrf" value="([^"]+)"/', $usersPage['body'], $usersCsrf)) {
    throw new RuntimeException('Kullanıcı yönetimi CSRF token bulunamadı.');
}

$importUsername = 'import.smoke.' . date('His');
$importFile = sys_get_temp_dir() . '/otel-security-user-import-smoke.csv';
file_put_contents(
    $importFile,
    "\xEF\xBB\xBFfull_name;username;email;phone;department_code;password;roles;status;daily_report;weekly_report;monthly_report\n"
    . 'Import Smoke Kullanıcı;' . $importUsername . ';' . $importUsername . '@otel.local;05550000999;GENEL;1234;Operasyon Müdürü|Gece Müdürü;active;evet;evet;evet' . "\n"
);

$import = request('POST', $baseUrl . '/admin/users/import', [
    '_csrf' => $usersCsrf[1],
    'user_import' => new CURLFile($importFile, 'text/csv', 'kullanici-import-smoke.csv'),
], $cookieFile);
assertStatus($import, 302, 'Kullanıcı import POST');

$userStmt = $pdo->prepare(
    'SELECT
        u.id,
        u.report_daily_enabled,
        u.report_weekly_enabled,
        u.report_monthly_enabled,
        GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ", ") AS role_names
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     WHERE u.username = :username AND u.deleted_at IS NULL
     GROUP BY u.id
     LIMIT 1'
);
$userStmt->execute(['username' => $importUsername]);
$importedUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$importedUser || !str_contains((string) $importedUser['role_names'], 'Operasyon Müdürü') || !str_contains((string) $importedUser['role_names'], 'Gece Müdürü')) {
    throw new RuntimeException('Import edilen kullanıcı beklenen rolleri almadı.');
}

if ((int) $importedUser['report_daily_enabled'] !== 1 || (int) $importedUser['report_weekly_enabled'] !== 1 || (int) $importedUser['report_monthly_enabled'] !== 1) {
    throw new RuntimeException('Import edilen kullanıcı rapor gönderim tiklerini almadı.');
}

$pdo->prepare("UPDATE users SET deleted_at = NOW(), status = 'passive' WHERE username = :username")
    ->execute(['username' => $importUsername]);
@unlink($importFile);
echo "Kullanıcı import DB: roller OK\n";

echo "HTTP smoke test tamamlandı.\n";
