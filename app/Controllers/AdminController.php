<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Backup;
use App\Models\Category;
use App\Models\Dashboard;
use App\Models\Department;
use App\Models\LoginIpBlock;
use App\Models\MailTemplate;
use App\Models\NotificationChannel;
use App\Models\NotificationRule;
use App\Models\Report;
use App\Models\Role;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserSuggestion;
use App\Models\VisitorDirectory;
use App\Models\VisitRecord;
use App\Models\Watchlist;
use App\Services\ReportService;
use App\Services\BackupService;
use App\Services\UserWelcomeService;
use App\Services\VisitRecordPdfService;
use App\Services\Notifications\NotificationQueue;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\NotificationSenderFactory;
use PDO;
use ZipArchive;

final class AdminController
{
    public function index(): string
    {
        Auth::requireLogin();

        return view('admin/index', [
            'title' => 'Yönetim',
            'settings' => (new Settings())->all(),
            'users' => Auth::can('settings.manage') ? (new User())->all() : [],
        ]);
    }

    public function guide(): string
    {
        Auth::requireLogin();

        return view('admin/guide', [
            'title' => 'V1 Kullanım Kılavuzu',
            'settings' => (new Settings())->all(),
            'backRoute' => '/admin',
            'backLabel' => 'Yönetim ekranına dön',
        ]);
    }

    public function suggestions(): string
    {
        Auth::requireLogin();

        $suggestions = new UserSuggestion();

        return view('admin/suggestions', [
            'title' => 'Kullanıcı Önerileri',
            'settings' => (new Settings())->all(),
            'suggestions' => $suggestions->all($_GET),
            'typeOptions' => $suggestions->typeOptions(),
            'priorityOptions' => $suggestions->priorityOptions(),
            'statusOptions' => $suggestions->statusOptions(),
            'filters' => [
                'status' => (string) ($_GET['status'] ?? ''),
                'type' => (string) ($_GET['type'] ?? ''),
            ],
        ]);
    }

    public function ipBlocks(): string
    {
        Auth::requireLogin();

        return view('admin/ip-blocks', [
            'title' => 'IP Güvenliği',
            'settings' => (new Settings())->all(),
            'blocks' => (new LoginIpBlock())->all(),
        ]);
    }

    public function releaseIpBlock(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/ip-blocks');

        $released = (new LoginIpBlock())->release(
            (int) ($_POST['id'] ?? 0),
            Auth::id(),
            (string) ($_POST['release_note'] ?? '')
        );

        flash($released ? 'success' : 'error', $released ? 'IP bloğu kaldırıldı.' : 'IP bloğu kaldırılamadı.');
        redirect('/admin/ip-blocks');
    }

    public function storeSuggestion(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/suggestions');

        $suggestions = new UserSuggestion();
        $action = (string) ($_POST['action'] ?? 'status');
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            if (!$suggestions->delete($id)) {
                flash('error', 'Öneri silinemedi.');
                redirect('/admin/suggestions');
            }

            flash('success', 'Öneri silindi.');
            redirect('/admin/suggestions');
        }

        if (!$suggestions->updateStatus($id, (string) ($_POST['status'] ?? 'new'), (int) Auth::id())) {
            flash('error', 'Öneri durumu güncellenemedi.');
            redirect('/admin/suggestions');
        }

