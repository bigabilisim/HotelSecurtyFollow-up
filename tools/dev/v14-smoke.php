<?php

declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8004', '/');
$root = dirname(__DIR__, 2);
$cookieFile = sys_get_temp_dir() . '/otel-security-v14-smoke-cookie.txt';
$passwordCookieFile = sys_get_temp_dir() . '/otel-security-v14-password-cookie.txt';

require $root . '/app/bootstrap.php';

if (is_file($cookieFile)) {
    unlink($cookieFile);
}
if (is_file($passwordCookieFile)) {
    unlink($passwordCookieFile);
}

function v14_request(string $method, string $url, ?array $fields, string $cookieFile): array
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields ?? []));
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

function v14_csrf(string $body): string
{
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $body, $match)) {
        throw new RuntimeException('CSRF token bulunamadı.');
    }

    return $match[1];
}

function v14_assert_status(array $response, int $expected, string $label): void
{
    if ($response['status'] !== $expected) {
        file_put_contents(sys_get_temp_dir() . '/otel-security-v14-smoke-failure.html', $response['body']);
        throw new RuntimeException($label . ' beklenen HTTP ' . $expected . ', gelen ' . $response['status']);
    }

    echo $label . ': HTTP ' . $response['status'] . "\n";
}

$pdo = App\Core\Database::connection();
$oldMailEnabled = (int) $pdo->query("SELECT is_enabled FROM notification_channels WHERE code = 'mail' LIMIT 1")->fetchColumn();
$oldDepartmentEmail = $pdo->query("SELECT email FROM departments WHERE code = 'GENEL' LIMIT 1")->fetchColumn();
$passwordTestUserId = 0;