        flash('success', 'Öneri durumu güncellendi.');
        redirect('/admin/suggestions');
    }

    public function categories(): string
    {
        Auth::requireLogin();

        $categoryModel = new Category();
        $editId = (int) ($_GET['edit_id'] ?? 0);

        return view('admin/categories', [
            'title' => 'Kategori Yönetimi',
            'settings' => (new Settings())->all(),
            'categories' => $categoryModel->all(),
            'editingCategory' => $editId > 0 ? $categoryModel->find($editId) : null,
            'users' => (new User())->all(),
        ]);
    }

    public function storeCategory(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/categories');

        $categoryModel = new Category();
        if (($_POST['action'] ?? '') === 'delete') {
            $categoryModel->delete((int) ($_POST['id'] ?? 0));
            flash('success', 'Kategori silindi.');
            redirect('/admin/categories');
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('error', 'Kategori adı zorunludur.');
            redirect('/admin/categories');
        }

        $categoryModel->save([
            'id' => (int) ($_POST['id'] ?? 0),
            'code' => trim($_POST['code'] ?? ''),
            'name' => $name,
            'color' => trim($_POST['color'] ?? '#0f766e'),
            'max_duration_minutes' => (int) ($_POST['max_duration_minutes'] ?? 0),
            'warning_before_minutes' => (int) ($_POST['warning_before_minutes'] ?? 10),
            'requires_department_approval' => isset($_POST['requires_department_approval']),
            'is_notification_enabled' => isset($_POST['is_notification_enabled']),
            'is_quick_access' => isset($_POST['is_quick_access']),
            'quick_access_order' => (int) ($_POST['quick_access_order'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
        ], Auth::id());

        flash('success', ((int) ($_POST['id'] ?? 0) > 0 ? 'Kategori güncellendi.' : 'Kategori kaydedildi.'));
        redirect('/admin/categories');
    }

    public function storeEscalationSettings(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin');

        $settings = [];
        for ($level = 1; $level <= 3; $level++) {
            $settings['escalation.level_' . $level . '_user_id'] = (string) max(0, (int) ($_POST['level_' . $level . '_user_id'] ?? 0));
            $settings['escalation.level_' . $level . '_after_minutes'] = (string) max(0, (int) ($_POST['level_' . $level . '_after_minutes'] ?? 0));
        }

        (new Settings())->setMany($settings);

        flash('success', 'Global eskalasyon zinciri güncellendi.');
        redirect('/admin');
    }

    public function storeSecuritySettings(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin');

        $enabled = isset($_POST['failed_login_alert_enabled']);
        $email = trim((string) ($_POST['failed_login_alert_email'] ?? ''));

        if ($enabled && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Hatalı şifre uyarısı için geçerli bir e-posta adresi yazın.');
            redirect('/admin');
        }

        (new Settings())->setMany([
            'security.failed_login_alert_enabled' => $enabled ? '1' : '0',
            'security.failed_login_alert_email' => $email,
        ]);

        flash('success', 'Hatalı şifre uyarı ayarı güncellendi.');
        redirect('/admin');
    }

    public function mailTemplates(): string
    {
        Auth::requireLogin();

        return view('admin/mail-templates', [
            'title' => 'Mail Şablonları',
            'settings' => (new Settings())->all(),
            'templates' => (new MailTemplate())->all(),
        ]);
    }

    public function storeMailTemplates(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/mail-templates');

        (new MailTemplate())->save($_POST['templates'] ?? []);

        flash('success', 'Mail şablonları güncellendi.');
        redirect('/admin/mail-templates');
    }

    public function departments(): string
    {
        Auth::requireLogin();

        $departmentModel = new Department();
        $editId = (int) ($_GET['edit_id'] ?? 0);

        return view('admin/departments', [
            'title' => 'Departman Yönetimi',
            'settings' => (new Settings())->all(),
            'departments' => $departmentModel->all(),
            'editingDepartment' => $editId > 0 ? $departmentModel->find($editId) : null,
            'users' => (new User())->all(),
        ]);
    }

    public function storeDepartment(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/departments');

        $departmentModel = new Department();
        if (($_POST['action'] ?? '') === 'delete') {
            $departmentModel->delete((int) ($_POST['id'] ?? 0));
            flash('success', 'Departman silindi.');
            redirect('/admin/departments');
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('error', 'Departman adı zorunludur.');
            redirect('/admin/departments');
        }

        $departmentModel->save([
            'id' => (int) ($_POST['id'] ?? 0),
            'code' => trim($_POST['code'] ?? ''),
            'name' => $name,
            'manager_user_id' => (int) ($_POST['manager_user_id'] ?? 0),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'status' => $_POST['status'] ?? 'active',
        ]);

        flash('success', ((int) ($_POST['id'] ?? 0) > 0 ? 'Departman güncellendi.' : 'Departman kaydedildi.'));
        redirect('/admin/departments');
    }

    public function users(): string
    {
        Auth::requireLogin();

        $userModel = new User();
        $editId = (int) ($_GET['edit_id'] ?? 0);

        return view('admin/users', [
            'title' => 'Kullanıcı ve Yetki Yönetimi',
            'settings' => (new Settings())->all(),
            'users' => $userModel->all(),
            'editingUser' => $editId > 0 ? $userModel->findForEdit($editId) : null,
            'currentUserId' => (int) Auth::id(),
            'roles' => (new Role())->all(),
            'departments' => (new Dashboard())->departments(),
            'panelPermissions' => $userModel->panelPermissionOptions(),
        ]);
    }

    public function visitors(): string
    {
        Auth::requireLogin();

        $visitorModel = new VisitorDirectory();
        $editId = (int) ($_GET['edit_id'] ?? 0);

        return view('admin/visitors', [
            'title' => 'Kayıtlı Kişiler',
            'settings' => (new Settings())->all(),
            'visitors' => $visitorModel->all($_GET),
            'editingVisitor' => $editId > 0 ? $visitorModel->find($editId) : null,
            'search' => trim($_GET['search'] ?? ''),
        ]);
    }

    public function storeVisitor(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/visitors');

        $visitorModel = new VisitorDirectory();
        if (($_POST['action'] ?? '') === 'delete') {
            if (!$visitorModel->delete((int) ($_POST['id'] ?? 0))) {
                flash('error', 'Kişi kaydı silinemedi.');
                redirect('/admin/visitors');
            }

            flash('success', 'Kişi kaydı silindi.');
            redirect('/admin/visitors');
        }

        try {
            $visitorModel->save([
                'id' => (int) ($_POST['id'] ?? 0),
                'full_name' => trim($_POST['full_name'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'company' => trim($_POST['company'] ?? ''),
                'vehicle_plate' => $this->formatVehiclePlate(trim($_POST['vehicle_plate'] ?? '')),
                'note' => trim($_POST['note'] ?? ''),
            ]);
        } catch (\Throwable $error) {
            flash('error', $error->getMessage());
            redirect('/admin/visitors');
        }

        flash('success', 'Kişi kaydı kaydedildi.');
        redirect('/admin/visitors');
    }

    public function watchlist(): string
    {
        Auth::requireLogin();

        $watchlist = new Watchlist();
        $editId = (int) ($_GET['edit_id'] ?? 0);

        return view('admin/watchlist', [
            'title' => 'Kara / Uyarı Listesi',
            'settings' => (new Settings())->all(),
            'entries' => $watchlist->all(),
            'editingEntry' => $editId > 0 ? $watchlist->find($editId) : null,
        ]);
    }

    public function storeWatchlist(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/watchlist');

        $watchlist = new Watchlist();
        if (($_POST['action'] ?? '') === 'delete') {
            if (!$watchlist->delete((int) ($_POST['id'] ?? 0))) {
                flash('error', 'Liste kaydı silinemedi.');
                redirect('/admin/watchlist');
            }

            flash('success', 'Liste kaydı silindi.');
            redirect('/admin/watchlist');
        }

        try {
            $watchlist->save([
                'id' => (int) ($_POST['id'] ?? 0),
                'list_type' => $_POST['list_type'] ?? 'warning',
                'match_type' => $_POST['match_type'] ?? 'name',
                'match_value' => trim($_POST['match_value'] ?? ''),
                'reason' => trim($_POST['reason'] ?? ''),
                'action_note' => trim($_POST['action_note'] ?? ''),
                'is_active' => isset($_POST['is_active']),
            ], Auth::id());
        } catch (\Throwable $error) {
            flash('error', $error->getMessage());
            redirect('/admin/watchlist');
        }

        flash('success', 'Liste kaydı kaydedildi.');
        redirect('/admin/watchlist');
    }

    public function quickAddWatchlist(): never
    {
        Auth::requireLogin();

        $returnRoute = $this->watchlistReturnRoute((string) ($_POST['return_route'] ?? '/admin/records'));
        $returnParams = $this->watchlistReturnParams($_POST);

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect($returnRoute, $returnParams);
        }

        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        if ($fullName === '') {
            flash('error', 'Kara listeye almak için kişi adı bulunamadı.');
            redirect($returnRoute, $returnParams);
        }

        $watchlist = new Watchlist();
        if ($watchlist->findActive('blacklist', 'name', $fullName)) {
            flash('success', $fullName . ' zaten kara listede.');
            redirect($returnRoute, $returnParams);
        }

        try {
            $watchlist->save([
                'list_type' => 'blacklist',
                'match_type' => 'name',
                'match_value' => $fullName,
                'reason' => trim((string) ($_POST['reason'] ?? 'Kayıt listesinden kara listeye alındı.')),
                'action_note' => trim((string) ($_POST['action_note'] ?? 'Giriş verme, yöneticiye haber ver.')),
                'is_active' => true,
            ], Auth::id());
        } catch (\Throwable $error) {
            flash('error', $error->getMessage());
            redirect($returnRoute, $returnParams);
        }

        flash('success', $fullName . ' kara listeye alındı.');
        redirect($returnRoute, $returnParams);
    }

    public function storeUser(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/users');

        $userModel = new User();
        if (($_POST['action'] ?? '') === 'delete') {
            if (!$userModel->delete((int) ($_POST['id'] ?? 0), (int) Auth::id())) {
                flash('error', 'Kendi kullanıcınızı silemezsiniz.');
                redirect('/admin/users');
            }

            flash('success', 'Kullanıcı silindi.');
            redirect('/admin/users');
        }

        $userId = (int) ($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($fullName === '' || $username === '' || ($userId <= 0 && strlen($password) < 4) || ($password !== '' && strlen($password) < 4)) {
            flash('error', 'Ad soyad, kullanıcı adı ve yeni kayıt için en az 4 karakter şifre zorunludur.');
            redirect('/admin/users');
        }

        $isNewUser = $userId <= 0 && !$userModel->findByUsername($username);
        $savedUserId = $userModel->save([
            'id' => $userId,
            'department_id' => (int) ($_POST['department_id'] ?? 0),
            'full_name' => $fullName,
            'username' => $username,
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'password' => $password,
            'status' => $_POST['status'] ?? 'active',
            'report_daily_enabled' => isset($_POST['report_daily_enabled']),
            'report_weekly_enabled' => isset($_POST['report_weekly_enabled']),
            'report_monthly_enabled' => isset($_POST['report_monthly_enabled']),
            'mobile_notification_enabled' => isset($_POST['mobile_notification_enabled']),
            'mobile_notification_entry_enabled' => isset($_POST['mobile_notification_entry_enabled']),
            'mobile_notification_exit_enabled' => isset($_POST['mobile_notification_exit_enabled']),
            'role_ids' => $_POST['role_ids'] ?? [],
            'panel_permissions' => $_POST['panel_permissions'] ?? [],
        ]);

        $message = $userId > 0 ? 'Kullanıcı güncellendi.' : 'Kullanıcı ve rolleri kaydedildi.';
        if ($isNewUser && $password !== '') {
            $mailResult = (new UserWelcomeService())->send($userModel->find($savedUserId) ?? [], $password);
            $message .= ' Mail: ' . ($mailResult['sent'] > 0 ? 'gönderildi.' : $mailResult['message']);
        }

        flash('success', $message);
        redirect('/admin/users');
    }

    public function userImportTemplate(): never
    {
        Auth::requireLogin();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="kullanici-import-ornek.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            exit;
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, [
            'full_name',
            'username',
            'email',
            'phone',
            'department_code',
            'password',
            'roles',
            'status',
            'daily_report',
            'weekly_report',
            'monthly_report',
            'web_push',
            'entry_push',
            'exit_push',
        ], ';');
        fputcsv($output, [
            'Ayşe Operasyon',
            'ayse.operasyon',
            'ayse.operasyon@otel.local',
            '05550000001',
            'GENEL',
            '1234',
            'Operasyon Müdürü|Gece Müdürü',
            'active',
            'evet',
            'evet',
            'evet',
            'evet',
            'evet',
            'evet',
        ], ';');
        fputcsv($output, [
            'Mehmet Gece',
            'mehmet.gece',
            'mehmet.gece@otel.local',
            '05550000002',
            'GUVENLIK',
            '1234',
            'Gece Müdürü',
            'active',
            'evet',
            'hayır',
            'hayır',
            'evet',
            'evet',
            'hayır',
        ], ';');
        fclose($output);
        exit;
    }

    public function importUsers(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/users');

        try {
            $rows = $this->readUserImportRows($_FILES['user_import'] ?? []);
        } catch (\Throwable $error) {
            flash('error', 'Import dosyası okunamadı: ' . $error->getMessage());
            redirect('/admin/users');
        }

        if (!$rows) {
            flash('error', 'Import dosyasında aktarılacak satır bulunamadı.');
            redirect('/admin/users');
        }

        $userModel = new User();
        $departmentMap = $this->departmentImportMap();
        $roleMap = $this->roleImportMap();
        $imported = 0;
        $welcomeSent = 0;
        $welcomeFailed = 0;
        $skipped = [];
        $welcomeService = new UserWelcomeService();

        foreach ($rows as $index => $row) {
            $line = (int) ($row['_line'] ?? ($index + 2));
            $fullName = trim((string) ($row['full_name'] ?? ''));
            $username = trim((string) ($row['username'] ?? ''));
            $password = (string) ($row['password'] ?? '');

            if ($fullName === '' || $username === '') {
                $skipped[] = "Satır {$line}: ad soyad ve kullanıcı adı zorunlu.";
                continue;
            }

            $existingUser = $userModel->findByUsername($username);
            $userId = (int) ($existingUser['id'] ?? 0);

            if ($userId <= 0 && strlen($password) < 4) {
                $skipped[] = "Satır {$line}: yeni kullanıcı için en az 4 karakter şifre zorunlu.";
                continue;
            }

            if ($password !== '' && strlen($password) < 4) {
                $skipped[] = "Satır {$line}: şifre en az 4 karakter olmalı.";
                continue;
            }
            $isNewUser = $userId <= 0;

            $departmentId = 0;
            $departmentValue = trim((string) ($row['department_code'] ?? ''));
            if ($departmentValue !== '') {
                $departmentId = (int) ($departmentMap[$this->lookupKey($departmentValue)] ?? 0);
                if ($departmentId <= 0) {
                    $skipped[] = "Satır {$line}: departman bulunamadı ({$departmentValue}).";
                    continue;
                }
            }

            [$roleIds, $unknownRoles] = $this->roleIdsFromImport((string) ($row['roles'] ?? ''), $roleMap);
            if ($unknownRoles) {
                $skipped[] = 'Satır ' . $line . ': rol bulunamadı (' . implode(', ', $unknownRoles) . ').';
                continue;
            }

            if (!$roleIds) {
                $skipped[] = "Satır {$line}: en az bir rol zorunlu.";
                continue;
            }

            try {
                $savedUserId = $userModel->save([
                    'id' => $userId,
                    'department_id' => $departmentId,
                    'full_name' => $fullName,
                    'username' => $username,
                    'email' => trim((string) ($row['email'] ?? '')),
                    'phone' => trim((string) ($row['phone'] ?? '')),
                    'password' => $password,
                    'status' => $this->normalizeImportStatus((string) ($row['status'] ?? 'active')),
                    'report_daily_enabled' => array_key_exists('daily_report', $row)
                        ? $this->importBoolean((string) $row['daily_report'])
                        : !empty($existingUser['report_daily_enabled']),
                    'report_weekly_enabled' => array_key_exists('weekly_report', $row)
                        ? $this->importBoolean((string) $row['weekly_report'])
                        : !empty($existingUser['report_weekly_enabled']),
                    'report_monthly_enabled' => array_key_exists('monthly_report', $row)
                        ? $this->importBoolean((string) $row['monthly_report'])
                        : !empty($existingUser['report_monthly_enabled']),
                    'mobile_notification_enabled' => array_key_exists('web_push', $row)
                        ? $this->importBoolean((string) $row['web_push'])
                        : !empty($existingUser['mobile_notification_enabled']),
                    'mobile_notification_entry_enabled' => array_key_exists('entry_push', $row)
                        ? $this->importBoolean((string) $row['entry_push'])
                        : ($existingUser ? !empty($existingUser['mobile_notification_entry_enabled']) : true),
                    'mobile_notification_exit_enabled' => array_key_exists('exit_push', $row)
                        ? $this->importBoolean((string) $row['exit_push'])
                        : !empty($existingUser['mobile_notification_exit_enabled']),
                    'role_ids' => $roleIds,
                ]);
                $imported++;

                if ($isNewUser && $password !== '') {
                    $mailResult = $welcomeService->send($userModel->find($savedUserId) ?? [], $password);
                    if ((int) ($mailResult['sent'] ?? 0) > 0) {
                        $welcomeSent++;
                    } else {
                        $welcomeFailed++;
                    }
                }
            } catch (\Throwable $error) {
                $skipped[] = "Satır {$line}: " . $error->getMessage();
            }
        }

        if ($imported > 0) {
            $mailText = $welcomeSent > 0 || $welcomeFailed > 0
                ? ' Giriş bilgisi maili: ' . $welcomeSent . ' gönderildi, ' . $welcomeFailed . ' başarısız.'
                : '';
            flash('success', $imported . ' kullanıcı içe aktarıldı veya güncellendi.' . $mailText);
        }

        if ($skipped) {
            $message = count($skipped) . ' satır atlandı. ' . implode(' ', array_slice($skipped, 0, 5));
            if (count($skipped) > 5) {
                $message .= ' ...';
            }
            flash('error', $message);
        }

        redirect('/admin/users');
    }

    public function notificationRules(): string
    {
        Auth::requireLogin();

        $ruleModel = new NotificationRule();
        $editId = (int) ($_GET['edit_id'] ?? 0);

        return view('admin/notification-rules', [
            'title' => 'Bildirim Kuralları',
            'settings' => (new Settings())->all(),
            'rules' => $ruleModel->all(),
            'editingRule' => $editId > 0 ? $ruleModel->findForEdit($editId) : null,
            'channels' => $ruleModel->channels(),
            'categories' => (new Dashboard())->categories(),
            'departments' => (new Dashboard())->departments(),
            'roles' => (new Role())->all(),
        ]);
    }

    public function notificationChannels(): string
    {
        Auth::requireLogin();

        return view('admin/notification-channels', [
            'title' => 'Bildirim Kanal Ayarları',
            'settings' => (new Settings())->all(),
            'channels' => (new NotificationChannel())->all(),
        ]);
    }

    public function storeNotificationChannel(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/notification-channels');

        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $channelModel = new NotificationChannel();
        $existing = $channelModel->findByCode($code);

        if ($code === '' || $name === '' || !$existing) {
            flash('error', 'Bildirim kanalı bulunamadı.');
            redirect('/admin/notification-channels');
        }

        $config = $this->notificationChannelConfig($code, $this->decodeJson((string) ($existing['config_json'] ?? '{}')));

        if (($_POST['action'] ?? '') === 'test') {
            $this->testNotificationChannel($code, $name, $config);
            redirect('/admin/notification-channels');
        }

        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($configJson === false) {
            flash('error', 'Kanal ayarları JSON formatına çevrilemedi.');
            redirect('/admin/notification-channels');
        }

        $channelModel->update($code, $name, isset($_POST['is_enabled']), $configJson);

        flash('success', 'Bildirim kanalı güncellendi.');
        redirect('/admin/notification-channels');
    }

    public function storeNotificationRule(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/notification-rules');

        $ruleModel = new NotificationRule();
        if (($_POST['action'] ?? '') === 'delete') {
            $ruleModel->delete((int) ($_POST['id'] ?? 0));
            flash('success', 'Bildirim kuralı silindi.');
            redirect('/admin/notification-rules');
        }

        $name = trim($_POST['name'] ?? '');
        $channelIds = $_POST['channel_ids'] ?? [];

        if ($name === '' || !$channelIds) {
            flash('error', 'Kural adı ve en az bir kanal zorunludur.');
            redirect('/admin/notification-rules');
        }

        $ruleId = (int) ($_POST['id'] ?? 0);
        $ruleModel->save([
            'id' => $ruleId,
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'name' => $name,
            'event_type' => $_POST['event_type'] ?? 'entry',
            'priority' => (int) ($_POST['priority'] ?? 100),
            'condition_json' => trim($_POST['condition_json'] ?? ''),
            'message_template' => trim($_POST['message_template'] ?? ''),
            'is_active' => isset($_POST['is_active']),
            'channel_ids' => $channelIds,
            'recipient_type' => $_POST['recipient_type'] ?? 'custom',
            'recipient_department_id' => (int) ($_POST['recipient_department_id'] ?? 0),
            'recipient_role_id' => (int) ($_POST['recipient_role_id'] ?? 0),
            'custom_name' => trim($_POST['custom_name'] ?? ''),
            'custom_email' => trim($_POST['custom_email'] ?? ''),
            'custom_phone' => trim($_POST['custom_phone'] ?? ''),
            'custom_telegram_chat_id' => trim($_POST['custom_telegram_chat_id'] ?? ''),
            'custom_whatsapp_number' => trim($_POST['custom_whatsapp_number'] ?? ''),
        ]);

        flash('success', ($ruleId > 0 ? 'Bildirim kuralı güncellendi.' : 'Bildirim kuralı kaydedildi.'));
        redirect('/admin/notification-rules');
    }

    public function reports(): string
    {
        Auth::requireLogin();

        $reportModel = new Report();
        $editId = (int) ($_GET['edit_id'] ?? 0);

        return view('admin/reports', [
            'title' => 'Raporlar',
            'settings' => (new Settings())->all(),
            'schedules' => $reportModel->schedules(),
            'editingSchedule' => $editId > 0 ? $reportModel->findSchedule($editId) : null,
            'logs' => $reportModel->logs(),
        ]);
    }

    public function visitRecords(): string
    {
        Auth::requireLogin();

        $recordModel = new VisitRecord();
        $filters = $recordModel->filters($_GET);
        $total = $recordModel->count($filters);

        return view('admin/visit-records', [
            'title' => 'Kayıtlar',
            'settings' => (new Settings())->all(),
            'filters' => $filters,
            'records' => $recordModel->records($filters, 250),
            'total' => $total,
            'statusLabels' => $recordModel->statusLabels(),
            'categories' => (new Dashboard())->categories(),
            'departments' => (new Dashboard())->departments(),
        ]);
    }

    public function downloadVisitRecordsPdf(): never
    {
        Auth::requireLogin();

        $recordModel = new VisitRecord();
        $filters = $recordModel->filters($_GET);
        $records = $recordModel->records($filters, 1000);
        $total = $recordModel->count($filters);
        $filePath = (new VisitRecordPdfService())->create($records, $filters, $total);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    public function sendVisitRecordsPdf(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/records');

        $recipientEmail = trim($_POST['recipient_email'] ?? '');
        $recipientName = trim($_POST['recipient_name'] ?? '');
        $filters = (new VisitRecord())->filters($_POST);

        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'PDF göndermek için geçerli bir e-posta adresi yazın.');
            redirect('/admin/records', $filters);
        }

        $recordModel = new VisitRecord();
        $records = $recordModel->records($filters, 1000);
        $total = $recordModel->count($filters);
        $filePath = (new VisitRecordPdfService())->create($records, $filters, $total);
        $subject = trim($_POST['subject'] ?? '') ?: 'Otel Güvenlik Kayıtları PDF';
        $message = trim($_POST['message'] ?? '') ?: 'Filtrelenen giriş çıkış kayıtları PDF olarak ekte gönderilmiştir.';
        $queued = (new NotificationService())->queueMail(
            $recipientEmail,
            $recipientName,
            $subject,
            $message . "\n\nKayıt sayısı: " . $total,
            $filePath,
            basename($filePath)
        );

        flash($queued > 0 ? 'success' : 'error', $queued > 0 ? 'PDF mail kuyruğuna eklendi.' : 'PDF mail kuyruğuna eklenemedi. Mail kanalını kontrol edin.');
        redirect('/admin/records', $filters);
    }

    public function storeReport(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/reports');

        $reportModel = new Report();

        if (($_POST['action'] ?? '') === 'run') {
            $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
            if ($scheduleId <= 0) {
                flash('error', 'Rapor planı seçilmedi.');
                redirect('/admin/reports');
            }

            $result = (new ReportService())->runSchedule($scheduleId);
            $processed = (new NotificationQueue())->process(max(10, (int) $result['notifications'] + 5));
            flash(
                'success',
                'Rapor oluşturuldu. Kuyruk: ' . $result['notifications']
                . ' | Gönderilen: ' . $processed['sent']
                . ' | Başarısız: ' . $processed['failed']
                . ' | Atlanan: ' . $processed['skipped']
            );
            redirect('/admin/reports');
        }

        if (($_POST['action'] ?? '') === 'delete') {
            if (!$reportModel->deleteSchedule((int) ($_POST['schedule_id'] ?? 0))) {
                flash('error', 'Rapor planı silinemedi.');
                redirect('/admin/reports');
            }

            flash('success', 'Rapor planı silindi.');
            redirect('/admin/reports');
        }

        try {
            $reportModel->saveSchedule([
                'id' => (int) ($_POST['schedule_id'] ?? 0),
                'name' => trim($_POST['name'] ?? ''),
                'report_type' => $_POST['report_type'] ?? 'daily',
                'run_time' => $_POST['run_time'] ?? '23:30:00',
                'day_of_month' => (int) ($_POST['day_of_month'] ?? 0),
                'output_format' => $_POST['output_format'] ?? 'html',
                'is_active' => isset($_POST['is_active']),
            ]);
        } catch (\Throwable $error) {
            flash('error', $error->getMessage());
            redirect('/admin/reports');
        }

        flash('success', 'Rapor planı kaydedildi.');
        redirect('/admin/reports');
    }

    public function backups(): string
    {
        Auth::requireLogin();

        return view('admin/backups', [
            'title' => 'Yedekleme',
            'settings' => (new Settings())->all(),
            'jobs' => (new Backup())->jobs(),
            'logs' => (new Backup())->logs(),
        ]);
    }

    public function downloadBackup(): never
    {
        Auth::requireLogin();

        $log = (new Backup())->findLog((int) ($_GET['log_id'] ?? 0));
        if (!$log || $log['status'] !== 'success') {
            flash('error', 'İndirilebilir yedek kaydı bulunamadı.');
            redirect('/admin/backups');
        }

        $filePath = (string) ($log['file_path'] ?? '');
        $realPath = realpath($filePath);
        $backupRoot = realpath(BASE_PATH . '/storage/backups');

        if (!$realPath || !$backupRoot || !str_starts_with($realPath, $backupRoot . DIRECTORY_SEPARATOR) || !is_file($realPath) || !is_readable($realPath)) {
            flash('error', 'Yedek dosyası bulunamadı veya indirilebilir değil.');
            redirect('/admin/backups');
        }

        $fileName = basename((string) ($log['file_name'] ?: $realPath));
        if (!str_ends_with(strtolower($fileName), '.zip')) {
            $fileName .= '.zip';
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
        header('Content-Length: ' . filesize($realPath));
        header('X-Content-Type-Options: nosniff');
        readfile($realPath);
        exit;
    }

    public function storeBackup(): never
    {
        Auth::requireLogin();
        $this->verifyCsrf('/admin/backups');

        if (($_POST['action'] ?? '') === 'run') {
            $jobId = (int) ($_POST['job_id'] ?? 0);
            if ($jobId <= 0) {
                flash('error', 'Yedek işi seçilmedi.');
                redirect('/admin/backups');
            }

            $result = (new BackupService())->runJob($jobId, Auth::id());
            $processed = (new NotificationQueue())->process(max(5, (int) ($result['mail_queued'] ?? 0) + 3));
            $mailText = (int) ($result['mail_queued'] ?? 0) > 0
                ? ' Mail kuyruğu: ' . (int) $result['mail_queued'] . ' | Gönderilen: ' . $processed['sent'] . ' | Başarısız: ' . $processed['failed'] . ' | Atlanan: ' . $processed['skipped']
                : ' Mail alıcısı aktif değil veya tanımlı değil.';
            flash('success', 'Yedek oluşturuldu: ' . basename((string) $result['file_path']) . '.' . $mailText);
            redirect('/admin/backups');
        }

        (new Backup())->updateJob([
            'id' => (int) ($_POST['job_id'] ?? 0),
            'frequency' => $_POST['frequency'] ?? 'daily',
            'run_time' => $_POST['run_time'] ?? '23:30:00',
            'retention_days' => (int) ($_POST['retention_days'] ?? 30),
            'include_database' => isset($_POST['include_database']),
            'include_uploads' => isset($_POST['include_uploads']),
            'mail_enabled' => isset($_POST['mail_enabled']),
            'mail_recipient_name' => trim($_POST['mail_recipient_name'] ?? ''),
            'mail_recipient_email' => trim($_POST['mail_recipient_email'] ?? ''),
            'is_active' => isset($_POST['is_active']),
        ]);

        flash('success', 'Yedekleme planı güncellendi.');
        redirect('/admin/backups');
    }

    private function readUserImportRows(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage($error));
        }

        $path = (string) ($file['tmp_name'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new \RuntimeException('Yüklenen dosya bulunamadı.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            throw new \RuntimeException('Sadece CSV veya XLSX dosyası yükleyebilirsiniz.');
        }

        return $extension === 'xlsx'
            ? $this->readXlsxImportRows($path)
            : $this->readCsvImportRows($path);
    }

    private function readCsvImportRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('CSV dosyası açılamadı.');
        }

        $sample = fgets($handle) ?: '';
        rewind($handle);

        $delimiter = $this->detectCsvDelimiter($sample);
        $headers = null;
        $rows = [];
        $line = 0;

        while (($record = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if ($this->isImportRowEmpty($record)) {
                continue;
            }

            if ($headers === null) {
                $headers = $this->normalizeImportHeaders($record);
                continue;
            }

            $rows[] = $this->combineImportRow($headers, $record, $line);
        }

        fclose($handle);

        if ($headers === null) {
            throw new \RuntimeException('Başlık satırı bulunamadı.');
        }

        return $rows;
    }

    private function readXlsxImportRows(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('Sunucuda XLSX okumak için PHP ZipArchive eklentisi kurulu olmalı.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('XLSX dosyası açılamadı.');
        }

        $sheetName = $this->firstXlsxSheetName($zip);
        $sheetContent = $zip->getFromName($sheetName);
        if ($sheetContent === false) {
            $zip->close();
            throw new \RuntimeException('XLSX çalışma sayfası okunamadı.');
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $xml = simplexml_load_string($sheetContent);
        if ($xml === false) {
            $zip->close();
            throw new \RuntimeException('XLSX çalışma sayfası geçersiz.');
        }

        $namespace = $xml->getNamespaces(true)[''] ?? '';
        $sheet = $namespace !== '' ? $xml->children($namespace) : $xml;
        $rawRows = [];

        foreach ($sheet->sheetData->row as $rowNode) {
            $rowNamespace = $rowNode->getNamespaces(true)[''] ?? $namespace;
            $row = $rowNamespace !== '' ? $rowNode->children($rowNamespace) : $rowNode;
            $values = [];
            $maxIndex = -1;

            foreach ($row->c as $cell) {
                $cellAttributes = $cell->attributes();
                $columnIndex = $this->xlsxColumnIndex((string) ($cellAttributes['r'] ?? ''));
                if ($columnIndex < 0) {
                    $columnIndex = count($values);
                }

                $values[$columnIndex] = $this->xlsxCellValue($cell, $sharedStrings);
                $maxIndex = max($maxIndex, $columnIndex);
            }

            if ($maxIndex < 0) {
                continue;
            }

            $lineValues = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $lineValues[$i] = $values[$i] ?? '';
            }

            $rawRows[] = [
                'line' => (int) (($rowNode->attributes()['r'] ?? null) ?: count($rawRows) + 1),
                'values' => $lineValues,
            ];
        }

        $zip->close();

        $headers = null;
        $rows = [];
        foreach ($rawRows as $rawRow) {
            $values = $rawRow['values'];
            if ($this->isImportRowEmpty($values)) {
                continue;
            }

            if ($headers === null) {
                $headers = $this->normalizeImportHeaders($values);
                continue;
            }

            $rows[] = $this->combineImportRow($headers, $values, (int) $rawRow['line']);
        }

        if ($headers === null) {
            throw new \RuntimeException('Başlık satırı bulunamadı.');
        }

        return $rows;
    }

    private function firstXlsxSheetName(ZipArchive $zip): string
    {
        $sheetNames = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                $sheetNames[] = $name;
            }
        }

        sort($sheetNames, SORT_NATURAL);
        if (!$sheetNames) {
            throw new \RuntimeException('XLSX içinde çalışma sayfası bulunamadı.');
        }

        return $sheetNames[0];
    }

    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return [];
        }

        $namespace = $xml->getNamespaces(true)[''] ?? '';
        $root = $namespace !== '' ? $xml->children($namespace) : $xml;
        $strings = [];

        foreach ($root->si as $item) {
            $itemNode = $namespace !== '' ? $item->children($namespace) : $item;
            $text = '';

            if (isset($itemNode->t)) {
                $text .= (string) $itemNode->t;
            }

            foreach ($itemNode->r as $run) {
                $runNode = $namespace !== '' ? $run->children($namespace) : $run;
                $text .= (string) ($runNode->t ?? '');
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function xlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $namespace = $cell->getNamespaces(true)[''] ?? '';
        $node = $namespace !== '' ? $cell->children($namespace) : $cell;
        $type = (string) (($cell->attributes()['t'] ?? null) ?: '');

        if ($type === 's') {
            $index = (int) ($node->v ?? -1);
            return trim((string) ($sharedStrings[$index] ?? ''));
        }

        if ($type === 'inlineStr') {
            return trim((string) ($node->is->t ?? ''));
        }

        return trim((string) ($node->v ?? ''));
    }

    private function xlsxColumnIndex(string $cellReference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $cellReference, $matches)) {
            return -1;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    private function detectCsvDelimiter(string $sample): string
    {
        $delimiters = [
            ';' => substr_count($sample, ';'),
            ',' => substr_count($sample, ','),
            "\t" => substr_count($sample, "\t"),
        ];
        arsort($delimiters);

        return (string) array_key_first($delimiters);
    }

    private function normalizeImportHeaders(array $headers): array
    {
        $aliases = [
            'full_name' => 'full_name',
            'ad_soyad' => 'full_name',
            'adsoyad' => 'full_name',
            'adi_soyadi' => 'full_name',
            'isim_soyisim' => 'full_name',
            'isim' => 'full_name',
            'username' => 'username',
            'user_name' => 'username',
            'kullanici_adi' => 'username',
            'kullanici' => 'username',
            'email' => 'email',
            'e_posta' => 'email',
            'eposta' => 'email',
            'mail' => 'email',
            'phone' => 'phone',
            'telefon' => 'phone',
            'gsm' => 'phone',
            'department_code' => 'department_code',
            'department' => 'department_code',
            'department_name' => 'department_code',
            'departman' => 'department_code',
            'departman_kodu' => 'department_code',
            'password' => 'password',
            'sifre' => 'password',
            'roles' => 'roles',
            'role' => 'roles',
            'roller' => 'roles',
            'rol' => 'roles',
            'status' => 'status',
            'durum' => 'status',
            'daily_report' => 'daily_report',
            'gunluk_rapor' => 'daily_report',
            'gunluk_rapor_gonder' => 'daily_report',
            'haftalik_report' => 'weekly_report',
            'weekly_report' => 'weekly_report',
            'haftalik_rapor' => 'weekly_report',
            'haftalik_rapor_gonder' => 'weekly_report',
            'monthly_report' => 'monthly_report',
            'aylik_rapor' => 'monthly_report',
            'aylik_rapor_gonder' => 'monthly_report',
        ];

        return array_map(function ($header) use ($aliases): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header)) ?? '';
            $key = $this->lookupKey($header);

            return $aliases[$key] ?? $key;
        }, $headers);
    }

    private function combineImportRow(array $headers, array $record, int $line): array
    {
        $allowed = array_flip([
            'full_name',
            'username',
            'email',
            'phone',
            'department_code',
            'password',
            'roles',
            'status',
            'daily_report',
            'weekly_report',
            'monthly_report',
        ]);

        $row = ['_line' => $line];
        foreach ($headers as $index => $field) {
            if (!isset($allowed[$field])) {
                continue;
            }

            $row[$field] = trim((string) ($record[$index] ?? ''));
        }

        return $row;
    }

    private function isImportRowEmpty(array $record): bool
    {
        foreach ($record as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function departmentImportMap(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, code, name
             FROM departments
             WHERE deleted_at IS NULL'
        );

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $department) {
            $map[$this->lookupKey((string) $department['code'])] = (int) $department['id'];
            $map[$this->lookupKey((string) $department['name'])] = (int) $department['id'];
        }

        return $map;
    }

    private function roleImportMap(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, code, name
             FROM roles
             WHERE deleted_at IS NULL'
        );

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $role) {
            $map[$this->lookupKey((string) $role['code'])] = (int) $role['id'];
            $map[$this->lookupKey((string) $role['name'])] = (int) $role['id'];
        }

        return $map;
    }

    private function roleIdsFromImport(string $value, array $roleMap): array
    {
        $value = trim($value);
        if ($value === '') {
            $value = 'security';
        }

        $ids = [];
        $unknown = [];
        $parts = preg_split('/[|,;]+/u', $value) ?: [];

        foreach ($parts as $part) {
            $role = trim($part);
            if ($role === '') {
                continue;
            }

            $key = $this->lookupKey($role);
            if (!isset($roleMap[$key])) {
                $unknown[] = $role;
                continue;
            }

            $ids[] = (int) $roleMap[$key];
        }

        return [array_values(array_unique($ids)), $unknown];
    }

    private function normalizeImportStatus(string $status): string
    {
        return match ($this->lookupKey($status)) {
            'passive', 'pasif' => 'passive',
            'locked', 'kilitli' => 'locked',
            default => 'active',
        };
    }

    private function importBoolean(string $value): bool
    {
        return in_array($this->lookupKey($value), ['1', 'true', 'evet', 'yes', 'on', 'aktif'], true);
    }

    private function lookupKey(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'ç' => 'c',
            'ğ' => 'g',
            'ı' => 'i',
            'i̇' => 'i',
            'ö' => 'o',
            'ş' => 's',
            'ü' => 'u',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?? '';

        return trim($value, '_');
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu sunucu limitini aşıyor.',
            UPLOAD_ERR_PARTIAL => 'Dosya eksik yüklendi.',
            UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
            default => 'Dosya yükleme hatası oluştu.',
        };
    }

    private function watchlistReturnRoute(string $route): string
    {
        return in_array($route, ['/admin/records', '/admin/visitors', '/admin/watchlist'], true)
            ? $route
            : '/admin/records';
    }

    private function watchlistReturnParams(array $input): array
    {
        $params = [];
        foreach (['date_from', 'date_to', 'category_id', 'department_id', 'status', 'search'] as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function formatVehiclePlate(string $value): string
    {
        $normalized = strtr($value, [
            'ç' => 'C',
            'Ç' => 'C',
            'ğ' => 'G',
            'Ğ' => 'G',
            'ı' => 'I',
            'İ' => 'I',
            'ö' => 'O',
            'Ö' => 'O',
            'ş' => 'S',
            'Ş' => 'S',
            'ü' => 'U',
            'Ü' => 'U',
        ]);
        $compact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $normalized) ?? '');
        $length = strlen($compact);
        $index = 0;
        $province = '';
        $letters = '';
        $numbers = '';

        while ($index < $length && ctype_digit($compact[$index]) && strlen($province) < 2) {
            $province .= $compact[$index];
            $index++;
        }

        while ($index < $length && ctype_alpha($compact[$index]) && strlen($letters) < 3) {
            $letters .= $compact[$index];
            $index++;
        }

        while ($index < $length && ctype_digit($compact[$index]) && strlen($numbers) < 4) {
            $numbers .= $compact[$index];
            $index++;
        }

        return implode(' ', array_filter([$province, $letters, $numbers], static fn (string $part): bool => $part !== ''));
    }

    private function verifyCsrf(string $redirectTo): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect($redirectTo);
        }
    }

    private function notificationChannelConfig(string $code, array $current): array
    {
        $configured = isset($_POST['configured']);

        if ($code === 'mail') {
            $driver = $_POST['mail_driver'] ?? ($current['driver'] ?? 'php_mail');
            $driver = in_array($driver, ['php_mail', 'smtp'], true) ? $driver : 'php_mail';

            $config = [
                'driver' => $driver,
                'configured' => $configured,
                'from_email' => trim($_POST['from_email'] ?? ($current['from_email'] ?? '')),
                'from_name' => trim($_POST['from_name'] ?? ($current['from_name'] ?? config('app.name'))),
            ];

            if ($driver === 'smtp') {
                $password = (string) ($_POST['smtp_password'] ?? '');
                $config += [
                    'smtp_host' => trim($_POST['smtp_host'] ?? ($current['smtp_host'] ?? '')),
                    'smtp_port' => (int) ($_POST['smtp_port'] ?? ($current['smtp_port'] ?? 587)),
                    'smtp_encryption' => $_POST['smtp_encryption'] ?? ($current['smtp_encryption'] ?? 'tls'),
                    'smtp_username' => trim($_POST['smtp_username'] ?? ($current['smtp_username'] ?? '')),
                    'smtp_password' => $password !== '' ? $password : (string) ($current['smtp_password'] ?? ''),
                    'smtp_timeout' => (int) ($_POST['smtp_timeout'] ?? ($current['smtp_timeout'] ?? 20)),
                ];
            }

            return $config;
        }

        if ($code === 'telegram') {
            $botToken = (string) ($_POST['bot_token'] ?? '');

            return [
                'driver' => 'telegram_bot_api',
                'configured' => $configured,
                'bot_token' => $botToken !== '' ? $botToken : (string) ($current['bot_token'] ?? ''),
                'parse_mode' => $_POST['parse_mode'] ?? ($current['parse_mode'] ?? ''),
            ];
        }

        if ($code === 'whatsapp') {
            $provider = $_POST['provider'] ?? ($current['provider'] ?? 'meta_cloud');
            $accessToken = (string) ($_POST['access_token'] ?? '');

            return [
                'driver' => 'whatsapp_provider',
                'configured' => $configured,
                'provider' => $provider,
                'api_version' => trim($_POST['api_version'] ?? ($current['api_version'] ?? 'v20.0')),
                'phone_number_id' => trim($_POST['phone_number_id'] ?? ($current['phone_number_id'] ?? '')),
                'access_token' => $accessToken !== '' ? $accessToken : (string) ($current['access_token'] ?? ''),
                'endpoint' => trim($_POST['endpoint'] ?? ($current['endpoint'] ?? '')),
            ];
        }

        return $current + ['configured' => $configured];
    }

    private function testNotificationChannel(string $code, string $name, array $config): void
    {
        $recipient = trim($_POST['test_recipient'] ?? '');
        $message = trim($_POST['test_message'] ?? '');

        if ($recipient === '') {
            flash('error', $name . ' testi için test alıcısı zorunludur.');
            return;
        }

        if ($message === '') {
            $message = 'Otel Güvenlik Sistemi test bildirimi.';
        }

        $config['configured'] = true;

        try {
            $result = NotificationSenderFactory::make($code)->send([
                'recipient_name' => 'Test Alıcısı',
                'recipient_address' => $recipient,
                'subject' => 'Otel Güvenlik Test Bildirimi',
                'message' => $message . "\n\nTest zamanı: " . date('d.m.Y H:i:s'),
            ], $config);
        } catch (\Throwable $error) {
            flash('error', $name . ' test gönderimi hata verdi: ' . $error->getMessage());
            return;
        }

        $status = (string) ($result['status'] ?? 'failed');
        $error = trim((string) ($result['error'] ?? ''));
        $providerId = trim((string) ($result['provider_message_id'] ?? ''));

        if ($status === 'sent') {
            $note = $providerId !== '' ? ' Sağlayıcı mesaj ID: ' . $providerId : '';
            $operationalNote = (!isset($_POST['is_enabled']) || !isset($_POST['configured']))
                ? ' Canlı gönderim için Aktif ve Ayarları tamamlandı seçenekleri işaretli olmalı.'
                : '';
            flash('success', $name . ' test bildirimi gönderildi.' . $note . $operationalNote);
            return;
        }

        flash('error', $name . ' test bildirimi gönderilemedi. Durum: ' . $status . ($error !== '' ? ' - ' . $error : ''));
    }

    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