try {
    $pdo->exec("UPDATE notification_channels SET is_enabled = 0 WHERE code = 'mail'");
    $pdo->exec("UPDATE departments SET email = 'genel@example.test' WHERE code = 'GENEL'");

    $adminRoleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'admin' LIMIT 1")->fetchColumn();
    $passwordUsername = 'v14.pass.' . date('His');
    $passwordOld = 'old1234';
    $passwordNew = 'new1234';
    $passwordStmt = $pdo->prepare(
        'INSERT INTO users (full_name, username, email, password_hash, status)
         VALUES ("V14 Password Test", :username, "v14-password@example.test", :password_hash, "active")'
    );
    $passwordStmt->execute([
        'username' => $passwordUsername,
        'password_hash' => password_hash($passwordOld, PASSWORD_DEFAULT),
    ]);
    $passwordTestUserId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute([
        'user_id' => $passwordTestUserId,
        'role_id' => $adminRoleId,
    ]);

    $passwordLogin = v14_request('GET', $baseUrl . '/login', null, $passwordCookieFile);
    v14_assert_status($passwordLogin, 200, 'Şifre testi login ekranı');
    $passwordAuth = v14_request('POST', $baseUrl . '/login', [
        '_csrf' => v14_csrf($passwordLogin['body']),
        'username' => $passwordUsername,
        'password' => $passwordOld,
    ], $passwordCookieFile);
    v14_assert_status($passwordAuth, 302, 'Şifre testi login POST');
    $passwordForm = v14_request('GET', $baseUrl . '/account/password', null, $passwordCookieFile);
    v14_assert_status($passwordForm, 200, 'Şifre testi form');
    $passwordPost = v14_request('POST', $baseUrl . '/account/password', [
        '_csrf' => v14_csrf($passwordForm['body']),
        'current_password' => $passwordOld,
        'new_password' => $passwordNew,
        'new_password_again' => $passwordNew,
    ], $passwordCookieFile);
    v14_assert_status($passwordPost, 302, 'Şifre testi POST');
    $hashStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $hashStmt->execute(['id' => $passwordTestUserId]);
    if (!password_verify($passwordNew, (string) $hashStmt->fetchColumn())) {
        throw new RuntimeException('Şifre değiştirme veritabanında güncellenmedi.');
    }
    echo "Şifre değiştirme POST: OK\n";

    $login = v14_request('GET', $baseUrl . '/login', null, $cookieFile);
    v14_assert_status($login, 200, 'Login ekranı');

    $auth = v14_request('POST', $baseUrl . '/login', [
        '_csrf' => v14_csrf($login['body']),
        'username' => 'admin',
        'password' => 'admin',
    ], $cookieFile);
    v14_assert_status($auth, 302, 'Login POST');

    $passwordPage = v14_request('GET', $baseUrl . '/account/password', null, $cookieFile);
    v14_assert_status($passwordPage, 200, 'Şifre değiştirme ekranı');
    if (!str_contains($passwordPage['body'], 'Şifre Değiştir')) {
        throw new RuntimeException('Şifre değiştirme ekranı beklenen başlığı göstermedi.');
    }

    $recordsPage = v14_request('GET', $baseUrl . '/admin/records', null, $cookieFile);
    v14_assert_status($recordsPage, 200, 'Kayıt hareketleri ekranı');
    foreach (['Giriş yaptı', 'Çıkış yaptı'] as $needle) {
        if (!str_contains($recordsPage['body'], $needle)) {
            throw new RuntimeException('Kayıt ekranında hareket etiketi bulunamadı: ' . $needle);
        }
    }
    echo "Giriş ve çıkış hareketleri: OK\n";

    $usersPage = v14_request('GET', $baseUrl . '/admin/users', null, $cookieFile);
    v14_assert_status($usersPage, 200, 'Kullanıcı yönetimi');
    $newUsername = 'v14.mail.' . date('His');
    $newUser = v14_request('POST', $baseUrl . '/admin/users', [
        '_csrf' => v14_csrf($usersPage['body']),
        'full_name' => 'V14 Mail Test',
        'username' => $newUsername,
        'email' => 'v14-mail@example.test',
        'phone' => '',
        'password' => 'test1234',
        'status' => 'active',
        'role_ids' => [$adminRoleId],
    ], $cookieFile);
    v14_assert_status($newUser, 302, 'Yeni kullanıcı POST');

    $mailStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM notification_logs
         WHERE recipient_address = :email
           AND subject LIKE "%Giriş Bilgileriniz%"'
    );
    $mailStmt->execute(['email' => 'v14-mail@example.test']);
    if ((int) $mailStmt->fetchColumn() <= 0) {
        throw new RuntimeException('Yeni kullanıcı giriş bilgisi mail kuyruğu oluşmadı.');
    }
    echo "Yeni kullanıcı giriş maili: OK\n";

    $dashboard = v14_request('GET', $baseUrl . '/dashboard', null, $cookieFile);
    v14_assert_status($dashboard, 200, 'Dashboard');

    $categoryId = (int) $pdo->query("SELECT id FROM visitor_categories WHERE code = 'REZERVASYONSUZ_GIRIS' LIMIT 1")->fetchColumn();
    $departmentId = (int) $pdo->query("SELECT id FROM departments WHERE code = 'GENEL' LIMIT 1")->fetchColumn();
    if ($categoryId <= 0 || $departmentId <= 0) {
        throw new RuntimeException('Rezervasyonsuz kategori veya GENEL departmanı bulunamadı.');
    }

    $visitorName = 'V14 Rezervasyonsuz Test ' . date('His');
    $entry = v14_request('POST', $baseUrl . '/visits/entry', [
        '_csrf' => v14_csrf($dashboard['body']),
        'category_id' => $categoryId,
        'full_name' => $visitorName,
        'phone' => '05550004455',
        'vehicle_plate' => '34 VTT 14',
        'company' => 'V14 Test',
        'department_id' => $departmentId,
        'host_name' => 'Ön Büro',
        'purpose' => 'Rezervasyonsuz',
        'note' => 'V14 rezervasyonsuz smoke',
    ], $cookieFile);
    v14_assert_status($entry, 302, 'Rezervasyonsuz giriş POST');

    $reviewStmt = $pdo->prepare(
        'SELECT rr.*
         FROM reservationless_reviews rr
         INNER JOIN visits v ON v.id = rr.visit_id
         INNER JOIN visitors vi ON vi.id = v.visitor_id
         WHERE vi.full_name = :full_name
         ORDER BY rr.id DESC
         LIMIT 1'
    );
    $reviewStmt->execute(['full_name' => $visitorName]);
    $review = $reviewStmt->fetch(PDO::FETCH_ASSOC);
    if (!$review || $review['status'] !== 'pending') {
        throw new RuntimeException('Rezervasyonsuz giriş oda formu kaydı oluşturulmadı.');
    }

    $reviewPage = v14_request('GET', $baseUrl . '/reservationless/review?token=' . urlencode((string) $review['response_token']), null, $cookieFile);
    v14_assert_status($reviewPage, 200, 'Rezervasyonsuz oda formu');

    $submit = v14_request('POST', $baseUrl . '/reservationless/review', [
        '_csrf' => v14_csrf($reviewPage['body']),
        'token' => (string) $review['response_token'],
        'room_number' => '1408',
        'manager_note' => 'V14 smoke oda notu kaydedildi.',
    ], $cookieFile);
    v14_assert_status($submit, 200, 'Rezervasyonsuz oda formu POST');

    $statusStmt = $pdo->prepare('SELECT status, room_number FROM reservationless_reviews WHERE id = :id');
    $statusStmt->execute(['id' => (int) $review['id']]);
    $updated = $statusStmt->fetch(PDO::FETCH_ASSOC);
    if (!$updated || $updated['status'] !== 'submitted' || $updated['room_number'] !== '1408') {
        throw new RuntimeException('Rezervasyonsuz oda formu kaydı tamamlanmadı.');
    }

    echo "Rezervasyonsuz oda formu: OK\n";
} finally {
    $restore = $pdo->prepare("UPDATE notification_channels SET is_enabled = :enabled WHERE code = 'mail'");
    $restore->execute(['enabled' => $oldMailEnabled]);

    $restoreDepartment = $pdo->prepare("UPDATE departments SET email = :email WHERE code = 'GENEL'");
    $restoreDepartment->execute(['email' => $oldDepartmentEmail ?: null]);

    if ($passwordTestUserId > 0) {
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute(['user_id' => $passwordTestUserId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $passwordTestUserId]);
    }
}

echo "V1.4 smoke test tamamlandı.\n";
